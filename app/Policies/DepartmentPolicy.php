<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-departments');
    }

    public function view(User $user, Department $department): bool
    {
        return $user->can('manage-departments');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-departments');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->can('manage-departments');
    }
}
