<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = ['period_id', 'product_id', 'ending_stock', 'source', 'recorded_by'];

    protected function casts(): array
    {
        return ['ending_stock' => 'decimal:2'];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
