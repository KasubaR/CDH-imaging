<?php

namespace App\Models;

use App\Enums\UploadSessionStatus;
use Database\Factories\UploadSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'examination_id',
    'uploaded_by',
    'original_filename',
    'declared_mime_type',
    'declared_size',
    'total_chunks',
    'status',
    'image_id',
    'failure_reason',
    'expires_at',
])]
class UploadSession extends Model
{
    /** @use HasFactory<UploadSessionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'declared_size' => 'integer',
            'total_chunks' => 'integer',
            'status' => UploadSessionStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }
}
