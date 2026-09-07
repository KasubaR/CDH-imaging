<?php

namespace App\Policies;

use App\Enums\Permission as PermissionEnum;
use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission(PermissionEnum::ViewImages);
    }

    public function view(User $user, Patient $patient): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission(PermissionEnum::Upload);
    }
}
