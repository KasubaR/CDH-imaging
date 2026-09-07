<?php

namespace App\Console\Commands;

use App\Enums\UploadSessionStatus;
use App\Models\UploadSession;
use App\Services\SettingsService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('images:purge-stale-uploads')]
#[Description('Delete abandoned chunked-upload sessions and their chunk files past the configured TTL')]
class PurgeStaleUploadSessionsCommand extends Command
{
    public function handle(SettingsService $settings): int
    {
        $hours = $settings->get('upload_session_ttl_hours');
        $cutoff = now()->subHours($hours);
        $purged = 0;

        UploadSession::query()
            ->whereIn('status', [
                UploadSessionStatus::Pending,
                UploadSessionStatus::Assembling,
                UploadSessionStatus::Failed,
            ])
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($sessions) use (&$purged): void {
                foreach ($sessions as $session) {
                    Storage::disk('local')->deleteDirectory('xrays/chunks/'.$session->uuid);
                    $session->delete();
                    $purged++;
                }
            });

        $this->info("Purged {$purged} stale upload session(s) older than {$hours} hour(s).");

        return self::SUCCESS;
    }
}
