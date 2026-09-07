<?php

namespace App\Services;

use GdImage;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates a bounded, aspect-ratio-preserving JPEG thumbnail from a verified
 * original image using PHP's built-in GD extension — no external dependency.
 *
 * Thumbnailing is always best-effort: it never throws and never touches the
 * original file. A null return means "no thumbnail" (already small enough, or
 * generation failed) and callers must fall back to the original.
 */
class ThumbnailGenerator
{
    private const MAX_DIMENSION = 500;

    private const JPEG_QUALITY = 80;

    /**
     * @return string|null the encoded JPEG bytes, or null if no thumbnail was produced
     */
    public function generate(string $sourcePath, string $mimeType): ?string
    {
        try {
            $source = $this->createImageResource($sourcePath, $mimeType);

            if ($source === null) {
                return null;
            }

            $width = imagesx($source);
            $height = imagesy($source);

            if ($width <= self::MAX_DIMENSION && $height <= self::MAX_DIMENSION) {
                imagedestroy($source);

                return null;
            }

            $scale = min(self::MAX_DIMENSION / $width, self::MAX_DIMENSION / $height);
            $thumbnailWidth = max(1, (int) round($width * $scale));
            $thumbnailHeight = max(1, (int) round($height * $scale));

            $thumbnail = imagecreatetruecolor($thumbnailWidth, $thumbnailHeight);

            // JPEG has no alpha channel — flatten any transparency onto white first.
            $white = imagecolorallocate($thumbnail, 255, 255, 255);
            imagefill($thumbnail, 0, 0, $white);

            imagecopyresampled(
                $thumbnail, $source,
                0, 0, 0, 0,
                $thumbnailWidth, $thumbnailHeight, $width, $height,
            );
            imagedestroy($source);

            ob_start();
            $encoded = imagejpeg($thumbnail, null, self::JPEG_QUALITY);
            $bytes = ob_get_clean();
            imagedestroy($thumbnail);

            if (! $encoded || $bytes === false || $bytes === '') {
                return null;
            }

            return $bytes;
        } catch (Throwable $exception) {
            Log::warning('Thumbnail generation failed.', [
                'source_path' => $sourcePath,
                'mime_type' => $mimeType,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function createImageResource(string $path, string $mimeType): ?GdImage
    {
        $resource = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            default => false,
        };

        return $resource instanceof GdImage ? $resource : null;
    }
}
