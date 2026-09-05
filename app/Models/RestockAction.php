<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestockAction extends Model
{
    protected $fillable = [
        'moora_result_id', 'purchase_order_id', 'status', 'approved_quantity', 'notes', 'processed_by',
        'proposed_by', 'proposed_at', 'approved_by', 'approved_at', 'ordered_at', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_quantity' => 'decimal:2',
            'proposed_at' => 'datetime',
            'approved_at' => 'datetime',
            'ordered_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(MooraResult::class, 'moora_result_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function label(): string
    {
        return match ($this->status) {
            'proposed' => 'Menunggu persetujuan',
            'approved' => 'Disetujui',
            'ordered' => 'Dipesan',
            'received' => 'Diterima',
            'skipped' => 'Tidak dipesan',
            default => 'Belum ditinjau',
        };
    }

    public function tone(): string
    {
        return match ($this->status) {
            'received' => 'green',
            'proposed', 'approved', 'ordered' => 'blue',
            'skipped' => 'gray',
            default => 'gray',
        };
    }
}
