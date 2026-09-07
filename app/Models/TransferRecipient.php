<?php

namespace App\Models;

use App\Enums\TransferRecipientStatus;
use Database\Factories\TransferRecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'transfer_id',
    'department_id',
    'status',
    'delivered_at',
    'acknowledged_at',
    'received_by',
    'viewed_at',
    'downloaded_at',
    'completed_at',
    'rejected_at',
    'rejection_reason',
    'recalled_at',
])]
class TransferRecipient extends Model
{
    /** @use HasFactory<TransferRecipientFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => TransferRecipientStatus::class,
            'delivered_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'viewed_at' => 'datetime',
            'downloaded_at' => 'datetime',
            'completed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'recalled_at' => 'datetime',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Who acknowledged receipt (acknowledged_at is when).
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
