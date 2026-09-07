<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Services\ImageDeletionService;
use App\Services\SettingsService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('images:purge-expired')]
#[Description('Permanently delete images older than the configured retention period')]
class PurgeExpiredImagesCommand extends Command
{
    public function handle(ImageDeletionService $imageDeletion, SettingsService $settings): int
    {
        $months = $settings->get('image_retention_months');
        $cutoff = now()->subMonths($months);
        $purged = 0;

        Image::query()
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($images) use ($imageDeletion, &$purged): void {
                foreach ($images as $image) {
                    $imageDeletion->delete($image);
                    $purged++;
                }
            });

        $this->info("Purged {$purged} image(s) older than {$months} month(s).");

        return self::SUCCESS;
    }
}
