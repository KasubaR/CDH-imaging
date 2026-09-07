<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'from_department_id',
    'to_department_id',
    'can_send',
    'can_receive',
])]
class DepartmentPermission extends Model
{
    protected function casts(): array
    {
        return [
            'can_send' => 'boolean',
            'can_receive' => 'boolean',
        ];
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }
}
