<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupEncryption;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;
use ZipArchive;

#[Signature('backup:images')]
#[Description('Create an encrypted archive of X-ray originals and thumbnails (excludes chunk staging)')]
class BackupImagesCommand extends Command
{
    /**
     * @var list<string>
     */
    private const INCLUDE_DIRECTORIES = [
        'xrays/originals',
        'xrays/thumbnails',
    ];

    public function handle(BackupEncryption $encryption): int
    {
        try {
            $encryption->requireKey();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $stamp = now()->format('Y-m-d-His');
        $relativeDir = (string) config('cdh.backup.images_path');
        $relativeEnc = $relativeDir.'/xrays-'.$stamp.'.zip.enc';
        $tempZip = storage_path('app/private/backups-tmp/xrays-'.$stamp.'.zip');
        $tempEnc = storage_path('app/private/backups-tmp/xrays-'.$stamp.'.zip.enc');

        if (! is_dir(dirname($tempZip)) && ! mkdir(dirname($tempZip), 0755, true) && ! is_dir(dirname($tempZip))) {
            $this->error('Unable to create temporary backup directory.');

            return self::FAILURE;
        }

        try {
            $this->buildArchive($tempZip);
            $encryption->encryptFile($tempZip, $tempEnc);

            Storage::disk('backups')->makeDirectory($relativeDir);
            Storage::disk('backups')->put($relativeEnc, file_get_contents($tempEnc) ?: '');

            $this->info("Encrypted image backup stored at backups:{$relativeEnc}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            foreach ([$tempZip, $tempEnc] as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    private function buildArchive(string $zipPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Unable to create archive at {$zipPath}.");
        }

        $localRoot = Storage::disk('local')->path('');
        $added = 0;

        foreach (self::INCLUDE_DIRECTORIES as $relativeDirectory) {
            $absoluteDirectory = Storage::disk('local')->path($relativeDirectory);

            if (! is_dir($absoluteDirectory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absoluteDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $absolutePath = $file->getPathname();
                $archiveName = ltrim(str_replace('\\', '/', substr($absolutePath, strlen(rtrim($localRoot, DIRECTORY_SEPARATOR)))), '/');

                if ($archiveName === '') {
                    continue;
                }

                if (! $zip->addFile($absolutePath, $archiveName)) {
                    $zip->close();

                    throw new RuntimeException("Unable to add {$archiveName} to the image backup archive.");
                }

                $added++;
            }
        }

        // Ensure an empty-but-valid archive when no X-rays exist yet.
        if ($added === 0) {
            $zip->addFromString('xrays/.keep', '');
        }

        if (! $zip->close()) {
            throw new RuntimeException('Unable to finalize the image backup archive.');
        }
    }
}
