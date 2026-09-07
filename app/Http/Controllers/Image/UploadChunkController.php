<?php

namespace App\Http\Controllers\Image;

use App\Enums\UploadSessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Image\StoreUploadChunkRequest;
use App\Models\Examination;
use App\Models\Image;
use App\Models\UploadSession;
use App\Models\User;
use App\Services\ImageIngestionService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Chunked-upload endpoints backing resources/js/upload.js. A large file is
 * sliced client-side and posted here piece by piece so one flaky connection
 * only has to retry a few MB, not the whole file — see the "Network
 * optimization" phase plan.
 *
 * Chunks land on the same private `local` disk as originals, under
 * xrays/chunks/{upload_uuid}/{chunk_index}, and are never exposed by a
 * public route. Once every chunk for a session is on disk they're
 * concatenated and handed to ImageIngestionService — the exact same
 * verify/dedup/store path the direct multipart upload uses (see
 * .ai/rules/image.md, .ai/rules/models.md) — so chunking never becomes a
 * second way to get an unverified file into `images`.
 */
class UploadChunkController extends Controller
{
    private const STORAGE_DISK = ImageIngestionService::STORAGE_DISK;

    private const CHUNKS_DIRECTORY = 'xrays/chunks';

    public function __construct(
        private readonly ImageIngestionService $imageIngestion,
        private readonly SettingsService $settings,
    ) {
        //
    }

    public function store(StoreUploadChunkRequest $request, Examination $examination): JsonResponse
    {
        $this->authorize('upload', [Image::class, $examination]);

        $user = $request->user();
        $uploadId = $request->string('upload_id')->toString();
        $chunkIndex = $request->integer('chunk_index');
        $totalChunks = $request->integer('total_chunks');

        $session = UploadSession::query()->where('uuid', $uploadId)->first();

        if ($session === null) {
            $session = UploadSession::query()->create([
                'uuid' => $uploadId,
                'examination_id' => $examination->id,
                'uploaded_by' => $user->id,
                'original_filename' => $request->string('filename')->toString(),
                'declared_mime_type' => $request->file('chunk')?->getMimeType() ?? 'application/octet-stream',
                'total_chunks' => $totalChunks,
                'status' => UploadSessionStatus::Pending,
                'expires_at' => now()->addHours($this->settings->get('upload_session_ttl_hours')),
            ]);
        }

        // A session is pinned to the examination/user that started it — chunks
        // for someone else's (or another examination's) upload_id are refused,
        // not silently appended to it.
        if ($session->examination_id !== $examination->id || $session->uploaded_by !== $user->id) {
            abort(403);
        }

        if ($session->status === UploadSessionStatus::Completed) {
            return response()->json([
                'status' => 'completed',
                'image_uuid' => $session->image?->uuid,
            ]);
        }

        if ($session->status === UploadSessionStatus::Failed) {
            return response()->json([
                'status' => 'failed',
                'message' => $session->failure_reason ?? 'Upload failed.',
            ], 422);
        }

        if ($totalChunks !== $session->total_chunks || $chunkIndex >= $totalChunks) {
            return response()->json([
                'message' => 'Chunk metadata does not match the original upload session.',
            ], 422);
        }

        $chunkDirectory = self::CHUNKS_DIRECTORY.'/'.$session->uuid;

        $maxBytes = $this->settings->get('image_max_bytes');
        $cumulativeBytes = $this->chunkBytesTotal($chunkDirectory) + $request->file('chunk')->getSize();

        if ($cumulativeBytes > $maxBytes) {
            $this->failSession($session, 'File exceeds the '.intdiv($maxBytes, 1024 * 1024).' MB limit.');
            Storage::disk(self::STORAGE_DISK)->deleteDirectory($chunkDirectory);

            return response()->json([
                'status' => 'failed',
                'message' => 'File exceeds the '.intdiv($maxBytes, 1024 * 1024).' MB limit.',
            ], 422);
        }

        $request->file('chunk')->storeAs($chunkDirectory, (string) $chunkIndex, self::STORAGE_DISK);

        $receivedCount = count($this->receivedChunkIndexes($chunkDirectory));

        if ($receivedCount < $totalChunks) {
            return response()->json([
                'status' => 'chunk_received',
                'received' => $receivedCount,
                'total' => $totalChunks,
            ]);
        }

        return $this->assembleAndIngest($session, $examination, $user, $request, $chunkDirectory);
    }

    /**
     * Report which chunk indexes already made it to disk for a given upload,
     * so a resumed/reloaded client only has to (re-)send what's missing.
     */
    public function status(Request $request, Examination $examination, string $uploadId): JsonResponse
    {
        $this->authorize('upload', [Image::class, $examination]);

        $session = UploadSession::query()
            ->where('uuid', $uploadId)
            ->where('examination_id', $examination->id)
            ->where('uploaded_by', $request->user()->id)
            ->first();

        if ($session === null) {
            return response()->json(['status' => 'not_found', 'received' => []]);
        }

        $received = $session->status->isTerminal()
            ? []
            : $this->receivedChunkIndexes(self::CHUNKS_DIRECTORY.'/'.$session->uuid);

        return response()->json([
            'status' => $session->status->value,
            'received' => $received,
            'total' => $session->total_chunks,
            'image_uuid' => $session->image?->uuid,
            'message' => $session->failure_reason,
        ]);
    }

    private function assembleAndIngest(
        UploadSession $session,
        Examination $examination,
        User $user,
        Request $request,
        string $chunkDirectory,
    ): JsonResponse {
        $session->update(['status' => UploadSessionStatus::Assembling]);

        $tempPath = tempnam(sys_get_temp_dir(), 'cdh_upload_');

        try {
            $this->assembleChunks($chunkDirectory, $session->total_chunks, $tempPath);

            $assembledFile = new UploadedFile($tempPath, $session->original_filename, null, null, true);

            $image = DB::transaction(
                fn () => $this->imageIngestion->ingest($assembledFile, $examination, $user, $request),
            );

            $session->update([
                'status' => UploadSessionStatus::Completed,
                'image_id' => $image->id,
            ]);

            return response()->json([
                'status' => 'completed',
                'image_uuid' => $image->uuid,
            ]);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Upload failed.';

            $this->failSession($session, $message);

            return response()->json(['status' => 'failed', 'message' => $message], 422);
        } finally {
            if (is_file($tempPath)) {
                unlink($tempPath);
            }

            Storage::disk(self::STORAGE_DISK)->deleteDirectory($chunkDirectory);
        }
    }

    private function assembleChunks(string $chunkDirectory, int $totalChunks, string $destinationPath): void
    {
        $out = fopen($destinationPath, 'wb');

        if ($out === false) {
            throw ValidationException::withMessages([
                'images' => ['The upload could not be assembled.'],
            ]);
        }

        for ($index = 0; $index < $totalChunks; $index++) {
            $chunkStream = Storage::disk(self::STORAGE_DISK)->readStream($chunkDirectory.'/'.$index);

            if ($chunkStream === null) {
                fclose($out);

                throw ValidationException::withMessages([
                    'images' => ['The upload could not be assembled — a chunk went missing.'],
                ]);
            }

            stream_copy_to_stream($chunkStream, $out);
            fclose($chunkStream);
        }

        fclose($out);
    }

    private function failSession(UploadSession $session, string $reason): void
    {
        $session->update([
            'status' => UploadSessionStatus::Failed,
            'failure_reason' => $reason,
        ]);
    }

    /**
     * @return list<int>
     */
    private function receivedChunkIndexes(string $chunkDirectory): array
    {
        $indexes = array_map(
            fn (string $path) => (int) basename($path),
            Storage::disk(self::STORAGE_DISK)->files($chunkDirectory),
        );

        sort($indexes);

        return $indexes;
    }

    private function chunkBytesTotal(string $chunkDirectory): int
    {
        return array_sum(array_map(
            fn (string $path) => Storage::disk(self::STORAGE_DISK)->size($path),
            Storage::disk(self::STORAGE_DISK)->files($chunkDirectory),
        ));
    }
}
