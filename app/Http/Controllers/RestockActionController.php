<?php

namespace App\Http\Controllers;

use App\Http\Requests\RestockActionRequest;
use App\Models\MooraResult;
use App\Models\MooraRun;
use App\Models\RestockAction;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RestockActionController extends Controller
{
    public function index(Request $request): View
    {
        $runs = MooraRun::with('period')->latest('id')->get();
        $run = $request->integer('run')
            ? MooraRun::findOrFail($request->integer('run'))
            : $runs->first();

        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();
        $supplier = $request->integer('supplier');
        $results = new LengthAwarePaginator([], 0, 50);
        $statusCounts = collect();
        $purchaseGroups = collect();
        $suppliers = Supplier::orderBy('name')->get();
        if ($run) {
            $run->load('period');
            $statusCounts = $run->results()->leftJoin('restock_actions', 'restock_actions.moora_result_id', '=', 'moora_results.id')
                ->selectRaw("COALESCE(restock_actions.status, 'pending') as state, COUNT(*) as total")
                ->groupBy('state')->pluck('total', 'state');
            $purchaseGroups = $run->results()->with(['product.supplier', 'restockAction'])
                ->whereHas('restockAction', fn ($query) => $query->where('status', 'approved')->whereNull('purchase_order_id')->where('approved_quantity', '>', 0))
                ->get()->toBase()->groupBy(fn ($result) => $result->product?->supplier_id ?? 'missing');
            $query = $run->results()->with(['product.supplier', 'restockAction.purchaseOrder'])->orderBy('rank_system');
            if ($search !== '') {
                $query->searchProduct($search);
            }
            if ($status === 'pending') {
                $query->where(fn ($query) => $query->whereDoesntHave('restockAction')->orWhereHas('restockAction', fn ($query) => $query->where('status', 'pending')));
            } elseif (in_array($status, ['proposed', 'approved', 'ordered', 'received', 'skipped'], true)) {
                $query->whereHas('restockAction', fn ($query) => $query->where('status', $status));
            }
            if ($supplier) {
                $query->whereHas('product', fn ($query) => $query->where('supplier_id', $supplier));
            }
            $results = $query->paginate(50)->withQueryString();
        }

        return view('restock-actions.index', compact('run', 'runs', 'results', 'search', 'status', 'supplier', 'suppliers', 'statusCounts', 'purchaseGroups'));
    }

    public function update(
        RestockActionRequest $request,
        MooraRun $run,
        MooraResult $result,
        ActivityLogger $logger,
    ): RedirectResponse {
        abort_unless($result->moora_run_id === $run->id, 404);

        $validated = $request->validated();
        $result->loadMissing('product');
        $action = RestockAction::firstOrNew(['moora_result_id' => $result->id]);
        if (! $action->canBeEditedBy($request->user())) {
            throw ValidationException::withMessages([
                'restock' => 'Keputusan ini terkunci. Hanya Owner dapat mengubah keputusan akhir; barang dalam pesanan diproses melalui Pesanan Pembelian.',
            ]);
        }
        $status = $validated['status'];
        $isOwner = $request->user()->role === 'owner';

        if (! $isOwner && ! in_array($status, ['pending', 'proposed'], true)) {
            abort(403, 'Petugas dapat menyiapkan usulan, tetapi persetujuan atau penolakan akhir dilakukan Owner.');
        }
        if ($status === 'approved') {
            abort_unless($isOwner, 403);
        }
        if ($status === 'skipped') {
            abort_unless($isOwner, 403);
        }
        if ($status !== 'skipped' && $result->product?->usesWholeUnits()
            && floor((float) $validated['approved_quantity']) !== (float) $validated['approved_quantity']) {
            throw ValidationException::withMessages([
                'approved_quantity' => "Jumlah {$result->displayProductName()} harus berupa bilangan bulat karena satuannya {$result->product->unit}.",
            ]);
        }

        $this->saveDecision($action, $status, $validated['approved_quantity'] ?? null, $validated['notes'] ?? null, $request->user());

        $logger->log($request->user(), 'restock.action_updated', "Memperbarui tindak lanjut {$result->displayProductName()} menjadi {$action->label()}.", [
            'run_id' => $run->id,
            'result_id' => $result->id,
            'restock_action_id' => $action->id,
            'status' => $action->status,
            'approved_quantity' => $action->approved_quantity,
        ]);

        return back()->with('success', match ($status) {
            'proposed' => 'Usulan restock dikirim dan menunggu persetujuan Owner.',
            'approved' => 'Keputusan Owner tersimpan dan barang siap dimasukkan ke pesanan pembelian.',
            'skipped' => 'Keputusan untuk tidak memesan barang telah disimpan.',
            default => 'Tindak lanjut restock berhasil disimpan.',
        });
    }

    public function bulkUpdate(Request $request, MooraRun $run, ActivityLogger $logger): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,proposed,approved,skipped'],
            'result_ids' => ['nullable', 'array'],
            'result_ids.*' => ['integer', 'distinct'],
            'all' => ['nullable', 'boolean'],
            'input_mode' => ['nullable', 'in:edited,suggested'],
            'rows' => ['nullable', 'array'],
        ]);
        $status = $validated['status'];
        if (! $request->user()->isOwner() && ! in_array($status, ['pending', 'proposed'], true)) {
            abort(403, 'Petugas hanya dapat menyimpan draft atau mengirim usulan.');
        }

        $ids = collect($validated['result_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique()->values();
        if (! $request->boolean('all') && $ids->isEmpty()) {
            throw ValidationException::withMessages(['restock' => 'Pilih setidaknya satu barang untuk tindakan massal.']);
        }
        $query = $run->results()->with('product');
        if (! $request->boolean('all')) {
            $query->whereIn('id', $ids);
            if ((clone $query)->count() !== $ids->count()) {
                throw ValidationException::withMessages(['restock' => 'Pilihan barang tidak sesuai dengan rekomendasi yang sedang dibuka.']);
            }
        }

        $updated = 0;
        $skipped = 0;
        DB::transaction(function () use ($query, $request, $status, &$updated, &$skipped): void {
            foreach ($query->lockForUpdate()->get() as $result) {
                $action = RestockAction::firstOrNew(['moora_result_id' => $result->id]);
                if (! $action->canBeEditedBy($request->user())
                    || ($request->boolean('all') && in_array($action->status, ['approved', 'skipped'], true))) {
                    $skipped++;

                    continue;
                }

                $quantity = $action->approved_quantity ?? $result->restock_quantity;
                $notes = $action->notes;
                if ($request->input('input_mode') === 'edited') {
                    $row = $request->validate([
                        "rows.{$result->id}" => ['required', 'array'],
                        "rows.{$result->id}.approved_quantity" => [
                            $status === 'skipped' ? 'nullable' : 'required', 'numeric',
                            in_array($status, ['approved', 'proposed'], true) ? 'gt:0' : 'min:0',
                        ],
                        "rows.{$result->id}.notes" => ['nullable', 'string', 'max:1000'],
                    ])['rows'][$result->id];
                    $quantity = $row['approved_quantity'] ?? null;
                    $notes = $row['notes'] ?? null;
                } elseif ($request->input('input_mode') === 'suggested') {
                    $quantity = $result->restock_quantity;
                }

                if ($status !== 'skipped' && in_array($status, ['approved', 'proposed'], true) && (float) $quantity <= 0) {
                    $skipped++;

                    continue;
                }
                if ($status !== 'skipped' && $result->product?->usesWholeUnits()
                    && floor((float) $quantity) !== (float) $quantity) {
                    throw ValidationException::withMessages([
                        "rows.{$result->id}.approved_quantity" => "Jumlah {$result->displayProductName()} harus berupa bilangan bulat.",
                    ]);
                }

                $this->saveDecision($action, $status, $quantity, $notes, $request->user());
                $updated++;
            }
            if ($updated === 0) {
                throw ValidationException::withMessages([
                    'restock' => 'Tidak ada barang yang dapat diperbarui. Periksa jumlah, keputusan Owner, dan barang yang sudah masuk pesanan.',
                ]);
            }
        });

        $logger->log($request->user(), 'restock.bulk_updated', "Memperbarui {$updated} tindak lanjut restock secara massal.", [
            'run_id' => $run->id, 'status' => $status, 'updated' => $updated,
            'skipped' => $skipped, 'all' => $request->boolean('all'),
        ]);
        $message = match ($status) {
            'proposed' => "{$updated} usulan restock dikirim ke Owner.",
            'approved' => "Jumlah keputusan untuk {$updated} barang disimpan dan disetujui.",
            'skipped' => "{$updated} barang ditandai tidak dipesan.",
            default => "{$updated} draft tindak lanjut berhasil disimpan.",
        };
        if ($skipped) {
            $message .= " {$skipped} barang dilewati karena terkunci atau tidak memiliki jumlah yang dapat dipesan.";
        }

        return back()->with('success', $message);
    }

    private function saveDecision(RestockAction $action, string $status, mixed $quantity, ?string $notes, User $user): void
    {
        $action->fill([
            'status' => $status,
            'approved_quantity' => $status === 'skipped' ? null : $quantity,
            'notes' => $notes,
            'processed_by' => $user->id,
            'ordered_at' => null,
            'received_at' => null,
        ]);
        if ($status === 'proposed') {
            $action->proposed_by = $user->id;
            $action->proposed_at = now();
        }
        if ($status === 'approved') {
            $action->approved_by = $user->id;
            $action->approved_at = now();
            $action->proposed_by ??= $user->id;
            $action->proposed_at ??= now();
        } else {
            $action->approved_by = null;
            $action->approved_at = null;
        }
        $action->save();
    }
}
