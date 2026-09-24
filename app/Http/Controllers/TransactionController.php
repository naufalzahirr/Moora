<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockTransaction;
use App\Services\ActivityLogger;
use App\Services\TransactionService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index()
    {
        $products = Product::where('active', true)->orderBy('name')->get();
        $balances = StockTransaction::selectRaw("product_id, SUM(CASE WHEN type IN ('sale', 'decrease') THEN -quantity ELSE quantity END) as balance")->groupBy('product_id')->pluck('balance', 'product_id');
        $transactions = StockTransaction::with('product')->orderByDesc('occurred_on')->latest('id')->paginate(30);

        return view('transactions.index', compact('products', 'balances', 'transactions'));
    }

    public function store(Request $request, TransactionService $service, ActivityLogger $logger)
    {
        $data = $request->validate([
            'submission_key' => ['required', 'uuid'],
            'product_id' => ['required', 'exists:products,id'],
            'type' => ['required', 'in:opening,receipt,sale,increase,decrease'],
            'occurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'quantity' => ['required', 'numeric', $request->input('type') === 'opening' ? 'min:0' : 'gt:0', 'max:999999999999', 'decimal:0,2'],
            'sales_value' => [$request->input('type') === 'sale' ? 'required' : 'nullable', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $service->record($data, $request->user());
        $logger->log($request->user(), 'transaction.recorded', 'Mencatat transaksi barang.', ['submission_key' => $data['submission_key']]);

        return redirect()->route('transactions.index')->with('success', 'Transaksi tersimpan. Stok dan total penjualan diperbarui otomatis.');
    }

    public function calculate(Request $request, TransactionService $service, ActivityLogger $logger)
    {
        $data = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:today'],
        ]);
        $run = $service->calculate($data['start_date'], $data['end_date'], $request->user());
        $logger->log($request->user(), 'moora.executed', 'Menghitung MOORA dari transaksi.', ['run_id' => $run->id]);

        return redirect()->route('calculations.results', $run)->with('success', 'MOORA dihitung otomatis dari transaksi pada rentang yang dipilih.');
    }
}
