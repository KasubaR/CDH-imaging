<?php

namespace App\Http\Controllers\Image;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Image\DestroyImageRequest;
use App\Http\Requests\Image\StoreImagesRequest;
use App\Models\Examination;
use App\Models\Image;
use App\Services\AuditLogService;
use App\Services\ImageDeletionService;
use App\Services\ImageIngestionService;
use App\Services\SettingsService;
use App\Services\TransferLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ImageController extends Controller
{
    private const STORAGE_DISK = ImageIngestionService::STORAGE_DISK;

    public function __construct(
        private readonly ImageIngestionService $imageIngestion,
        private readonly TransferLifecycleService $transferLifecycle,
        private readonly AuditLogService $auditLog,
        private readonly ImageDeletionService $imageDeletion,
        private readonly SettingsService $settings,
    ) {
        //
    }

    public function create(Examination $examination): View
    {
        $this->authorize('upload', [Image::class, $examination]);

        $examination->load(['patient', 'examinationType']);

        return view('images.create', [
            'examination' => $examination,
            'imageMaxBytes' => $this->settings->get('image_max_bytes'),
        ]);
    }

    public function store(StoreImagesRequest $request, Examination $examination): RedirectResponse
    {
        $this->authorize('upload', [Image::class, $examination]);

        /** @var list<UploadedFile> $files */
        $files = $request->file('images');
        $storedPaths = [];

        try {
            DB::transaction(function () use ($files, $examination, $request, &$storedPaths): void {
                foreach ($files as $file) {
                    $image = $this->imageIngestion->ingest($file, $examination, $request->user(), $request);
                    $storedPaths[] = $image->storage_path;
                }
            });
        } catch (Throwable $exception) {
            Storage::disk(self::STORAGE_DISK)->delete($storedPaths);

            throw $exception;
        }

        return redirect()
            ->route('examinations.show', $examination)
            ->with('status', count($files).' image(s) uploaded successfully.');
    }

    public function show(Image $image): StreamedResponse
    {
        $this->authorize('view', $image);

        $user = auth()->user();

        if ($user !== null) {
            $this->auditLog->record($user, AuditAction::Viewed, $image);
        }

        return Storage::disk($image->disk)->response(
            $image->storage_path,
            $image->original_filename,
            ['Content-Type' => $image->mime_type],
        );
    }

    /**
     * Serve the lightweight preview for gallery/grid views. Falls back to the
     * full original when no thumbnail was generated (skipped or failed at
     * upload time) so a view never breaks just because one is missing.
     */
    public function thumbnail(Image $image): StreamedResponse
    {
        $this->authorize('view', $image);

        if ($image->thumbnail_path === null) {
            return Storage::disk($image->disk)->response(
                $image->storage_path,
                $image->original_filename,
                ['Content-Type' => $image->mime_type],
            );
        }

        return Storage::disk($image->disk)->response(
            $image->thumbnail_path,
            $image->original_filename,
            ['Content-Type' => 'image/jpeg'],
        );
    }

    /**
     * Force a real file download (Content-Disposition: attachment) of the
     * original, as opposed to show()'s inline display. Gated separately via
     * the `download` permission/policy — viewing and downloading are distinct
     * abilities.
     */
    public function download(Image $image): StreamedResponse
    {
        $this->authorize('download', $image);

        $user = auth()->user();

        if ($user !== null && $image->examination !== null) {
            $this->transferLifecycle->trackImageDownloaded($image->examination, $user);
            $this->auditLog->record($user, AuditAction::Downloaded, $image);
        }

        $extension = pathinfo($image->stored_filename, PATHINFO_EXTENSION) ?: 'jpg';
        $safeFilename = 'XRAY_'.substr($image->uuid, 0, 8).'.'.$extension;

        return Storage::disk($image->disk)->download(
            $image->storage_path,
            $safeFilename,
            ['Content-Type' => $image->mime_type],
        );
    }

    public function confirmDelete(Image $image): View
    {
        $this->authorize('delete', $image);

        $image->load(['examination.patient', 'examination.examinationType']);

        return view('images.delete', [
            'image' => $image,
            'examination' => $image->examination,
        ]);
    }

    public function destroy(DestroyImageRequest $request, Image $image): RedirectResponse
    {
        $examination = $image->examination;

        $this->imageDeletion->delete($image, $request->user());

        if ($examination === null) {
            return redirect()
                ->route('patients.index')
                ->with('status', 'Image permanently deleted.');
        }

        return redirect()
            ->route('examinations.show', $examination)
            ->with('status', 'Image permanently deleted.');
    }
}
