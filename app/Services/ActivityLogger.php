<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Contracts\Auth\Authenticatable;

class ActivityLogger
{
    public function log(?Authenticatable $user, string $action, string $description, array $metadata = []): void
    {
        ActivityLog::create([
            'user_id' => $user?->getAuthIdentifier(),
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata ?: null,
        ]);
    }
}
