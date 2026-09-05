<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'code', 'name', 'unit', 'minimum_stock', 'safety_stock', 'target_stock', 'review_period_days',
        'minimum_order_quantity', 'order_multiple', 'category_id', 'supplier_id', 'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'minimum_stock' => 'decimal:2',
            'safety_stock' => 'decimal:2',
            'target_stock' => 'decimal:2',
            'minimum_order_quantity' => 'decimal:2',
            'order_multiple' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function mooraResults(): HasMany
    {
        return $this->hasMany(MooraResult::class);
    }

    public function usesWholeUnits(): bool
    {
        return self::isWholeUnit($this->unit);
    }

    public static function isWholeUnit(?string $unit): bool
    {
        return in_array(mb_strtolower(trim((string) $unit)), ['pcs', 'pc', 'unit', 'pack', 'pak', 'box', 'dus', 'botol', 'kaleng'], true);
    }

    public function quantityStep(): string
    {
        return $this->usesWholeUnits() ? '1' : '0.01';
    }

    public function formatQuantity(int|float|string|null $quantity, bool $includeUnit = false): string
    {
        if ($quantity === null) {
            return '—';
        }

        $formatted = number_format((float) $quantity, $this->usesWholeUnits() ? 0 : 2, ',', '.');
        if (! $this->usesWholeUnits()) {
            $formatted = rtrim(rtrim($formatted, '0'), ',');
        }

        return $includeUnit ? "{$formatted} {$this->unit}" : $formatted;
    }
}
