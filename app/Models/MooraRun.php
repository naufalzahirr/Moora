<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MooraRun extends Model
{
    protected $fillable = [
        'period_id', 'executed_by', 'status', 'total_alternatives', 'matched_alternatives',
        'accuracy', 'criteria_snapshot', 'normalization_divisors', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'accuracy' => 'decimal:2',
            'criteria_snapshot' => 'array',
            'normalization_divisors' => 'array',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(MooraResult::class);
    }

    public function report(): HasOne
    {
        return $this->hasOne(Report::class);
    }

    public function hasManualComparison(): bool
    {
        return $this->results->every(fn (MooraResult $result): bool => $result->yi_manual !== null);
    }
}
