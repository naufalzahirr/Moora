<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Period;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Support\Collection;

class InventoryService
{
    public function onHand(Product|int $product): float
    {
        $productId = $product instanceof Product ? $product->id : $product;

        return (float) InventoryMovement::where('product_id', $productId)->sum('quantity_change');
    }

    public function incoming(Product|int $product): float
    {
        $productId = $product instanceof Product ? $product->id : $product;

        return (float) PurchaseOrderItem::query()
            ->where('product_id', $productId)
            ->whereHas('purchaseOrder', fn ($query) => $query->whereIn('status', ['approved', 'ordered', 'partial']))
            ->selectRaw('COALESCE(SUM(ordered_quantity - received_quantity), 0) as total')
            ->value('total');
    }

    /** @return Collection<int, array{on_hand: float, incoming: float, projected: float}> */
    public function summaryForProducts(Collection $products): Collection
    {
        $productIds = $products->pluck('id');
        $onHand = InventoryMovement::query()
            ->whereIn('product_id', $productIds)
            ->selectRaw('product_id, COALESCE(SUM(quantity_change), 0) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');
        $incoming = PurchaseOrderItem::query()
            ->whereIn('product_id', $productIds)
            ->whereHas('purchaseOrder', fn ($query) => $query->whereIn('status', ['approved', 'ordered', 'partial']))
            ->selectRaw('product_id, COALESCE(SUM(ordered_quantity - received_quantity), 0) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        return $products->mapWithKeys(function (Product $product) use ($onHand, $incoming): array {
            $available = (float) ($onHand[$product->id] ?? 0);
            $onOrder = (float) ($incoming[$product->id] ?? 0);

            return [$product->id => [
                'on_hand' => $available,
                'incoming' => $onOrder,
                'projected' => $available + $onOrder,
            ]];
        });
    }

    public function recordAdjustment(Product $product, float $countedQuantity, string $occurredOn, User $user, ?string $notes = null): ?InventoryMovement
    {
        $currentQuantity = $this->onHand($product);
        $difference = round($countedQuantity - $currentQuantity, 2);
        if (abs($difference) < 0.005) {
            return null;
        }

        return InventoryMovement::create([
            'product_id' => $product->id,
            'movement_type' => 'adjustment',
            'quantity_change' => $difference,
            'occurred_on' => $occurredOn,
            'reference' => 'Stok opname',
            'notes' => $notes,
            'metadata' => ['before' => $currentQuantity, 'counted' => $countedQuantity],
            'recorded_by' => $user->id,
        ]);
    }

    public function establishPeriodBalance(Period $period, User $user): void
    {
        if ($period->runs()->exists()) {
            return;
        }

        $period->loadMissing(['sales.product', 'stockMovements']);
        $stocks = $period->stockMovements->keyBy('product_id');

        foreach ($period->sales as $sale) {
            $stock = $stocks->get($sale->product_id);
            if (! $stock) {
                continue;
            }

            $hasExistingBalance = InventoryMovement::where('product_id', $sale->product_id)->exists();
            if (! $hasExistingBalance) {
                InventoryMovement::create([
                    'product_id' => $sale->product_id,
                    'movement_type' => 'opening_balance',
                    'quantity_change' => $stock->ending_stock,
                    'occurred_on' => $period->end_date,
                    'reference' => $period->displayName(),
                    'notes' => 'Saldo awal stok berjalan dari stok akhir pada data operasional.',
                    'metadata' => ['period_id' => $period->id],
                    'recorded_by' => $user->id,
                ]);

                continue;
            }

            $saleMovement = InventoryMovement::firstOrCreate(
                ['period_id' => $period->id, 'product_id' => $sale->product_id, 'movement_type' => 'sale'],
                [
                    'quantity_change' => -1 * (float) $sale->sold_quantity,
                    'occurred_on' => $period->end_date,
                    'reference' => $period->displayName(),
                    'notes' => 'Penjualan agregat dari data operasional.',
                    'metadata' => ['source_reference' => $sale->source_reference],
                    'recorded_by' => $user->id,
                ]
            );

            $currentQuantity = $this->onHand($sale->product);
            $difference = round((float) $stock->ending_stock - $currentQuantity, 2);
            if (abs($difference) < 0.005) {
                continue;
            }

            InventoryMovement::updateOrCreate(
                ['period_id' => $period->id, 'product_id' => $sale->product_id, 'movement_type' => 'adjustment'],
                [
                    'quantity_change' => $difference,
                    'occurred_on' => $period->end_date,
                    'reference' => $period->displayName(),
                    'notes' => 'Penyesuaian ke stok akhir hasil stok opname periode.',
                    'metadata' => ['target_ending_stock' => (float) $stock->ending_stock, 'after_sale_movement_id' => $saleMovement->id],
                    'recorded_by' => $user->id,
                ]
            );
        }
    }
}
