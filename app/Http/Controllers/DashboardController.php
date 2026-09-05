<?php

namespace App\Http\Controllers;

use App\Models\Criterion;
use App\Models\MooraRun;
use App\Models\Period;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(InventoryService $inventory): View
    {
        $latestRun = MooraRun::with('period')->latest('id')->first();
        $pendingPeriod = Period::query()
            ->where('status', 'draft')
            ->latest('updated_at')
            ->latest('id')
            ->first();
        $run = $latestRun?->load([
            'period',
            'results' => fn ($query) => $query->with(['product', 'restockAction'])->orderBy('rank_system'),
        ]);
        $period = $run?->period ?? $pendingPeriod;
        $criteria = Criterion::active()->orderBy('code')->get();
        $dashboardCriteria = $run
            ? collect($run->criteria_snapshot)
            : $criteria->mapWithKeys(fn (Criterion $criterion): array => [$criterion->code => [
                'code' => $criterion->code,
                'name' => $criterion->name,
                'type' => $criterion->type,
                'weight' => (float) $criterion->weight,
                'source' => $criterion->value_source,
            ]]);

        $activeProducts = Product::where('active', true)->get();
        $inventorySummary = $inventory->summaryForProducts($activeProducts);

        return view('dashboard.index', [
            'run' => $run,
            'period' => $period,
            'pendingPeriod' => $pendingPeriod,
            'dashboardCriteria' => $dashboardCriteria,
            'productCount' => $run?->total_alternatives
                ?? ($period ? $period->sales()->count() : Product::where('active', true)->count()),
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
