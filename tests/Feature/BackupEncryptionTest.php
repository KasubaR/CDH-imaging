<?php

namespace Tests\Feature;

use App\Services\Backup\BackupEncryption;
use RuntimeException;
use Tests\TestCase;

class BackupEncryptionTest extends TestCase
{
    public function test_encrypt_and_decrypt_round_trip(): void
    {
        config(['cdh.backup.encryption_key' => 'test-backup-key-for-cdh']);

        $dir = storage_path('app/private/backups-tmp');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $plain = $dir.'/roundtrip-plain.txt';
        $enc = $dir.'/roundtrip.enc';
        $restored = $dir.'/roundtrip-restored.txt';
        $payload = 'xray-backup-payload-'.uniqid('', true);

        file_put_contents($plain, $payload);

        $encryption = new BackupEncryption;
        $encryption->encryptFile($plain, $enc);
        $encryption->decryptFile($enc, $restored);

        $this->assertFileExists($enc);
        $this->assertNotSame($payload, file_get_contents($enc));
        $this->assertSame($payload, file_get_contents($restored));

        @unlink($plain);
        @unlink($enc);
        @unlink($restored);
    }

    public function test_require_key_fails_when_empty(): void
    {
        config(['cdh.backup.encryption_key' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('BACKUP_ENCRYPTION_KEY is not configured.');

        (new BackupEncryption)->requireKey();
    }
}
