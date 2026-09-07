---
paths:
  - 'app/Http/Controllers/Image/**,app/Http/Requests/Image/**,database/migrations/*images*,app/Models/Image.php'
---

# Models

## Image storage: private disk, UUID filenames, sha256 dedup
Originals live on the `local` disk (storage/app/private/xrays/originals/), never `public` — there is no direct public URL. The only way to view an image is the authenticated GET /images/{uuid} route (images.show; route key is `uuid`), which calls ImagePolicy::view before streaming via Storage::disk($image->disk)->response(). Don't reintroduce asset('storage/...') for images. Downloads use a patient-safe Content-Disposition name `XRAY_{first8uuid}.{ext}` — never the patient name or original upload filename.

Storage filename = Str::uuid() + the extension verifyGenuineImage() verified — stored in `uuid`/`stored_filename`/`storage_path` columns. `original_filename` is display-only, never used for anything filesystem-related.

ImagePolicy grants access to the referring department, or a transfer from/to the user's department whose recipient row is not Rejected/Recalled. EnsureAccountIsActive middleware logs out inactive accounts on every web request.

Duplicate detection (checksum column, sha256, DB-unique): verifyGenuineImage() now also returns hash_file('sha256', $realPath). Before storing, ImageIngestionService::ingest() checks Image::where('checksum', $checksum)->exists() and rejects with a ValidationException if found — global/system-wide, not scoped to one examination. The unique index on `checksum` is the authoritative backstop for a concurrent-upload race (caught as a QueryException with SQLSTATE 23000 and converted to the same rejection message). If you add another upload path, it must go through this same service — don't bypass by inserting into `images` directly; see .ai/rules/requests-image.md.

`thumbnail_path` is populated asynchronously by the queued App\Jobs\GenerateThumbnail — see .ai/rules/jobs.md.
