<?php

namespace App\Models;

use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['patient_name', 'nrc', 'gender', 'date_of_birth'])]
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function examinations(): HasMany
    {
        return $this->hasMany(Examination::class);
    }
}
