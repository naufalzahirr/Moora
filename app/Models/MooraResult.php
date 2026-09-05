<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MooraResult extends Model
{
    protected $fillable = [
        'moora_run_id', 'product_id', 'product_code_snapshot', 'product_name_snapshot',
        'alternative_code', 'raw_values', 'normalized_values',
        'weighted_values', 'yi_system', 'yi_manual', 'difference', 'rank_system', 'rank_manual', 'restock_target',
        'restock_quantity', 'restock_basis', 'matches',
    ];

    protected function casts(): array
    {
        return [
            'raw_values' => 'array',
            'normalized_values' => 'array',
            'weighted_values' => 'array',
            'yi_system' => 'decimal:8',
            'yi_manual' => 'decimal:8',
            'difference' => 'decimal:8',
            'restock_target' => 'decimal:2',
            'restock_quantity' => 'decimal:2',
            'restock_basis' => 'array',
            'matches' => 'boolean',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(MooraRun::class, 'moora_run_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function restockAction(): HasOne
    {
        return $this->hasOne(RestockAction::class);
    }

    public function displayProductCode(): string
    {
        return $this->product_code_snapshot ?: ($this->product?->code ?? '—');
    }

    public function displayProductName(): string
    {
        return $this->product_name_snapshot ?: ($this->product?->name ?? 'Barang tidak tersedia');
    }

    /** @param array<int, array<string, mixed>>|null $criteria */
    public function onHandAtCalculation(?array $criteria = null): float
    {
        if (array_key_exists('on_hand', $this->restock_basis ?? [])) {
            return (float) $this->restock_basis['on_hand'];
        }

        $criteria ??= $this->run?->criteria_snapshot ?? [];
        $stockCriterion = collect($criteria)->first(
            fn (array $criterion): bool => ($criterion['source'] ?? $criterion['value_source'] ?? null) === 'ending_stock'
        );

        return (float) ($this->raw_values[$stockCriterion['code'] ?? ''] ?? 0);
    }
}
