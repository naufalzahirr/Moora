<?php

namespace App\Services;

use App\Models\MooraRun;
use App\Models\Period;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    public function record(array $data, User $user): void
    {
        DB::transaction(function () use ($data, $user): void {
            $product = Product::lockForUpdate()->findOrFail($data['product_id']);
            if (StockTransaction::where('submission_key', $data['submission_key'])->exists()) {
                return;
            }
            if (! $product->active) {
                throw ValidationException::withMessages(['product_id' => 'Barang ini tidak aktif.']);
            }
            if ($product->usesWholeUnits() && floor((float) $data['quantity']) !== (float) $data['quantity']) {
                throw ValidationException::withMessages(['quantity' => 'Jumlah untuk satuan '.$product->unit.' harus bilangan bulat.']);
            }
            $history = StockTransaction::where('product_id', $product->id)->orderBy('occurred_on')->orderBy('id')->get();
            if ($data['type'] === 'opening' && $history->isNotEmpty()) {
                throw ValidationException::withMessages(['type' => 'Stok awal sudah tercatat. Gunakan transaksi masuk atau koreksi stok.']);
            }
            if ($data['type'] !== 'opening' && $history->isEmpty()) {
                throw ValidationException::withMessages(['type' => 'Catat stok awal barang ini terlebih dahulu, boleh 0.']);
            }
            if ($history->isNotEmpty() && $data['occurred_on'] < $history->last()->occurred_on->toDateString()) {
                throw ValidationException::withMessages(['occurred_on' => 'Tanggal tidak boleh mendahului transaksi terakhir barang ini: '.$history->last()->occurred_on->format('d-m-Y').'.']);
            }
            $balance = $history->sum(fn ($item) => in_array($item->type, ['sale', 'decrease']) ? -(float) $item->quantity : (float) $item->quantity);
            if (in_array($data['type'], ['sale', 'decrease']) && (float) $data['quantity'] > $balance) {
                throw ValidationException::withMessages(['quantity' => 'Jumlah melebihi stok tersedia: '.$product->formatQuantity($balance, true).'.']);
            }
            StockTransaction::create([
                ...$data,
                'sales_value' => $data['type'] === 'sale' ? $data['sales_value'] : 0,
                'recorded_by' => $user->id,
            ]);
        });
    }

    public function calculate(string $start, string $end, User $user): MooraRun
    {
        return DB::transaction(function () use ($start, $end, $user) {
            $products = Product::where('active', true)->orderBy('id')->lockForUpdate()->get();
            if ($products->isEmpty()) {
                throw ValidationException::withMessages(['transactions' => 'Tambahkan barang aktif terlebih dahulu.']);
            }
            $cutoff = StockTransaction::max('id') ?? 0;
            $entries = StockTransaction::whereIn('product_id', $products->pluck('id'))->where('id', '<=', $cutoff)->whereDate('occurred_on', '<=', $end)->get()->groupBy('product_id');
            $period = Period::create([
                'name' => 'Analisis transaksi '.$start.' sampai '.$end.' '.Str::uuid(),
                'start_date' => $start, 'end_date' => $end, 'status' => 'ready', 'created_by' => $user->id,
                'source_type' => 'transactions', 'transaction_cutoff' => $cutoff,
            ]);
            foreach ($products as $product) {
                $history = $entries->get($product->id, collect());
                $opening = $history->firstWhere('type', 'opening');
                if (! $opening || $opening->occurred_on->toDateString() > $start) {
                    throw ValidationException::withMessages(['transactions' => 'Stok awal '.$product->name.' harus tersedia pada atau sebelum tanggal awal analisis. Pilih rentang setelah mulai pencatatan.']);
                }
                $sales = $history->filter(fn ($item) => $item->type === 'sale' && $item->occurred_on->toDateString() >= $start);
                Sale::create(['period_id' => $period->id, 'product_id' => $product->id, 'sold_quantity' => $sales->sum('quantity'), 'sales_value' => $sales->sum('sales_value'), 'source_reference' => 'Rekap otomatis transaksi']);
                StockMovement::create(['period_id' => $period->id, 'product_id' => $product->id,
                    'ending_stock' => $history->sum(fn ($item) => in_array($item->type, ['sale', 'decrease']) ? -(float) $item->quantity : (float) $item->quantity),
                    'source' => 'Saldo transaksi pada akhir rentang', 'recorded_by' => $user->id]);
            }

            return app(MooraService::class)->execute($period, $user);
        });
    }
}
