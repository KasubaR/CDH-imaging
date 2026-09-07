<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Jobs\GenerateThumbnail;
use App\Models\Examination;
use App\Models\Image;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The single path every image upload — the direct multipart form and the
 * chunked-upload assembler alike — must go through to turn a real file on
 * disk into an `images` row. Independently re-verifies the file's genuine
 * content (never trusting the client's filename/MIME/extension), enforces
 * the sha256 checksum dedup, and dispatches thumbnail generation. See
 * .ai/rules/image.md and .ai/rules/models.md — don't bypass this by
 * inserting into `images` directly from another controller.
 */
class ImageIngestionService
{
    /**
     * Server-verified MIME types mapped to the extension each is stored under.
     * Never derived from the client's filename or declared Content-Type.
     *
     * @var array<string, string>
     */
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public const STORAGE_DISK = 'local';

    public const ORIGINALS_DIRECTORY = 'xrays/originals';

    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly SettingsService $settings,
    ) {
        //
    }

    /**
     * Verify, store, and persist one uploaded file as an Image belonging to
     * $examination, then dispatch (best-effort, queued) thumbnail generation.
     *
     * @throws ValidationException
     */
    public function ingest(UploadedFile $file, Examination $examination, User $uploader, ?Request $request = null): Image
    {
        [$mimeType, $sizeBytes, $checksum] = $this->verifyGenuineImage($file);

        if (Image::query()->where('checksum', $checksum)->exists()) {
            throw $this->duplicateException($file);
        }

        $uuid = (string) Str::uuid();
        $storedFilename = $uuid.'.'.self::ALLOWED_MIME_TYPES[$mimeType];
        $path = $file->storeAs(self::ORIGINALS_DIRECTORY, $storedFilename, self::STORAGE_DISK);

        try {
            $image = $examination->images()->create([
                'uploaded_by' => $uploader->id,
                'disk' => self::STORAGE_DISK,
                'uuid' => $uuid,
                'stored_filename' => $storedFilename,
                'storage_path' => $path,
                'thumbnail_path' => null,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $mimeType,
                'file_size' => $sizeBytes,
                'checksum' => $checksum,
            ]);
        } catch (QueryException $exception) {
            Storage::disk(self::STORAGE_DISK)->delete($path);

            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            // Another request stored the identical file (or reused this UUID) between
            // our checksum check above and this insert. The unique indexes on
            // checksum/uuid are the authoritative guard against that race.
            throw $this->duplicateException($file);
        }

        $this->auditLog->record($uploader, AuditAction::Uploaded, $image, $request);

        // Queued (see .ai/rules/controllers-image.md): best-effort, never blocks or
        // fails the upload. afterCommit() means it never fires for an image whose
        // insert is later rolled back as part of a multi-file batch failure.
        GenerateThumbnail::dispatch($image->id)->afterCommit();

        return $image;
    }

    /**
     * Independently re-verify a file's real content before it is ever stored.
     *
     * Client-declared size/extension/MIME type are a first pass, but a client can
     * lie about all three. This re-derives everything from the actual bytes —
     * never from the upload's filename or reported Content-Type.
     *
     * @return array{0: string, 1: int, 2: string} the verified MIME type, byte size and sha256 checksum
     *
     * @throws ValidationException
     */
    private function verifyGenuineImage(UploadedFile $file): array
    {
        $realPath = $file->getRealPath();

        if (! $file->isValid() || $realPath === false || ! is_file($realPath)) {
            throw ValidationException::withMessages([
                'images' => ['One of the uploaded files failed to transfer correctly.'],
            ]);
        }

        $sizeBytes = filesize($realPath);
        $maxBytes = $this->settings->get('image_max_bytes');

        if ($sizeBytes === false || $sizeBytes === 0 || $sizeBytes > $maxBytes) {
            throw ValidationException::withMessages([
                'images' => ['Each file must be larger than 0 bytes and '.($maxBytes / (1024 * 1024)).' MB or smaller.'],
            ]);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMimeType = $finfo === false ? false : finfo_file($finfo, $realPath);
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if (! is_string($detectedMimeType) || ! array_key_exists($detectedMimeType, self::ALLOWED_MIME_TYPES)) {
            throw ValidationException::withMessages([
                'images' => ['Only JPG, JPEG and PNG files are supported.'],
            ]);
        }

        $imageInfo = @getimagesize($realPath);

        if ($imageInfo === false || ! array_key_exists($imageInfo['mime'], self::ALLOWED_MIME_TYPES)) {
            throw ValidationException::withMessages([
                'images' => ['One of the uploaded files is not a valid image.'],
            ]);
        }

        // Require the magic-byte sniff and the decoded image header to agree; a
        // mismatch (e.g. a JPEG/PHP polyglot with tampered headers) is suspicious
        // enough to reject outright rather than trust either signal alone.
        if ($imageInfo['mime'] !== $detectedMimeType) {
            throw ValidationException::withMessages([
                'images' => ['One of the uploaded files is not a valid image.'],
            ]);
        }

        $checksum = hash_file('sha256', $realPath);

        if ($checksum === false) {
            throw ValidationException::withMessages([
                'images' => ['One of the uploaded files could not be verified.'],
            ]);
        }

        return [$detectedMimeType, $sizeBytes, $checksum];
    }

    private function duplicateException(UploadedFile $file): ValidationException
    {
        return ValidationException::withMessages([
            'images' => ["{$file->getClientOriginalName()} has already been uploaded to the system."],
        ]);
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23000';
    }
}
