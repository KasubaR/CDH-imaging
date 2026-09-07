<?php

namespace App\Enums;

enum NotificationType: string
{
    case TransferReceived = 'transfer_received';
    case TransferRejected = 'transfer_rejected';
    case TransferRecalled = 'transfer_recalled';

    public function label(): string
    {
        return match ($this) {
            self::TransferReceived => 'New X-ray received',
            self::TransferRejected => 'Transfer rejected',
            self::TransferRecalled => 'Transfer recalled',
        };
    }
}
