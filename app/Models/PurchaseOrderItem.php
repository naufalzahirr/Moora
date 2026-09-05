<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id', 'product_id', 'moora_result_id', 'suggested_quantity', 'ordered_quantity',
        'received_quantity', 'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'suggested_quantity' => 'decimal:2',
            'ordered_quantity' => 'decimal:2',
            'received_quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(MooraResult::class, 'moora_result_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function remainingQuantity(): float
    {
        return max(0, (float) $this->ordered_quantity - (float) $this->received_quantity);
    }
}
