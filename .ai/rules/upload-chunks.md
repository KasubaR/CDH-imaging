---
paths:
  - 'app/Http/Controllers/Image/UploadChunkController.php,app/Services/ImageIngestionService.php,app/Jobs/GenerateThumbnail.php,app/Models/UploadSession.php,resources/js/upload.js,resources/views/images/create.blade.php'
---

# Chunked upload architecture

## Pipeline (Phase 30)
Browser (`resources/js/upload.js`) → chunk POST → temporary `xrays/chunks/{upload_uuid}/` + `upload_sessions` → reassemble → `ImageIngestionService` (validate MIME/size, sha256 checksum, permanent `xrays/originals/`) → `GenerateThumbnail` on the database queue → `xrays/thumbnails/`.

The user continues using the app while thumbnails generate asynchronously (`QUEUE_CONNECTION=database`). Multipart `examinations.images.store` remains a fallback for tests/non-JS; the create UI is chunk-only.

## Client limits come from config
`images/create.blade.php` exposes `data-max-bytes` (`cdh.image_max_bytes`) and `data-chunk-bytes` (min of 4 MB and `cdh.upload_chunk_max_bytes`). `upload.js` must not hardcode 15 MB — see also `.ai/rules/js.md` for session/resume/purge details.
