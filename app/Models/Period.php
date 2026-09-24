<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Period extends Model
{
    protected $fillable = [
        'name', 'start_date', 'end_date', 'source_file', 'source_path', 'source_sha256', 'source_size',
        'source_type', 'transaction_cutoff', 'source_mime', 'status', 'created_by', 'revision_of_id', 'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'calculated_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revisedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revision_of_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'revision_of_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(MooraRun::class);
    }

    public function selectionKey(): string
    {
        return $this->source_type.'/'.$this->start_date->format('Y-m-d').'/'.$this->end_date->format('Y-m-d');
    }

    public function monthlyLabel(): string
    {
        if ($this->source_type === 'transactions') {
            return $this->displayRange();
        }

        return $this->start_date->format('Y-m') === $this->end_date->format('Y-m')
            ? $this->start_date->translatedFormat('F Y')
            : 'Data lama · '.$this->displayRange();
    }

    public function displayRange(): string
    {
        return $this->start_date->translatedFormat('d M Y').' - '.$this->end_date->translatedFormat('d M Y');
    }

    public function displayName(): string
    {
        return preg_replace('/ \\(Revisi (\\d+)\\)$/u', ' (Pembaruan $1)', $this->name) ?? $this->name;
    }

    public function isLocked(): bool
    {
        return $this->status === 'completed';
    }

    /** @return array{days_old: int, is_stale: bool} */
    public function freshness(int $staleAfterDays = 7): array
    {
        $daysOld = max(0, $this->end_date->startOfDay()->diffInDays(now()->startOfDay(), false));

        return [
            'days_old' => $daysOld,
            'is_stale' => $daysOld > $staleAfterDays,
        ];
    }
}
