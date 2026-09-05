<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\MooraRun;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RestockAction;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = PurchaseOrder::query()
            ->with(['supplier', 'items.product'])
            ->latest('id')
            ->get();
        $order = $request->integer('order')
            ? $orders->firstWhere('id', $request->integer('order')) ?? abort(404)
            : $orders->first();

        if ($order) {
            $order->load(['supplier', 'run.period', 'creator', 'approver', 'items.product']);
        }

        return view('purchase-orders.index', compact('orders', 'order'));
    }

    public function createFromRun(MooraRun $run, Request $request, ActivityLogger $logger): RedirectResponse
    {
        abort_unless($request->user()->role === 'owner', 403);
        $run->load(['results.product.supplier', 'results.restockAction']);
        $actions = $run->results
            ->map(fn ($result) => $result->restockAction?->setRelation('result', $result))
            ->filter(fn ($action) => $action
                && $action->status === 'approved'
                && ! $action->purchase_order_id
                && (float) $action->approved_quantity > 0)
            ->values();

        if ($actions->isEmpty()) {
            throw ValidationException::withMessages([
                'restock' => 'Belum ada barang berstatus siap dipesan yang belum masuk ke pesanan pembelian.',
            ]);
        }

        $withoutSupplier = $actions->filter(fn (RestockAction $action): bool => ! $action->result->product?->supplier);
        if ($withoutSupplier->isNotEmpty()) {
            throw ValidationException::withMessages([
                'restock' => 'Tetapkan supplier pada barang berikut sebelum membuat pesanan: '.$withoutSupplier
                    ->map(fn (RestockAction $action): string => $action->result->displayProductName())
                    ->join(', ').'.',
            ]);
        }

        $orders = DB::transaction(function () use ($actions, $run, $request): array {
            return $actions->groupBy(fn (RestockAction $action): int => $action->result->product->supplier_id)
                ->map(function ($supplierActions, int $supplierId) use ($run, $request): PurchaseOrder {
                    $leadTime = (int) $supplierActions->first()->result->product->supplier->lead_time_days;
                    $order = PurchaseOrder::create([
                        'order_number' => $this->nextOrderNumber(),
                        'supplier_id' => $supplierId,
                        'moora_run_id' => $run->id,
                        'status' => 'draft',
                        'expected_at' => now()->addDays($leadTime)->toDateString(),
                        'created_by' => $request->user()->id,
                    ]);

                    foreach ($supplierActions as $action) {
                        PurchaseOrderItem::create([
                            'purchase_order_id' => $order->id,
                            'product_id' => $action->result->product_id,
                            'moora_result_id' => $action->moora_result_id,
                            'suggested_quantity' => $action->result->restock_quantity,
                            'ordered_quantity' => $action->approved_quantity,
                        ]);
                        $action->update(['purchase_order_id' => $order->id]);
                    }

                    return $order;
                })
                ->values()
                ->all();
        });

        $logger->log($request->user(), 'purchase_order.created', 'Membuat '.count($orders).' draft pesanan pembelian dari rekomendasi restock.', [
            'run_id' => $run->id,
            'purchase_order_ids' => collect($orders)->pluck('id')->all(),
        ]);

        return redirect()->route('purchase-orders.index', ['order' => $orders[0]->id])
            ->with('success', count($orders).' draft pesanan pembelian berhasil dibuat dan dikelompokkan per supplier.');
    }

    public function updateStatus(Request $request, PurchaseOrder $order, ActivityLogger $logger): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'ordered', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $nextStatus = $validated['status'];

        if ($nextStatus === 'approved') {
            abort_unless($request->user()->role === 'owner', 403);
            abort_unless($order->status === 'draft', 422, 'Hanya draft yang dapat disetujui.');
        }
        if ($nextStatus === 'ordered') {
            abort_unless($order->status === 'approved', 422, 'Pesanan harus disetujui sebelum dikirim ke supplier.');
        }
        if ($nextStatus === 'cancelled') {
            abort_unless(in_array($order->status, ['draft', 'approved'], true), 422, 'Pesanan yang sudah dikirim tidak dapat dibatalkan dari halaman ini.');
            abort_unless($request->user()->role === 'owner', 403);
        }

        DB::transaction(function () use ($order, $nextStatus, $validated, $request): void {
            $updates = ['status' => $nextStatus, 'notes' => $validated['notes'] ?? $order->notes];
            if ($nextStatus === 'approved') {
                $updates += ['approved_at' => now(), 'approved_by' => $request->user()->id];
            }
            if ($nextStatus === 'ordered') {
                $updates['ordered_at'] = now();
            }
            $order->update($updates);

            if ($nextStatus === 'ordered') {
                RestockAction::where('purchase_order_id', $order->id)->update(['status' => 'ordered', 'ordered_at' => now()]);
            }
            if ($nextStatus === 'cancelled') {
                RestockAction::where('purchase_order_id', $order->id)->update(['status' => 'approved', 'purchase_order_id' => null]);
            }
        });

        $logger->log($request->user(), 'purchase_order.status_updated', "Mengubah {$order->order_number} menjadi {$order->fresh()->label()}.", [
            'purchase_order_id' => $order->id,
            'status' => $nextStatus,
        ]);

        return back()->with('success', 'Status pesanan pembelian berhasil diperbarui.');
    }

    public function receive(Request $request, PurchaseOrder $order, ActivityLogger $logger): RedirectResponse
    {
        abort_unless(in_array($order->status, ['ordered', 'partial'], true), 422, 'Hanya pesanan yang telah dikirim yang dapat diterima.');
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $order->load('items.product');

        DB::transaction(function () use ($order, $validated, $request): void {
            foreach ($order->items as $item) {
                if (! array_key_exists($item->id, $validated['items']) || $validated['items'][$item->id] === null || $validated['items'][$item->id] === '') {
                    continue;
                }

                $receivedNow = (float) $validated['items'][$item->id];
                $remainingQuantity = $item->remainingQuantity();
                if ($receivedNow > $remainingQuantity) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}" => "Penerimaan {$item->product->name} tidak boleh melebihi sisa ".number_format($remainingQuantity, 0, ',', '.').'.',
                    ]);
                }
                if ($item->product->usesWholeUnits() && floor($receivedNow) !== $receivedNow) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}" => "Penerimaan {$item->product->name} harus berupa bilangan bulat karena satuannya {$item->product->unit}.",
                    ]);
                }

                if ($receivedNow <= 0) {
                    continue;
                }
                $receivedQuantity = (float) $item->received_quantity + $receivedNow;

                InventoryMovement::create([
                    'product_id' => $item->product_id,
                    'purchase_order_item_id' => $item->id,
                    'movement_type' => 'receipt',
                    'quantity_change' => $receivedNow,
                    'occurred_on' => now()->toDateString(),
                    'reference' => $order->order_number,
                    'notes' => 'Penerimaan barang dari '.$order->supplier->name.'.',
                    'metadata' => ['previous_received' => (float) $item->received_quantity, 'received_now' => $receivedNow, 'received_total' => $receivedQuantity],
                    'recorded_by' => $request->user()->id,
                ]);
                $item->update(['received_quantity' => $receivedQuantity]);
                RestockAction::where('purchase_order_id', $order->id)
                    ->where('moora_result_id', $item->moora_result_id)
                    ->update([
                        'status' => $receivedQuantity >= (float) $item->ordered_quantity ? 'received' : 'ordered',
                        'received_at' => $receivedQuantity >= (float) $item->ordered_quantity ? now() : null,
                    ]);
            }

            $order->refresh()->load('items');
            $allReceived = $order->items->every(fn (PurchaseOrderItem $item): bool => (float) $item->received_quantity >= (float) $item->ordered_quantity);
            $hasReceived = $order->items->contains(fn (PurchaseOrderItem $item): bool => (float) $item->received_quantity > 0);
            $order->update([
                'status' => $allReceived ? 'received' : ($hasReceived ? 'partial' : 'ordered'),
                'received_at' => $allReceived ? now() : null,
            ]);
        });

        $logger->log($request->user(), 'purchase_order.received', "Mencatat penerimaan untuk {$order->order_number}.", [
            'purchase_order_id' => $order->id,
            'status' => $order->fresh()->status,
        ]);

        return back()->with('success', 'Penerimaan barang dicatat dan stok berjalan telah diperbarui.');
    }

    private function nextOrderNumber(): string
    {
        $prefix = 'PO-'.now()->format('YmdHis');
        $sequence = PurchaseOrder::where('order_number', 'like', "{$prefix}-%")->count() + 1;

        return sprintf('%s-%03d', $prefix, $sequence);
    }
}
