---
paths:
  - 'app/Services/ImageDeletionService.php,app/Console/Commands/PurgeExpiredImagesCommand.php,config/cdh.php,routes/console.php,app/Policies/ImagePolicy.php,app/Http/Controllers/Image/**,app/Http/Requests/Image/DestroyImageRequest.php'
---

# Image deletion and retention

## Permanent delete only — no recycle bin
`ImageDeletionService` is the single path for removing images (admin destroy and `images:purge-expired`). Order: audit `Deleted` first (while the row still exists), then delete original + thumbnail from the image's disk, then delete the `images` row. No soft deletes.

## Admin-only HTTP delete
`ImagePolicy::delete` is admin-only. UI lives on the examination viewer gallery → confirm page (`images.delete`) requiring typed `PERMANENTLY DELETE` → `DELETE images.destroy`. Gate `delete-images` stays for Blade if needed.

## Retention by upload time
`config('cdh.image_retention_months')` (env `IMAGE_RETENTION_MONTHS`, default 6). Daily schedule in `routes/console.php` runs `images:purge-expired` with `withoutOverlapping()`. Cutoff is `images.created_at < now()->subMonths(...)`. Purge audits via `recordSystem` (null actor). No per-department overrides in v1.
