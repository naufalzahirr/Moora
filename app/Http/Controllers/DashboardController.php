<?php

namespace App\Http\Controllers;

use App\Models\Period;
use App\Models\Product;
use App\Models\StockTransaction;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $period = Period::orderByDesc('start_date')->latest('id')->first();
        $run = $period?->runs()->latest('id')->first();
        $stocks = $period?->stockMovements()->get()->keyBy('product_id') ?? collect();
        $sales = $period?->sales()->get() ?? collect();
        $completeCount = $sales->filter(fn ($sale) => $sale->sold_quantity !== null
            && $sale->sales_value !== null && $stocks->get($sale->product_id)?->ending_stock !== null)->count();

        return view('dashboard.index', [
            'transactionCount' => StockTransaction::count(),
            'openingCount' => StockTransaction::where('type', 'opening')->whereHas('product', fn ($q) => $q->where('active', true))->count(),
            'period' => $period,
            'run' => $run,
            'productCount' => Product::where('active', true)->count(),
            'completeCount' => $completeCount,
            'totalCount' => $sales->count(),
            'results' => $run?->results()->orderBy('rank_system')->take(5)->get() ?? collect(),
        ]);
    }
}
