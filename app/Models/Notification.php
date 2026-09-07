<?php

namespace App\Models;

use App\Enums\NotificationType;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'type', 'title', 'body', 'data', 'read_at'])]
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead(): void
    {
        if ($this->read_at !== null) {
            return;
        }

        $this->update(['read_at' => now()]);
    }

    /**
     * @param  Builder<Notification>  $query
     * @return Builder<Notification>
     */
    #[Scope]
    protected function unread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
