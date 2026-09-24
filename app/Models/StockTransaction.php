<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransaction extends Model
{
    protected $fillable = ['submission_key', 'product_id', 'type', 'occurred_on', 'quantity', 'sales_value', 'notes', 'recorded_by'];

    protected function casts(): array
    {
        return ['occurred_on' => 'date', 'quantity' => 'decimal:2', 'sales_value' => 'decimal:2'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function label(): string
    {
        return match ($this->type) {
            'opening' => 'Stok awal', 'receipt' => 'Barang masuk', 'sale' => 'Penjualan',
            'increase' => 'Koreksi stok bertambah', 'decrease' => 'Koreksi stok berkurang',
        };
    }
}
