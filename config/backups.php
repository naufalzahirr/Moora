<?php

return [
    'daily_at' => env('BACKUP_DAILY_AT', '02:00'),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
];
