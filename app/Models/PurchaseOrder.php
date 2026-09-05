<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'order_number', 'supplier_id', 'moora_run_id', 'status', 'expected_at', 'approved_at', 'ordered_at',
        'received_at', 'notes', 'created_by', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'expected_at' => 'date',
            'approved_at' => 'datetime',
            'ordered_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(MooraRun::class, 'moora_run_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function label(): string
    {
        return match ($this->status) {
            'approved' => 'Disetujui',
            'ordered' => 'Dipesan',
            'partial' => 'Diterima sebagian',
            'received' => 'Selesai diterima',
            'cancelled' => 'Dibatalkan',
            default => 'Draft',
        };
    }

    public function tone(): string
    {
        return match ($this->status) {
            'received' => 'green',
            'approved', 'ordered', 'partial' => 'blue',
            'cancelled' => 'red',
            default => 'gray',
        };
    }
}
