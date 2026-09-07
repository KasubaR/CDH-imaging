<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackupDatabaseCommandTest extends TestCase
{
    public function test_backup_database_fails_without_encryption_key(): void
    {
        config(['cdh.backup.encryption_key' => '']);

        $exitCode = Artisan::call('backup:database');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('BACKUP_ENCRYPTION_KEY', Artisan::output());
    }

    public function test_backup_database_refuses_non_mysql_default_connection(): void
    {
        config([
            'cdh.backup.encryption_key' => 'test-backup-key-for-cdh',
            'database.default' => 'sqlite',
        ]);

        $exitCode = Artisan::call('backup:database');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('mysql driver', Artisan::output());
    }
}
