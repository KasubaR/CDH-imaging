<?php

namespace App\Enums;

/**
 * Aggregate status of a Transfer across all of its recipients (App\Enums\TransferRecipientStatus
 * tracks the same lifecycle per department). Delivery is synchronous in this system — there is no
 * queue between departments — so a transfer moves to Delivered as soon as every recipient has been
 * delivered to, which currently happens in the same request that sends it.
 */
enum TransferStatus: string
{
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Completed => 'Completed',
        };
    }
}
