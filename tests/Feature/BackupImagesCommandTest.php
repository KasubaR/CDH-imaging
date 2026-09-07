<?php

namespace Tests\Feature;

use App\Services\Backup\BackupEncryption;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BackupImagesCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cdh.backup.encryption_key' => 'test-backup-key-for-cdh']);

        Storage::disk('backups')->deleteDirectory('images');
        Storage::disk('local')->deleteDirectory('xrays');
    }

    protected function tearDown(): void
    {
        Storage::disk('backups')->deleteDirectory('images');
        Storage::disk('local')->deleteDirectory('xrays');

        parent::tearDown();
    }

    public function test_backup_images_creates_encrypted_archive_and_leaves_no_plaintext(): void
    {
        Storage::disk('local')->put('xrays/originals/sample.jpg', 'fake-original-bytes');
        Storage::disk('local')->put('xrays/thumbnails/sample.jpg', 'fake-thumb-bytes');
        Storage::disk('local')->put('xrays/chunks/session/0', 'should-not-be-backed-up');

        $exitCode = Artisan::call('backup:images');

        $this->assertSame(0, $exitCode);

        $files = Storage::disk('backups')->files('images');
        $this->assertCount(1, $files);
        $this->assertTrue(str_ends_with($files[0], '.zip.enc'));

        $encAbsolute = Storage::disk('backups')->path($files[0]);
        $this->assertFileExists($encAbsolute);

        $tmpDir = storage_path('app/private/backups-tmp');
        $this->assertDirectoryDoesNotContainMatchingFiles($tmpDir, '/xrays-.*\.zip$/');

        $decrypted = $tmpDir.'/test-images-restore.zip';
        app(BackupEncryption::class)->decryptFile($encAbsolute, $decrypted);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($decrypted) === true);
        $this->assertNotFalse($zip->locateName('xrays/originals/sample.jpg'));
        $this->assertNotFalse($zip->locateName('xrays/thumbnails/sample.jpg'));
        $this->assertFalse($zip->locateName('xrays/chunks/session/0'));
        $zip->close();

        @unlink($decrypted);
    }

    public function test_backup_images_fails_without_encryption_key(): void
    {
        config(['cdh.backup.encryption_key' => null]);

        $exitCode = Artisan::call('backup:images');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('BACKUP_ENCRYPTION_KEY', Artisan::output());
    }

    private function assertDirectoryDoesNotContainMatchingFiles(string $directory, string $pattern): void
    {
        if (! is_dir($directory)) {
            $this->assertTrue(true);

            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            $this->assertDoesNotMatchRegularExpression($pattern, $entry);
        }
    }
}
