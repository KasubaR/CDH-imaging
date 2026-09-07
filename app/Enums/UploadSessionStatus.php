<?php

namespace App\Enums;

/**
 * Lifecycle of one chunked-upload session (one client-selected file being sent
 * in pieces):
 *
 *   Pending -> Assembling -> Completed
 *                 \-> Failed
 *
 * Pending: at least one chunk received, not all of them yet.
 * Assembling: every chunk is on disk and UploadChunkController is concatenating
 * them and running the assembled file through ImageIngestionService.
 * Completed: ingested successfully — `image_id` is set and the chunk directory
 * is gone.
 * Failed: ingestion rejected the assembled file (duplicate checksum, invalid
 * content, oversized) — `failure_reason` holds the message and the chunk
 * directory is gone.
 */
enum UploadSessionStatus: string
{
    case Pending = 'pending';
    case Assembling = 'assembling';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }
}
