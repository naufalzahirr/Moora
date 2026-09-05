<?php

namespace App\Http\Controllers;

use App\Models\MooraRun;
use App\Models\Period;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Services\InventoryService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(InventoryService $inventory): View
    {
        $latestRun = MooraRun::with('period')->latest('id')->first();
        $pendingPeriod = Period::query()
            ->whereIn('status', ['draft', 'ready'])
            ->latest('updated_at')
            ->latest('id')
            ->first();
        $run = $latestRun?->load([
            'period',
            'results' => fn ($query) => $query->with(['product', 'restockAction'])->orderBy('rank_system'),
        ]);
        $period = $run?->period ?? $pendingPeriod;
        $activeProducts = Product::where('active', true)->get();
        $inventorySummary = $inventory->summaryForProducts($activeProducts);

        return view('dashboard.index', [
            'run' => $run,
            'period' => $period,
            'pendingPeriod' => $pendingPeriod,
            'productCount' => $activeProducts->count(),
            'proposedCount' => $run?->results->filter(fn ($result) => $result->restockAction?->status === 'proposed')->count() ?? 0,
            'pendingOrderCount' => PurchaseOrder::whereIn('status', ['draft', 'approved'])->count(),
            'awaitingReceiptCount' => PurchaseOrder::whereIn('status', ['ordered', 'partial'])->count(),
            'priorities' => $run?->results->filter(fn ($result) => (float) $result->restock_quantity > 0
                && ! $result->restockAction?->purchase_order_id
                && ! in_array($result->restockAction?->status, ['ordered', 'received', 'skipped'], true)
            )->take(5) ?? collect(),
            'inventorySummary' => $inventorySummary,
            'lowStockCount' => $activeProducts->filter(
                fn (Product $product): bool => (float) $product->minimum_stock > 0
                    && (float) ($inventorySummary[$product->id]['on_hand'] ?? 0) < (float) $product->minimum_stock
            )->count(),
            'minimumStockUnconfiguredCount' => $activeProducts->filter(
                fn (Product $product): bool => (float) $product->minimum_stock <= 0
            )->count(),
            'incomingProductCount' => $inventorySummary->filter(
                fn (array $summary): bool => (float) $summary['incoming'] > 0
            )->count(),
            'freshness' => $period?->freshness(),
        ]);
    }
}
