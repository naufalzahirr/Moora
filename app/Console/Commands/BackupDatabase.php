<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'app:backup-database {--prune=14 : Lama penyimpanan backup dalam hari}';

    protected $description = 'Membuat backup SQLite/MySQL aplikasi dan membersihkan backup rutin lama';

    public function handle(DatabaseBackupService $backups): int
    {
        try {
            $path = $backups->backup((int) $this->option('prune'));
            $this->info("Backup berhasil dibuat: {$path}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            report($exception);

            return self::FAILURE;
        }
    }
}
