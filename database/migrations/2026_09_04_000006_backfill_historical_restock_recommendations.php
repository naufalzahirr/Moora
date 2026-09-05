<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $runs = DB::table('moora_runs')->pluck('criteria_snapshot', 'id');

        DB::table('moora_results')
            ->join('products', 'products.id', '=', 'moora_results.product_id')
            ->whereNull('moora_results.restock_quantity')
            ->select([
                'moora_results.id',
                'moora_results.moora_run_id',
                'moora_results.raw_values',
                'products.minimum_stock',
                'products.target_stock',
            ])
            ->orderBy('moora_results.id')
            ->each(function (object $result) use ($runs): void {
                $criteria = json_decode((string) ($runs[$result->moora_run_id] ?? '[]'), true) ?: [];
                $rawValues = json_decode((string) $result->raw_values, true) ?: [];
                $soldCode = collect($criteria)->firstWhere('source', 'sold_quantity')['code'] ?? 'C2';
                $stockCode = collect($criteria)->firstWhere('source', 'ending_stock')['code'] ?? 'C1';
                $soldQuantity = (float) ($rawValues[$soldCode] ?? 0);
                $endingStock = (float) ($rawValues[$stockCode] ?? 0);
                $target = $result->target_stock !== null
                    ? (float) $result->target_stock
                    : max((float) $result->minimum_stock, $soldQuantity);

                DB::table('moora_results')
                    ->where('id', $result->id)
                    ->update([
                        'restock_target' => $target,
                        'restock_quantity' => max(0, $target - $endingStock),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // Historical recommendations are a safe, one-way enrichment of snapshots.
    }
};
