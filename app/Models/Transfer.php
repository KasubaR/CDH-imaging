<?php

namespace App\Models;

use App\Enums\TransferStatus;
use Database\Factories\TransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'examination_id',
    'from_department_id',
    'sent_by',
    'message',
    'status',
    'sent_at',
    'delivered_at',
    'completed_at',
])]
class Transfer extends Model
{
    /** @use HasFactory<TransferFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(TransferRecipient::class);
    }
}
