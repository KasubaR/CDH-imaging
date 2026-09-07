---
paths:
  - 'app/Models/UploadSession.php,app/Http/Controllers/Image/UploadChunkController.php,app/Enums/UploadSessionStatus.php,app/Console/Commands/PurgeStaleUploadSessionsCommand.php,resources/js/upload.js'
---

# Js

## Chunked upload pipeline: upload_sessions, chunk directories, resume, cleanup
resources/js/upload.js slices each selected file into chunks sized by `data-chunk-bytes` from the create form (defaults to ~4MB, capped by `config('cdh.upload_chunk_max_bytes')`) and POSTs them to examinations.images.chunks.store with up to 3 files concurrent (MAX_CONCURRENT_FILES) — this replaced the old single-FormData-per-batch submit, which could exceed post_max_size for a large multi-file batch. Client max file size comes from `data-max-bytes` (`config('cdh.image_max_bytes')`), not a hardcoded 15 MB.

Server side: UploadChunkController stores chunks at xrays/chunks/{upload_uuid}/{chunk_index} on the private `local` disk and creates/updates one `upload_sessions` row (App\Enums\UploadSessionStatus: Pending -> Assembling -> Completed|Failed) per client-generated upload_id (a UUID). A session is pinned to the examination+user that started it (uploaded_by/examination_id checked on every chunk, 403 otherwise). Once every expected chunk index is on disk (pigeonhole: count == total_chunks and chunk_index is validated < total_chunks, so no gap-checking needed), the controller concatenates them into a real temp file, wraps it as `new UploadedFile($path, $name, null, null, true)`, and runs it through ImageIngestionService::ingest() — the exact same path the direct multipart upload uses. Failure (duplicate checksum, invalid content, oversized) marks the session Failed with failure_reason and deletes the chunk directory; never leaves partial chunks around on a terminal outcome.

GET examinations.images.chunks.status returns which chunk indexes already exist for an upload_id so the client can resume after a reload/dropped connection — resources/js/upload.js persists {file name|size|lastModified -> upload_id} in localStorage (key `cdh-upload-resume:{examinationId}`) to recognize a re-selected file and resume it, but only when the prior attempt didn't end terminally (a Failed/not_found session mints a fresh upload_id instead of retrying a dead one).

Abandoned sessions (Pending/Assembling/Failed older than config('cdh.upload_session_ttl_hours'), default 24h) and their chunk directories are removed by the hourly `images:purge-stale-uploads` command (routes/console.php) — mirrors PurgeExpiredImagesCommand's pattern but is a separate command/schedule entry since it's cleaning up incomplete uploads, not retention-expired completed ones.
