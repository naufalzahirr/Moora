<?php

namespace App\Http\Controllers;

use App\Http\Requests\DatasetImportRequest;
use App\Http\Requests\DatasetRequest;
use App\Models\Criterion;
use App\Models\Period;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Services\ActivityLogger;
use App\Services\MooraService;
use App\Services\SalesImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class DatasetController extends Controller
{
    public function index(Request $request): View
    {
        $period = $request->integer('period')
            ? Period::findOrFail($request->integer('period'))
            : Period::latest('id')->first();

        $sales = $period
            ? $period->sales()->with('product')->orderBy('product_id')->get()
            : collect();
        $stocks = $period?->stockMovements()->get()->keyBy('product_id') ?? collect();
        $criteria = Criterion::orderBy('code')->get();

        return view('datasets.index', [
            'period' => $period,
            'periods' => Period::latest('updated_at')->latest('id')->get(),
            'sales' => $sales,
            'stocks' => $stocks,
            'criteria' => $criteria,
            'completeCount' => $sales->filter(function (Sale $sale) use ($stocks, $criteria): bool {
                $stock = $stocks->get($sale->product_id);

                return $criteria->every(fn (Criterion $criterion): bool => $criterion->valueFor($sale, $stock) !== null);
            })->count(),
        ]);
    }

    public function storePeriod(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date', 'before_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:today'],
        ], [
            'start_date.before_or_equal' => 'Tanggal awal tidak boleh melewati hari ini.',
            'end_date.before_or_equal' => 'Tanggal akhir tidak boleh melewati hari ini.',
        ]);
        $this->ensureBasePeriodAvailable($validated['name'], $validated['start_date'], $validated['end_date']);

        if (! Product::where('active', true)->exists()) {
            throw ValidationException::withMessages([
                'period' => 'Tambahkan setidaknya satu barang aktif sebelum membuat data baru.',
            ]);
        }

        $period = DB::transaction(function () use ($request, $validated): Period {
            $period = Period::create([...$validated, 'created_by' => $request->user()->id, 'status' => 'draft']);
            foreach (Product::where('active', true)->orderBy('id')->get() as $product) {
                Sale::create([
                    'period_id' => $period->id,
                    'product_id' => $product->id,
                    'sold_quantity' => 0,
                    'sales_value' => 0,
                    'source_reference' => 'Input manual',
                ]);
            }

            return $period;
        });

        $logger->log($request->user(), 'period.created', "Membuat data operasional {$period->name}.", ['period_id' => $period->id]);

        return redirect()->route('datasets.index', ['period' => $period])->with('success', 'Data operasional baru siap diisi.');
    }

    public function import(
        DatasetImportRequest $request,
        SalesImportService $importer,
        ActivityLogger $logger
    ): RedirectResponse {
        $this->ensureBasePeriodAvailable(
            $request->string('name')->toString(),
            $request->date('start_date')->toDateString(),
            $request->date('end_date')->toDateString(),
        );
        $rows = $importer->parse($request->file('file'));
        $uploadedFile = $request->file('file');
        $archive = $this->archiveSourceFile($uploadedFile);
        $existingProducts = Product::query()
            ->whereIn('code', collect($rows)->pluck('code')->all())
            ->get()
            ->keyBy(fn (Product $product): string => mb_strtolower($product->code));
        $nameMismatches = collect($rows)
            ->filter(fn (array $row): bool => ($existing = $existingProducts->get(mb_strtolower($row['code']))) !== null && $existing->name !== $row['name'])
            ->pluck('code')
            ->values();

        try {
            $period = DB::transaction(function () use ($request, $rows, $archive): Period {
                $period = Period::create([
                    'name' => $request->string('name'),
                    'start_date' => $request->date('start_date'),
                    'end_date' => $request->date('end_date'),
                    'source_file' => $request->file('file')->getClientOriginalName(),
                    ...$archive,
                    'status' => 'draft',
                    'created_by' => $request->user()->id,
                ]);

                foreach ($rows as $row) {
                    $product = Product::firstOrCreate(
                        ['code' => $row['code']],
                        ['name' => $row['name'], 'unit' => 'pcs', 'active' => true]
                    );

                    Sale::updateOrCreate(
                        ['period_id' => $period->id, 'product_id' => $product->id],
                        [
                            'sold_quantity' => $row['sold_quantity'],
                            'sales_value' => $row['sales_value'],
                            'source_reference' => $request->file('file')->getClientOriginalName(),
                        ]
                    );
                }

                return $period;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($archive['source_path']);

            throw $exception;
        }

        $logger->log($request->user(), 'dataset.imported', "Mengimpor {$period->source_file} untuk {$period->name}.", [
            'period_id' => $period->id,
            'rows' => count($rows),
            'source_sha256' => $period->source_sha256,
            'name_mismatches' => $nameMismatches->all(),
        ]);

        return redirect()->route('datasets.index', ['period' => $period])
            ->with('success', count($rows).' baris penjualan berhasil diimpor. Lengkapi stok akhir sebelum membuat rekomendasi.'.($nameMismatches->isNotEmpty() ? ' Nama master untuk '.$nameMismatches->join(', ').' tidak diubah; perbarui melalui Data Barang bila diperlukan.' : ''));
    }

    public function update(DatasetRequest $request, ActivityLogger $logger, MooraService $moora): RedirectResponse
    {
        $period = Period::findOrFail($request->integer('period_id'));
        $rows = $request->validated('rows');

        if ($period->isLocked()) {
            throw ValidationException::withMessages([
                'period' => 'Data yang sudah selesai terkunci untuk menjaga riwayat rekomendasi. Buat pembaruan untuk melakukan perubahan.',
            ]);
        }

        DB::transaction(function () use ($request, $period, $rows): void {
            foreach ($rows as $productId => $row) {
                $product = Product::findOrFail($productId);
                Sale::updateOrCreate(
                    ['period_id' => $period->id, 'product_id' => $product->id],
                    [
                        'sold_quantity' => $row['sold_quantity'],
                        'sales_value' => $row['sales_value'],
                        'manual_yi' => $row['manual_yi'] ?? null,
                        'source_reference' => $period->source_file ?? 'Input manual',
                    ]
                );
                StockMovement::updateOrCreate(
                    ['period_id' => $period->id, 'product_id' => $product->id],
                    [
                        'ending_stock' => $row['ending_stock'],
                        'source' => 'Input stok akhir',
                        'recorded_by' => $request->user()->id,
                    ]
                );
            }
            $period->update(['status' => 'ready']);
        });

        $logger->log($request->user(), 'dataset.updated', "Menyimpan data operasional {$period->displayName()}.", ['period_id' => $period->id]);

        if ($request->string('next')->toString() === 'calculate') {
            $period->load('sales');
            $manualValues = $period->sales->mapWithKeys(
                fn (Sale $sale): array => [$sale->product_id => $sale->manual_yi]
            )->all();
            $run = $moora->execute($period, $request->user(), $manualValues);
            $logger->log($request->user(), 'moora.executed', "Membuat rekomendasi untuk {$period->displayName()}.", [
                'period_id' => $period->id,
                'run_id' => $run->id,
                'accuracy' => $run->accuracy,
            ]);

            return redirect()->route('calculations.results', $run)
                ->with('success', 'Data disimpan dan rekomendasi restock berhasil dibuat.');
        }

        return redirect()->route('datasets.index', ['period' => $period])
            ->with('success', 'Data berhasil disimpan dan siap dibuatkan rekomendasi.');
    }

    public function revise(Request $request, Period $period, ActivityLogger $logger): RedirectResponse
    {
        if (! $period->isLocked()) {
            throw ValidationException::withMessages([
                'period' => 'Hanya data yang sudah selesai yang dapat dibuatkan pembaruan.',
            ]);
        }

        [$revision, $created] = DB::transaction(function () use ($period, $request): array {
            $sourcePeriod = Period::query()->lockForUpdate()->findOrFail($period->id);
            $existingDraft = $sourcePeriod->revisions()
                ->where('status', 'draft')
                ->latest('id')
                ->first();
            if ($existingDraft) {
                return [$existingDraft, false];
            }

            $sourcePeriod->load(['sales', 'stockMovements']);
            $revision = Period::create([
                'name' => $this->nextRevisionName($sourcePeriod->name),
                'start_date' => $sourcePeriod->start_date,
                'end_date' => $sourcePeriod->end_date,
                'source_file' => $sourcePeriod->source_file,
                'source_path' => $sourcePeriod->source_path,
                'source_sha256' => $sourcePeriod->source_sha256,
                'source_size' => $sourcePeriod->source_size,
                'source_mime' => $sourcePeriod->source_mime,
                'status' => 'draft',
                'created_by' => $request->user()->id,
                'revision_of_id' => $sourcePeriod->id,
            ]);

            foreach ($sourcePeriod->sales as $sale) {
                Sale::create([
                    'period_id' => $revision->id,
                    'product_id' => $sale->product_id,
                    'sold_quantity' => $sale->sold_quantity,
                    'sales_value' => $sale->sales_value,
                    'manual_yi' => $sale->manual_yi,
                    'source_reference' => $sale->source_reference,
                ]);
            }
            foreach ($sourcePeriod->stockMovements as $stock) {
                StockMovement::create([
                    'period_id' => $revision->id,
                    'product_id' => $stock->product_id,
                    'ending_stock' => $stock->ending_stock,
                    'source' => $stock->source,
                    'recorded_by' => $request->user()->id,
                ]);
            }

            return [$revision, true];
        });

        if ($created) {
            $logger->log($request->user(), 'period.revised', "Membuat pembaruan {$revision->displayName()} dari {$period->displayName()}.", [
                'source_period_id' => $period->id,
                'revision_period_id' => $revision->id,
            ]);
        }

        return redirect()->route('datasets.index', ['period' => $revision])
            ->with('success', $created
                ? 'Pembaruan data dibuat sebagai draft. Ubah data, lalu buat rekomendasi sebagai riwayat baru.'
                : 'Draft pembaruan yang sudah ada dibuka kembali. Tidak ada pembaruan ganda yang dibuat.');
    }

    public function discardRevision(Request $request, Period $period, ActivityLogger $logger): RedirectResponse
    {
        abort_unless($request->user()->role === 'owner', 403);
        if ($period->status !== 'draft' || ! $period->revision_of_id) {
            throw ValidationException::withMessages([
                'period' => 'Hanya draft pembaruan yang belum dihitung yang dapat dibatalkan.',
            ]);
        }

        DB::transaction(function () use ($period): void {
            $period->sales()->delete();
            $period->stockMovements()->delete();
            $period->delete();
        });

        $logger->log($request->user(), 'period.revision_discarded', "Membatalkan draft pembaruan {$period->displayName()}.", [
            'period_id' => $period->id,
            'source_period_id' => $period->revision_of_id,
        ]);

        return redirect()->route('datasets.index', ['period' => $period->revision_of_id])
            ->with('success', 'Draft pembaruan dibatalkan. Riwayat data asli tidak berubah.');
    }

    public function downloadSource(Period $period): BinaryFileResponse
    {
        abort_unless($period->source_path && Storage::disk('local')->exists($period->source_path), 404);

        return response()->download(
            Storage::disk('local')->path($period->source_path),
            $period->source_file ?? 'sumber-data',
            $period->source_mime ? ['Content-Type' => $period->source_mime] : []
        );
    }

    public function downloadTemplate(): Response
    {
        return response("kode_barang,nama_barang,jumlah_terjual,nilai_penjualan\nBRG-001,Contoh Barang,0,0\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template-impor-penjualan.csv"',
        ]);
    }

    /** @return array{source_path: string, source_sha256: string, source_size: int, source_mime: string|null} */
    private function archiveSourceFile(UploadedFile $file): array
    {
        $realPath = $file->getRealPath();
        $hash = $realPath ? hash_file('sha256', $realPath) : false;
        $size = (int) $file->getSize();
        $mime = $file->getMimeType();
        if ($hash === false) {
            throw new \RuntimeException('Checksum berkas sumber tidak dapat dibuat.');
        }

        $path = $file->storeAs(
            'imports/'.now()->format('Y/m'),
            Str::uuid().'.'.strtolower($file->getClientOriginalExtension()),
            'local'
        );
        if ($path === false) {
            throw new \RuntimeException('Berkas sumber tidak dapat diarsipkan.');
        }

        return [
            'source_path' => $path,
            'source_sha256' => $hash,
            'source_size' => $size,
            'source_mime' => $mime,
        ];
    }

    private function nextRevisionName(string $name): string
    {
        $prefix = preg_replace('/ \((?:Revisi|Pembaruan) \d+\)$/u', '', $name) ?: $name;
        $revision = 1;

        while (Period::whereIn('name', [
            "{$prefix} (Revisi {$revision})",
            "{$prefix} (Pembaruan {$revision})",
        ])->exists()) {
            $revision++;
        }

        return "{$prefix} (Pembaruan {$revision})";
    }

    private function ensureBasePeriodAvailable(string $name, string $startDate, string $endDate): void
    {
        if (Period::where('name', $name)->exists()) {
            throw ValidationException::withMessages(['name' => 'Nama data sudah digunakan. Gunakan nama lain agar riwayat mudah dibedakan.']);
        }

        $overlap = Period::query()
            ->whereNull('revision_of_id')
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->first();
        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => "Rentang tanggal bertumpang tindih dengan data {$overlap->displayName()}. Buat pembaruan bila data tersebut yang perlu diperbarui.",
            ]);
        }
    }
}
