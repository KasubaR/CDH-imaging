---
paths:
  - 'app/Services/ThumbnailGenerator.php,app/Http/Controllers/Image/**,app/Jobs/GenerateThumbnail.php'
---

# Jobs

## Thumbnail generation is now queued, not synchronous
Phase 29 (network optimization) moved thumbnailing off the request: ImageController::store() no longer calls ThumbnailGenerator directly. ImageIngestionService::ingest() dispatches App\Jobs\GenerateThumbnail::dispatch($image->id)->afterCommit() instead — afterCommit() means the job never fires for an image whose insert is rolled back as part of a multi-file batch failure. QUEUE_CONNECTION is now `database` (jobs table already existed); `composer run dev` already runs a queue listener via `php artisan dev`, so local dev needs no extra process, but production needs a supervised `queue:work`. Tests still hardcode QUEUE_CONNECTION=sync in phpunit.xml, so the job runs inline mid-request there — existing thumbnail assertions work unchanged. The job stays best-effort/never-throws, same as before: missing image, missing original, or a null ThumbnailGenerator result are all silent no-ops.
