<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

class DatabaseBackupService
{
    public function backup(int $retentionDays = 14): string
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['sqlite', 'mysql'], true)) {
            throw new RuntimeException('Backup bawaan mendukung SQLite dan MySQL.');
        }

        $directory = config('backups.directory');
        File::ensureDirectoryExists($directory, 0700);
        $extension = $driver === 'sqlite' ? 'sqlite' : 'sql';
        $path = $directory.'/h2-asia-'.now()->format('Ymd-His-u').'.'.$extension;

        try {
            if ($driver === 'sqlite') {
                $this->backupSqlite($path);
            } else {
                $this->backupMysql($path);
            }
            chmod($path, 0600);
        } catch (Throwable $exception) {
            File::delete($path);
            throw $exception;
        }

        $threshold = now()->subDays(max(1, $retentionDays))->getTimestamp();
        foreach (File::files($directory) as $file) {
            // Cadangan sebelum migrasi dan berkas manual harus tetap tersimpan.
            if (preg_match('/^h2-asia-\d{8}-\d{6}(?:-\d{6})?\.(?:sqlite|sql)$/', $file->getFilename())
                && $file->getMTime() < $threshold) {
                File::delete($file->getPathname());
            }
        }

        return $path;
    }

    private function backupSqlite(string $path): void
    {
        $databasePath = DB::connection()->getDatabaseName();
        if (! is_string($databasePath) || $databasePath === ':memory:' || ! File::exists($databasePath)) {
            throw new RuntimeException('Berkas database SQLite tidak ditemukan untuk dicadangkan.');
        }

        $escapedPath = str_replace("'", "''", $path);
        DB::statement("VACUUM INTO '{$escapedPath}'");
    }

    private function backupMysql(string $path): void
    {
        $connection = DB::connection()->getConfig();
        $container = config('backups.mysql.container');
        $command = $container
            ? [config('backups.mysql.docker_binary'), 'exec', '--env', 'MYSQL_PWD', $container, 'mysqldump']
            : [config('backups.mysql.binary')];

        array_push($command,
            '--single-transaction', '--quick', '--skip-lock-tables', '--no-tablespaces',
            '--set-gtid-purged=OFF', '--hex-blob', '--default-character-set=utf8mb4',
            '--user='.$connection['username'],
        );
        if (! $container && ! empty($connection['unix_socket'])) {
            $command[] = '--socket='.$connection['unix_socket'];
        } else {
            array_push($command,
                '--protocol=TCP',
                '--host='.($container ? '127.0.0.1' : $connection['host']),
                '--port='.($container ? '3306' : $connection['port']),
            );
        }
        $command[] = $connection['database'];

        $stream = fopen($path, 'x');
        if ($stream === false) {
            throw new RuntimeException('Berkas backup tidak dapat dibuat.');
        }
        chmod($path, 0600);
        try {
            $result = Process::env(['MYSQL_PWD' => (string) $connection['password']])
                ->timeout(300)
                ->start($command, function (string $type, string $output) use ($stream): void {
                    if ($type === 'out' && fwrite($stream, $output) !== strlen($output)) {
                        throw new RuntimeException('Penulisan backup tidak lengkap. Periksa ruang penyimpanan.');
                    }
                })->wait();

            if (! $result->successful() || fstat($stream)['size'] === 0) {
                throw new RuntimeException('mysqldump gagal. Periksa layanan MySQL dan konfigurasi backup.');
            }
        } finally {
            fclose($stream);
        }
    }
}
