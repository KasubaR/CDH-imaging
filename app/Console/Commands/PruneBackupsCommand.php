<?php

namespace App\Console\Commands;

use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('backup:prune')]
#[Description('Delete encrypted backup files older than the configured retention period')]
class PruneBackupsCommand extends Command
{
    public function handle(SettingsService $settings): int
    {
        $retentionDays = $settings->get('backup_retention_days');
        $cutoff = now()->subDays($retentionDays);
        $disk = Storage::disk('backups');
        $removed = 0;

        foreach ([
            (string) config('cdh.backup.database_path'),
            (string) config('cdh.backup.images_path'),
        ] as $directory) {
            if (! $disk->exists($directory)) {
                continue;
            }

            foreach ($disk->files($directory) as $path) {
                if (! str_ends_with($path, '.enc')) {
                    continue;
                }

                $lastModified = $disk->lastModified($path);

                if ($lastModified === false) {
                    continue;
                }

                if (Carbon::createFromTimestamp($lastModified)->lessThan($cutoff)) {
                    $disk->delete($path);
                    $removed++;
                }
            }
        }

        $this->info("Pruned {$removed} encrypted backup file(s) older than {$retentionDays} day(s).");

        return self::SUCCESS;
    }
}
