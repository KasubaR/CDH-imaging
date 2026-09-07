---
paths:
  - 'app/Services/ThumbnailGenerator.php,app/Http/Controllers/Image/**'
---

# Controllers Image

## Thumbnails: queued, best-effort, never touch the original
ThumbnailGenerator::generate() is called from App\Jobs\GenerateThumbnail, not inline in ImageController::store() — see .ai/rules/jobs.md for the queue wiring (Phase 29 moved this off the request once QUEUE_CONNECTION became `database`).

It's GD-based (no new Composer dependency), bounds to 500px on the longer side preserving aspect ratio, skips (returns null) if the original is already within bounds, and always re-encodes as JPEG regardless of source format. It never throws — any failure is logged and treated as "no thumbnail", never as an upload failure, and it never reads/writes the original file.

Stored at xrays/thumbnails/{uuid}.jpg on the same `local` disk as the original, referenced by images.thumbnail_path. ImageController::thumbnail() (GET /images/{image}/thumbnail) falls back to serving the full original when thumbnail_path is null — a missing thumbnail must never break a view. Any UI that renders a grid/list of images should hit images.thumbnail, not images.show — see the gallery in resources/views/examinations/show.blade.php for the pattern.
