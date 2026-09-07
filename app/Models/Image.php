<?php

namespace App\Models;

use Database\Factories\ImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'examination_id',
    'uploaded_by',
    'disk',
    'uuid',
    'stored_filename',
    'storage_path',
    'thumbnail_path',
    'original_filename',
    'mime_type',
    'file_size',
    'checksum',
])]
class Image extends Model
{
    /** @use HasFactory<ImageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
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

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
