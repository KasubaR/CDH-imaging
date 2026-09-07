<?php

namespace App\Policies;

use App\Models\ExaminationType;
use App\Models\User;

class ExaminationTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-examination-types');
    }

    public function view(User $user, ExaminationType $examinationType): bool
    {
        return $user->can('manage-examination-types');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-examination-types');
    }

    public function update(User $user, ExaminationType $examinationType): bool
    {
        return $user->can('manage-examination-types');
    }
}
