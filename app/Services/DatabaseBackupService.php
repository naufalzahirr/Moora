<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class DatabaseBackupService
{
    public function backup(int $retentionDays = 14): string
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new RuntimeException('Backup otomatis bawaan saat ini mendukung SQLite. Gunakan backup terkelola dari server database untuk driver lain.');
        }

        $databasePath = DB::connection()->getDatabaseName();
        if (! is_string($databasePath) || $databasePath === ':memory:' || ! File::exists($databasePath)) {
            throw new RuntimeException('Berkas database SQLite tidak ditemukan untuk dicadangkan.');
        }

        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/h2-asia-'.now()->format('Ymd-His').'.sqlite';
        $escapedPath = str_replace("'", "''", $path);
        DB::statement("VACUUM INTO '{$escapedPath}'");

        $threshold = now()->subDays(max(1, $retentionDays))->getTimestamp();
        foreach (File::files($directory) as $file) {
            if ($file->getMTime() < $threshold) {
                File::delete($file->getPathname());
            }
        }

        return $path;
    }
}
