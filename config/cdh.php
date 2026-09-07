<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Image retention (months)
    |--------------------------------------------------------------------------
    |
    | Images older than this many months (by images.created_at / upload time)
    | are permanently removed by the daily images:purge-expired command.
    |
    */

    'image_retention_months' => (int) env('IMAGE_RETENTION_MONTHS', 6),

    /*
    |--------------------------------------------------------------------------
    | Image upload size (bytes)
    |--------------------------------------------------------------------------
    |
    | The single source of truth for the maximum size of one image, enforced
    | both by StoreImagesRequest (client-declared size, first pass) and
    | ImageIngestionService (real byte count, authoritative — see
    | .ai/rules/image.md). The chunked-upload endpoint enforces the same limit
    | against the cumulative size of a session's chunks.
    |
    */

    'image_max_bytes' => (int) env('IMAGE_MAX_BYTES', 15 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Chunked upload settings
    |--------------------------------------------------------------------------
    |
    | upload_chunk_max_bytes bounds one chunk request (generous headroom over
    | the client's target chunk size, so it's never the reason a chunk fails).
    | upload_session_ttl_hours is how long an incomplete upload_sessions row
    | (and its chunk files) may sit idle before images:purge-stale-uploads
    | removes it.
    |
    */

    'upload_chunk_max_bytes' => (int) env('UPLOAD_CHUNK_MAX_BYTES', 6 * 1024 * 1024),

    'upload_session_ttl_hours' => (int) env('UPLOAD_SESSION_TTL_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Encrypted backups
    |--------------------------------------------------------------------------
    |
    | Two separate strategies: MySQL dump (backup:database) and X-ray file
    | archive (backup:images). Neither replaces the other — dumping MySQL
    | does not back up binaries under storage/app/private/xrays/.
    | BACKUP_ENCRYPTION_KEY is required; outputs land on the `backups` disk.
    |
    */

    'backup' => [
        'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
        'database_path' => 'database',
        'images_path' => 'images',
    ],

];
