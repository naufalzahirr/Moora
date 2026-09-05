<?php

namespace App\Services;

use App\Models\Criterion;
use App\Models\MooraResult;
use App\Models\MooraRun;
use App\Models\Period;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MooraService
{
    public const MATCH_TOLERANCE = 0.0001;

    public const RANK_TIE_TOLERANCE = 0.0000000001;

    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * @return Collection<int, array{product: mixed, raw: array<string, float>}>
     */
    public function dataset(Period $period, Collection $criteria): Collection
    {
        $sales = Sale::with('product.supplier')
            ->where('period_id', $period->id)
            ->orderBy('product_id')
            ->get()
            ->keyBy('product_id');

        $stocks = StockMovement::where('period_id', $period->id)
            ->get()
            ->keyBy('product_id');

        $missingStock = $sales->keys()->reject(fn (int $productId): bool => $stocks->get($productId)?->ending_stock !== null);
        if ($missingStock->isNotEmpty()) {
            $names = $missingStock->map(fn (int $id): string => $sales[$id]->product->name)->join(', ');
            throw ValidationException::withMessages([
                'dataset' => "Stok akhir belum lengkap untuk: {$names}.",
            ]);
        }

        $incompleteSales = $sales->filter(fn (Sale $sale): bool => $sale->sold_quantity === null || $sale->sales_value === null);
        if ($incompleteSales->isNotEmpty()) {
            throw ValidationException::withMessages([
                'dataset' => 'Data penjualan belum lengkap untuk: '.$incompleteSales->pluck('product.name')->join(', ').'.',
            ]);
        }

        return $sales->values()->map(function (Sale $sale, int $index) use ($stocks, $criteria): array {
            $stock = $stocks->get($sale->product_id);
            $sourceValues = [
                'ending_stock' => (float) $stock->ending_stock,
                'sold_quantity' => (float) $sale->sold_quantity,
                'sales_value' => (float) $sale->sales_value,
            ];

            return [
                'product' => $sale->product,
                'alternative_code' => 'A'.($index + 1),
                'ending_stock' => $sourceValues['ending_stock'],
                'sold_quantity' => $sourceValues['sold_quantity'],
                'raw' => $criteria->mapWithKeys(
                    fn (Criterion $criterion): array => [$criterion->code => $sourceValues[$criterion->value_source]]
                )->all(),
            ];
        })->values();
    }

    /**
     * @return array{rows: Collection, criteria: Collection, divisors: array<string, float>}
     */
    public function calculate(Period $period): array
    {
        $criteria = Criterion::active()->orderBy('code')->get();
        $this->validateCriteria($criteria);

        $dataset = $this->dataset($period, $criteria);
        if ($dataset->isEmpty()) {
            throw ValidationException::withMessages([
                'dataset' => 'Belum ada data penjualan pada periode yang dipilih.',
            ]);
        }

        $divisors = $criteria->mapWithKeys(function (Criterion $criterion) use ($dataset): array {
            $sumOfSquares = $dataset->sum(
                fn (array $row): float => ((float) $row['raw'][$criterion->code]) ** 2
            );
            $divisor = sqrt($sumOfSquares);

            if ($divisor <= 0) {
                throw ValidationException::withMessages([
                    'dataset' => "Seluruh nilai {$criterion->name} bernilai nol; normalisasi tidak dapat dilakukan.",
                ]);
            }

            return [$criterion->code => $divisor];
        })->all();

        $rows = $dataset->map(function (array $row) use ($criteria, $divisors, $period): array {
            $normalized = [];
            $weighted = [];
            $benefit = 0.0;
            $cost = 0.0;

            foreach ($criteria as $criterion) {
                $code = $criterion->code;
                $normalized[$code] = (float) $row['raw'][$code] / $divisors[$code];
                $weighted[$code] = $normalized[$code] * (float) $criterion->weight;

                if ($criterion->type === 'benefit') {
                    $benefit += $weighted[$code];
                } else {
                    $cost += $weighted[$code];
                }
            }

            $daysInPeriod = max(1, $period->start_date->diffInDays($period->end_date) + 1);
            $dailyDemand = (float) $row['sold_quantity'] / $daysInPeriod;
            $leadTimeDays = (int) ($row['product']->supplier?->lead_time_days ?? 0);
            $reviewPeriodDays = (int) $row['product']->review_period_days;
            $demandDuringCoverage = $dailyDemand * ($leadTimeDays + $reviewPeriodDays);
            $forecastTarget = $demandDuringCoverage + (float) $row['product']->safety_stock;
            $restockTarget = max(
                (float) $row['product']->minimum_stock,
                (float) ($row['product']->target_stock ?? 0),
                $forecastTarget,
            );
            $onHand = (float) $row['ending_stock'];
            $incoming = $this->inventory->incoming($row['product']);
            $shortage = max(0, $restockTarget - ($onHand + $incoming));
            $minimumOrder = (float) $row['product']->minimum_order_quantity;
            $orderMultiple = max(0.01, (float) $row['product']->order_multiple);
            $restockQuantity = $shortage > 0
                ? max($minimumOrder, ceil($shortage / $orderMultiple) * $orderMultiple)
                : 0.0;

            return [
                ...$row,
                'normalized' => $normalized,
                'weighted' => $weighted,
                'yi' => $benefit - $cost,
                'restock_target' => $restockTarget,
                'restock_quantity' => $restockQuantity,
                'restock_basis' => [
                    'period_days' => $daysInPeriod,
                    'average_daily_demand' => $dailyDemand,
                    'lead_time_days' => $leadTimeDays,
                    'review_period_days' => $reviewPeriodDays,
                    'safety_stock' => (float) $row['product']->safety_stock,
                    'minimum_stock' => (float) $row['product']->minimum_stock,
                    'configured_target_stock' => $row['product']->target_stock !== null ? (float) $row['product']->target_stock : null,
                    'on_hand' => $onHand,
                    'incoming' => $incoming,
                    'projected_available' => $onHand + $incoming,
                    'minimum_order_quantity' => $minimumOrder,
                    'order_multiple' => $orderMultiple,
                ],
            ];
        })->sort(fn (array $left, array $right): int => $this->compareRanks(
            $left['yi'], $right['yi'], $left['product']->code, $right['product']->code
        ))->values()
            ->map(fn (array $row, int $index): array => [...$row, 'rank' => $index + 1]);

        return compact('rows', 'criteria', 'divisors');
    }

    /**
     * @param  array<int|string, float|string|null>  $manualValues  keyed by product id
     */
    public function execute(Period $period, User $user, array $manualValues = []): MooraRun
    {
        $calculation = $this->calculate($period);
        $rows = $calculation['rows'];
        $manual = collect($manualValues)
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->map(fn ($value): float => (float) str_replace(',', '.', (string) $value));

        $manualRanks = $rows
            ->filter(fn (array $row): bool => $manual->has((int) $row['product']->id))
            ->sort(fn (array $left, array $right): int => $this->compareRanks(
                (float) $manual->get((int) $left['product']->id),
                (float) $manual->get((int) $right['product']->id),
                $left['product']->code,
                $right['product']->code,
            ))
            ->values()
            ->mapWithKeys(fn (array $row, int $index): array => [(int) $row['product']->id => $index + 1]);

        return DB::transaction(function () use ($period, $user, $calculation, $rows, $manual, $manualRanks): MooraRun {
            $matched = 0;
            $manualComplete = $manual->count() === $rows->count();

            $run = MooraRun::create([
                'period_id' => $period->id,
                'executed_by' => $user->id,
                'status' => $manualComplete ? 'completed' : 'incomplete',
                'total_alternatives' => $rows->count(),
                'matched_alternatives' => 0,
                'accuracy' => 0,
                'criteria_snapshot' => $calculation['criteria']->map(fn (Criterion $criterion): array => [
                    'code' => $criterion->code,
                    'name' => $criterion->name,
                    'type' => $criterion->type,
                    'weight' => (float) $criterion->weight,
                    'source' => $criterion->value_source,
                ])->values()->all(),
                'normalization_divisors' => $calculation['divisors'],
                'notes' => $manualComplete
                    ? 'Nilai pembanding dan sistem dibandingkan dengan toleransi '.self::MATCH_TOLERANCE.'. Jika Yi seri, urutan kode barang digunakan sebagai tie-breaker. Jumlah restock memakai rata-rata permintaan harian, lead time supplier, masa tinjau, safety stock, stok tersedia, pesanan masuk, MOQ, dan kelipatan pesanan.'
                    : 'Perhitungan sistem selesai; nilai pembanding belum lengkap. Jika Yi seri, urutan kode barang digunakan sebagai tie-breaker. Jumlah restock memakai rata-rata permintaan harian, lead time supplier, masa tinjau, safety stock, stok tersedia, pesanan masuk, MOQ, dan kelipatan pesanan.',
            ]);

            foreach ($rows as $row) {
                $productId = (int) $row['product']->id;
                $manualYi = $manual->get($productId);
                $difference = $manualYi === null ? null : abs((float) $row['yi'] - $manualYi);
                $rankManual = $manualRanks->get($productId);
                $matches = $manualYi === null
                    ? null
                    : $difference <= self::MATCH_TOLERANCE && $rankManual === $row['rank'];

                if ($matches) {
                    $matched++;
                }

                MooraResult::create([
                    'moora_run_id' => $run->id,
                    'product_id' => $productId,
                    'product_code_snapshot' => $row['product']->code,
                    'product_name_snapshot' => $row['product']->name,
                    'alternative_code' => $row['alternative_code'],
                    'raw_values' => $row['raw'],
                    'normalized_values' => $row['normalized'],
                    'weighted_values' => $row['weighted'],
                    'yi_system' => $row['yi'],
                    'yi_manual' => $manualYi,
                    'difference' => $difference,
                    'rank_system' => $row['rank'],
                    'rank_manual' => $rankManual,
                    'restock_target' => $row['restock_target'],
                    'restock_quantity' => $row['restock_quantity'],
                    'restock_basis' => $row['restock_basis'],
                    'matches' => $matches,
                ]);
            }

            $run->update([
                'matched_alternatives' => $matched,
                'accuracy' => $manualComplete && $rows->isNotEmpty()
                    ? round(($matched / $rows->count()) * 100, 2)
                    : 0,
            ]);

            $this->inventory->establishPeriodBalance($period, $user);
            $period->update(['status' => 'completed', 'calculated_at' => now()]);

            return $run->load(['period', 'results.product']);
        });
    }

    private function validateCriteria(Collection $criteria): void
    {
        if ($criteria->isEmpty()) {
            throw ValidationException::withMessages(['criteria' => 'Belum ada kriteria aktif.']);
        }

        $weight = (float) $criteria->sum(fn (Criterion $criterion): float => (float) $criterion->weight);
        if (abs($weight - 1.0) > 0.000001) {
            throw ValidationException::withMessages([
                'criteria' => 'Total bobot kriteria aktif harus tepat 100%.',
            ]);
        }

        if (! $criteria->contains('type', 'benefit') || ! $criteria->contains('type', 'cost')) {
            throw ValidationException::withMessages([
                'criteria' => 'Konfigurasi MOORA harus memiliki kriteria benefit dan cost.',
            ]);
        }
    }

    private function compareRanks(float $leftScore, float $rightScore, string $leftCode, string $rightCode): int
    {
        if (abs($leftScore - $rightScore) > self::RANK_TIE_TOLERANCE) {
            return $rightScore <=> $leftScore;
        }

        return strcasecmp($leftCode, $rightCode);
    }
}
