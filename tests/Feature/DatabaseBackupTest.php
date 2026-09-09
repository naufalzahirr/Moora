<?php

namespace Tests\Feature;

use App\Services\DatabaseBackupService;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Mockery;
use PDO;
use RuntimeException;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/h2-backup-test-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->directory);
        config(['backups.directory' => $this->directory.'/backups']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_mysql_backup_streams_sql_without_putting_password_in_command(): void
    {
        $this->mockMysql();
        config(['backups.mysql.container' => 'test-mysql']);
        Process::fake(['*' => Process::result(output: '-- SQL backup'.PHP_EOL)]);

        $path = app(DatabaseBackupService::class)->backup();

        $this->assertSame('-- SQL backup'.PHP_EOL, file_get_contents($path));
        $this->assertSame(0600, fileperms($path) & 0777);
        Process::assertRan(function ($process): bool {
            return in_array('test-mysql', $process->command, true)
                && in_array('--single-transaction', $process->command, true)
                && $process->environment['MYSQL_PWD'] === 'test-secret'
                && ! str_contains(implode(' ', $process->command), 'test-secret');
        });
    }

    public function test_failed_mysql_backup_removes_partial_file_and_keeps_existing_backups(): void
    {
        $this->mockMysql();
        File::ensureDirectoryExists(config('backups.directory'));
        $previous = config('backups.directory').'/h2-asia-20200101-000000.sql';
        file_put_contents($previous, 'previous backup');
        touch($previous, 1);
        Process::fake(['*' => Process::result(output: 'partial', exitCode: 1)]);

        try {
            app(DatabaseBackupService::class)->backup();
            $this->fail('Backup gagal harus melempar exception.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('mysqldump gagal', $exception->getMessage());
        }

        $this->assertCount(1, File::files(config('backups.directory')));
        $this->assertSame('previous backup', file_get_contents($previous));
    }

    public function test_empty_mysql_dump_is_rejected(): void
    {
        $this->mockMysql();
        Process::fake(['*' => Process::result(output: '')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mysqldump gagal');
        app(DatabaseBackupService::class)->backup();
    }

    public function test_sqlite_backup_is_readable_and_retention_preserves_migration_backups(): void
    {
        $source = $this->directory.'/source.sqlite';
        $pdo = new PDO('sqlite:'.$source);
        $pdo->exec('CREATE TABLE records (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO records VALUES (1, 'data lama')");
        config([
            'database.default' => 'backup_test',
            'database.connections.backup_test' => ['driver' => 'sqlite', 'database' => $source],
        ]);
        File::ensureDirectoryExists(config('backups.directory'));
        $routine = config('backups.directory').'/h2-asia-20200101-000000.sqlite';
        $migration = config('backups.directory').'/pre-mysql-20200101-000000.sqlite';
        foreach ([$routine, $migration] as $path) {
            file_put_contents($path, 'old backup');
            touch($path, 1);
        }

        $path = app(DatabaseBackupService::class)->backup();

        $restored = new PDO('sqlite:'.$path);
        $this->assertSame('data lama', $restored->query('SELECT name FROM records')->fetchColumn());
        $this->assertFileDoesNotExist($routine);
        $this->assertFileExists($migration);
        DB::disconnect('backup_test');
    }

    private function mockMysql(): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('mysql');
        $connection->shouldReceive('getConfig')->andReturn([
            'host' => '127.0.0.1', 'port' => 3308, 'database' => 'backup_test',
            'username' => 'backup_user', 'password' => 'test-secret',
        ]);
        DB::shouldReceive('connection')->andReturn($connection);
    }
}
