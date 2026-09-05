<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    protected $fillable = ['period_id', 'product_id', 'sold_quantity', 'sales_value', 'manual_yi', 'source_reference'];

    protected function casts(): array
    {
        return ['sold_quantity' => 'decimal:2', 'sales_value' => 'decimal:2', 'manual_yi' => 'decimal:8'];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
