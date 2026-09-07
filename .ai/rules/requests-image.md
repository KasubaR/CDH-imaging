---
paths:
  - 'app/Services/ImageIngestionService.php,app/Http/Controllers/Image/**,app/Http/Requests/Image/**'
---

# Requests Image

## ImageIngestionService is the single verify/dedupe/store path for every upload route
verifyGenuineImage() (finfo + getimagesize content-sniffing), the checksum-dedup check, the UUID-named store, and the GenerateThumbnail dispatch all moved out of ImageController into App\Services\ImageIngestionService::ingest(UploadedFile $file, Examination $examination, User $uploader, ?Request $request): Image — used by both ImageController::store() (direct multipart) and UploadChunkController (chunked, after assembling a real file from the session's chunks and wrapping it as `new UploadedFile($path, $name, null, null, true)`). Any new upload path must call this service, not reimplement verification/dedup — see .ai/rules/image.md and .ai/rules/models.md, which describe the checks this service now owns. The max allowed size is `App\Services\SettingsService::get('image_max_bytes')` (DB-backed admin override, falls back to `config('cdh.image_max_bytes')` with no row — see .ai/rules/settings.md), not a hardcoded constant or a raw config() call. StoreImagesRequest, ImageIngestionService, UploadChunkController and the images.create view all read it through that same service, never `config()` directly — `StoreUploadChunkRequest`'s `upload_chunk_max_bytes` is a different, still config()-only knob (a technical per-HTTP-request cap, not an admin setting).
