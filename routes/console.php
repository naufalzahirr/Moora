<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('about:h2', function (): void {
    $this->info('SPK Restock H2 Asia Swalayan - MOORA');
})->purpose('Menampilkan identitas aplikasi');

Schedule::command('app:backup-database --prune='.config('backups.retention_days'))
    ->dailyAt(config('backups.daily_at'))
    ->withoutOverlapping();
