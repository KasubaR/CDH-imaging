<?php

namespace App\Models;

use Database\Factories\ExaminationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'patient_id',
    'examination_type_id',
    'body_part',
    'description',
    'date_taken',
    'time_taken',
    'referring_department_id',
    'referring_clinician',
    'radiographer',
    'created_by',
])]
class Examination extends Model
{
    /** @use HasFactory<ExaminationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date_taken' => 'date',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function examinationType(): BelongsTo
    {
        return $this->belongsTo(ExaminationType::class);
    }

    public function referringDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'referring_department_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class);
    }
}
