<?php

namespace App\Enums;

enum AuditAction: string
{
    case Uploaded = 'uploaded';
    case Sent = 'sent';
    case Received = 'received';
    case Viewed = 'viewed';
    case Downloaded = 'downloaded';
    case Forwarded = 'forwarded';
    case Recalled = 'recalled';
    case Rejected = 'rejected';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'uploaded X-ray',
            self::Sent => 'sent X-ray',
            self::Received => 'received X-ray',
            self::Viewed => 'viewed X-ray',
            self::Downloaded => 'downloaded X-ray',
            self::Forwarded => 'forwarded X-ray',
            self::Recalled => 'recalled X-ray',
            self::Rejected => 'rejected X-ray',
            self::Deleted => 'deleted X-ray',
        };
    }
}
