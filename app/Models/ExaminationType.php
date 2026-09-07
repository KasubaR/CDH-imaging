<?php

namespace App\Models;

use Database\Factories\ExaminationTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'description', 'is_active'])]
class ExaminationType extends Model
{
    /** @use HasFactory<ExaminationTypeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function examinations(): HasMany
    {
        return $this->hasMany(Examination::class);
    }
}
