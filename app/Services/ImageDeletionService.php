<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\Image;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Permanently deletes an image's files and database row. No recycle bin.
 * Shared by admin destroy and the scheduled retention purge.
 */
class ImageDeletionService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
        //
    }

    public function delete(Image $image, ?User $actor = null): void
    {
        $disk = $image->disk;
        $originalPath = $image->storage_path;
        $thumbnailPath = $image->thumbnail_path;

        if ($actor !== null) {
            $this->auditLog->record($actor, AuditAction::Deleted, $image);
        } else {
            $this->auditLog->recordSystem(AuditAction::Deleted, $image);
        }

        $paths = array_values(array_filter([$originalPath, $thumbnailPath]));

        if ($paths !== []) {
            Storage::disk($disk)->delete($paths);
        }

        $image->delete();
    }
}
