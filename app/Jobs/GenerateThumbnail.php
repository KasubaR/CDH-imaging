<?php

namespace App\Jobs;

use App\Models\Image;
use App\Services\ThumbnailGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Generates and attaches a thumbnail for an already-stored Image. Always
 * best-effort: never touches the original, never throws, and a missing
 * thumbnail (image gone, generation failed) just means the job quietly does
 * nothing — see ThumbnailGenerator and .ai/rules/controllers-image.md.
 */
class GenerateThumbnail implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $imageId)
    {
        //
    }

    public function handle(ThumbnailGenerator $thumbnailGenerator): void
    {
        $image = Image::query()->find($this->imageId);

        // Already thumbnailed (job retried after partially succeeding) or the
        // image was deleted before this ran — either way, nothing to do.
        if ($image === null || $image->thumbnail_path !== null) {
            return;
        }

        if (! Storage::disk($image->disk)->exists($image->storage_path)) {
            return;
        }

        $thumbnailBytes = $thumbnailGenerator->generate(
            Storage::disk($image->disk)->path($image->storage_path),
            $image->mime_type,
        );

        if ($thumbnailBytes === null) {
            return;
        }

        $thumbnailPath = 'xrays/thumbnails/'.$image->uuid.'.jpg';
        Storage::disk($image->disk)->put($thumbnailPath, $thumbnailBytes);
        $image->update(['thumbnail_path' => $thumbnailPath]);
    }
}
