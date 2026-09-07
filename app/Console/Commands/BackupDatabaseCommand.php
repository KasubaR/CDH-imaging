<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupEncryption;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('backup:database')]
#[Description('Create an encrypted MySQL dump (does not include X-ray files)')]
class BackupDatabaseCommand extends Command
{
    public function handle(BackupEncryption $encryption): int
    {
        try {
            $encryption->requireKey();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (! is_array($config) || ($config['driver'] ?? null) !== 'mysql') {
            $this->error('backup:database requires the default database connection to use the mysql driver.');

            return self::FAILURE;
        }

        $stamp = now()->format('Y-m-d-His');
        $relativeDir = (string) config('cdh.backup.database_path');
        $relativeEnc = $relativeDir.'/cdh-'.$stamp.'.sql.enc';
        $tempSql = storage_path('app/private/backups-tmp/cdh-'.$stamp.'.sql');
        $tempEnc = storage_path('app/private/backups-tmp/cdh-'.$stamp.'.sql.enc');

        if (! is_dir(dirname($tempSql)) && ! mkdir(dirname($tempSql), 0755, true) && ! is_dir(dirname($tempSql))) {
            $this->error('Unable to create temporary backup directory.');

            return self::FAILURE;
        }

        try {
            $this->runMysqldump($config, $tempSql);
            $encryption->encryptFile($tempSql, $tempEnc);

            Storage::disk('backups')->makeDirectory($relativeDir);
            Storage::disk('backups')->put($relativeEnc, file_get_contents($tempEnc) ?: '');

            $this->info("Encrypted database backup stored at backups:{$relativeEnc}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            foreach ([$tempSql, $tempEnc] as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function runMysqldump(array $config, string $outputPath): void
    {
        $binary = $this->resolveDumpBinary();

        if ($binary === null) {
            throw new RuntimeException('Neither mysqldump nor mariadb-dump was found on PATH.');
        }

        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '3306');
        $database = (string) ($config['database'] ?? '');
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');

        if ($database === '') {
            throw new RuntimeException('Database name is not configured.');
        }

        $command = [
            $binary,
            '--host='.$host,
            '--port='.$port,
            '--user='.$username,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--result-file='.$outputPath,
            $database,
        ];

        $result = Process::env([
            'MYSQL_PWD' => $password,
        ])->timeout(600)->run($command);

        if ($result->failed() || ! is_file($outputPath)) {
            throw new RuntimeException(trim($result->errorOutput()) !== ''
                ? trim($result->errorOutput())
                : 'mysqldump failed without producing an output file.');
        }
    }

    private function resolveDumpBinary(): ?string
    {
        foreach (['mysqldump', 'mariadb-dump'] as $binary) {
            $result = Process::run([
                PHP_OS_FAMILY === 'Windows' ? 'where' : 'which',
                $binary,
            ]);

            if ($result->successful()) {
                return $binary;
            }
        }

        return null;
    }
}
