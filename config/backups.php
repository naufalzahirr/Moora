<?php

return [
    'directory' => storage_path('app/backups'),
    'daily_at' => env('BACKUP_DAILY_AT', '02:00'),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
    'mysql' => [
        'binary' => env('BACKUP_MYSQL_BINARY', 'mysqldump'),
        'container' => env('BACKUP_MYSQL_CONTAINER'),
        'docker_binary' => env('BACKUP_DOCKER_BINARY', 'docker'),
    ],
];
