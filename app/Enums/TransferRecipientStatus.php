<?php

namespace App\Enums;

/**
 * Per-department lifecycle of one TransferRecipient row:
 *
 *   Pending -> Delivered -> Acknowledged -> Viewed -> Downloaded -> Completed
 *                              \-> Rejected (branch, from any non-terminal stage)
 *                              \-> Recalled (branch, only before Acknowledged)
 *
 * Viewed and Downloaded are deliberately separate stages/columns — opening an examination in the
 * viewer is not the same event as downloading an original image file, and each is recorded only
 * when that specific action actually happens (see App\Services\TransferLifecycleService). Stages
 * are never skipped/backfilled by an unrelated event; a recipient's status always reflects the
 * furthest stage actually reached.
 *
 * Acknowledging is mandatory: a recipient department cannot progress to Viewed until it has
 * acknowledged receipt (see TransferLifecycleService::departmentMustAcknowledge()). Rejected and
 * Recalled are terminal branches — order() ranks them alongside Completed so neither can be
 * reached from the other via the normal forward-only advance. Recalled is only allowed before
 * Acknowledged; after that the recipient must Reject instead.
 */
enum TransferRecipientStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Acknowledged = 'acknowledged';
    case Viewed = 'viewed';
    case Downloaded = 'downloaded';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Recalled = 'recalled';

    /**
     * Position in the lifecycle, for forward-only comparisons (never let a status regress).
     * Completed, Rejected and Recalled are all terminal and rank equally last.
     */
    public function order(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Delivered => 1,
            self::Acknowledged => 2,
            self::Viewed => 3,
            self::Downloaded => 4,
            self::Completed, self::Rejected, self::Recalled => 5,
        };
    }

    /**
     * The transfer_recipients timestamp column this stage is recorded on, or null for Pending,
     * which has no timestamp of its own (it's the implicit starting state).
     */
    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::Pending => null,
            self::Delivered => 'delivered_at',
            self::Acknowledged => 'acknowledged_at',
            self::Viewed => 'viewed_at',
            self::Downloaded => 'downloaded_at',
            self::Completed => 'completed_at',
            self::Rejected => 'rejected_at',
            self::Recalled => 'recalled_at',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Completed
            || $this === self::Rejected
            || $this === self::Recalled;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Delivered => 'Delivered',
            self::Acknowledged => 'Acknowledged',
            self::Viewed => 'Viewed',
            self::Downloaded => 'Downloaded',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
            self::Recalled => 'Recalled',
        };
    }

    /**
     * Wording for the department inbox table (Phase 17), which uses "New"/"Received" in place of
     * the internal Delivered/Acknowledged names — everything downstream keeps the same word.
     */
    public function inboxLabel(): string
    {
        return match ($this) {
            self::Pending, self::Delivered => 'New',
            self::Acknowledged => 'Received',
            self::Viewed => 'Viewed',
            self::Downloaded => 'Downloaded',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
            self::Recalled => 'Recalled',
        };
    }

    /**
     * x-status-badge variant slug (resources/css/components.css .status-badge--{variant}).
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending, self::Delivered => 'new',
            self::Acknowledged => 'acknowledged',
            self::Viewed => 'viewed',
            self::Downloaded => 'downloaded',
            self::Completed => 'completed',
            self::Rejected, self::Recalled => 'rejected',
        };
    }
}
