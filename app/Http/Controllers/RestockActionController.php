<?php

namespace App\Http\Controllers;

use App\Http\Requests\RestockActionRequest;
use App\Models\MooraResult;
use App\Models\MooraRun;
use App\Models\RestockAction;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        if ($run) {
            $run->load([
                'period',
                'results' => fn ($query) => $query
                    ->with(['product.supplier', 'restockAction.processor', 'restockAction.purchaseOrder'])
                    ->orderBy('rank_system'),
            ]);
        }

        return view('restock-actions.index', compact('run', 'runs'));
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
        if ($action->exists && $action->purchase_order_id) {
            throw ValidationException::withMessages([
                'restock' => 'Barang ini sudah masuk pesanan pembelian. Ubah status melalui Pesanan Pembelian agar jejak penerimaan dan stok tetap konsisten.',
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

        $action->fill([
            'status' => $status,
            'approved_quantity' => $status === 'skipped' ? null : $validated['approved_quantity'],
            'notes' => $validated['notes'] ?? null,
            'processed_by' => $request->user()->id,
            'ordered_at' => null,
            'received_at' => null,
        ]);
        if ($status === 'proposed') {
            $action->proposed_by = $request->user()->id;
            $action->proposed_at = now();
            $action->approved_by = null;
            $action->approved_at = null;
        }
        if ($status === 'approved') {
            $action->approved_by = $request->user()->id;
            $action->approved_at = now();
            $action->proposed_by ??= $request->user()->id;
            $action->proposed_at ??= now();
        }
        if (in_array($status, ['pending', 'skipped'], true)) {
            $action->approved_by = null;
            $action->approved_at = null;
        }
        $action->save();

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
        $isOwner = $request->user()->role === 'owner';
        $validated = $request->validate([
            'status' => ['required', 'in:pending,proposed,approved,skipped'],
            'result_ids' => ['nullable', 'array'],
            'result_ids.*' => ['integer'],
            'all' => ['nullable', 'boolean'],
        ]);
        $status = $validated['status'];
        if (! $isOwner && ! in_array($status, ['pending', 'proposed'], true)) {
            abort(403, 'Petugas hanya dapat menyimpan draft atau mengirim usulan.');
        }
        if ($isOwner && ! in_array($status, ['pending', 'proposed', 'approved', 'skipped'], true)) {
            abort(403);
        }

        $query = $run->results()->with(['product', 'restockAction']);
        if (! $request->boolean('all')) {
            $ids = collect($validated['result_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique()->values();
            if ($ids->isEmpty()) {
                throw ValidationException::withMessages(['restock' => 'Pilih setidaknya satu barang untuk tindakan massal.']);
            }
            $query->whereIn('id', $ids);
        }

        $updated = 0;
        $skipped = 0;
        DB::transaction(function () use ($query, $request, $status, &$updated, &$skipped): void {
            $query->get()->each(function (MooraResult $result) use ($request, $status, &$updated, &$skipped): void {
                $action = RestockAction::firstOrNew(['moora_result_id' => $result->id]);
                if ($action->exists && $action->purchase_order_id) {
                    $skipped++;

                    return;
                }
                $quantity = $status === 'skipped'
                    ? null
                    : ($action->approved_quantity ?? $result->restock_quantity ?? 0);
                if ($status !== 'skipped' && (float) $quantity <= 0) {
                    $skipped++;

                    return;
                }

                $action->fill([
                    'status' => $status,
                    'approved_quantity' => $quantity,
                    'processed_by' => $request->user()->id,
                    'ordered_at' => null,
                    'received_at' => null,
                ]);
                if ($status === 'proposed') {
                    $action->proposed_by = $request->user()->id;
                    $action->proposed_at = now();
                    $action->approved_by = null;
                    $action->approved_at = null;
                }
                if ($status === 'approved') {
                    $action->proposed_by ??= $request->user()->id;
                    $action->proposed_at ??= now();
                    $action->approved_by = $request->user()->id;
                    $action->approved_at = now();
                }
                if (in_array($status, ['pending', 'skipped'], true)) {
                    $action->approved_by = null;
                    $action->approved_at = null;
                }
                $action->save();
                $updated++;
            });
        });

        if ($updated === 0) {
            throw ValidationException::withMessages([
                'restock' => 'Tidak ada barang yang dapat diperbarui. Barang yang sudah masuk pesanan tidak dapat diubah dari halaman ini.',
            ]);
        }

        $logger->log($request->user(), 'restock.bulk_updated', "Memperbarui {$updated} tindak lanjut restock secara massal.", [
            'run_id' => $run->id,
            'status' => $status,
            'updated' => $updated,
            'skipped' => $skipped,
            'all' => $request->boolean('all'),
        ]);

        $message = match ($status) {
            'proposed' => "{$updated} usulan restock dikirim ke Owner.",
            'approved' => "{$updated} barang disetujui sesuai saran yang tersimpan.",
            'skipped' => "{$updated} barang ditandai tidak dipesan.",
            default => "{$updated} draft tindak lanjut berhasil disimpan.",
        };
        if ($skipped) {
            $message .= " {$skipped} barang dilewati karena sudah diproses atau tidak memiliki saran jumlah.";
        }

        return back()->with('success', $message);
    }
}
