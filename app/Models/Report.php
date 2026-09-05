<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = [
        'moora_run_id', 'document_name', 'storage_path', 'sha256', 'file_size', 'generated_by',
        'generated_at', 'archived_at',
    ];

    protected function casts(): array
    {
        return ['generated_at' => 'datetime', 'archived_at' => 'datetime'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(MooraRun::class, 'moora_run_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
