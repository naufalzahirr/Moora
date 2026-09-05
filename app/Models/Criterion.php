<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Criterion extends Model
{
    protected $table = 'criteria';

    protected $fillable = ['code', 'name', 'type', 'weight', 'value_source', 'source_description', 'active'];

    protected function casts(): array
    {
        return ['weight' => 'decimal:6', 'active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function typeLabel(): string
    {
        return $this->type === 'benefit' ? 'Benefit' : 'Cost';
    }

    public function valueFor(?Sale $sale, ?StockMovement $stock): ?float
    {
        $value = match ($this->value_source) {
            'ending_stock' => $stock?->ending_stock,
            'sold_quantity' => $sale?->sold_quantity,
            'sales_value' => $sale?->sales_value,
            default => null,
        };

        return $value === null ? null : (float) $value;
    }

    public function formatValue(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        $formatted = number_format($value, 0, ',', '.');

        return $this->value_source === 'sales_value' ? 'Rp'.$formatted : $formatted;
    }

    public function accessibleName(): string
    {
        return Str::ucfirst(Str::lower($this->name));
    }
}
