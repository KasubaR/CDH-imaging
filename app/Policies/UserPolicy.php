<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-accounts');
    }

    public function view(User $user, User $account): bool
    {
        return $user->can('manage-accounts');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-accounts');
    }

    public function update(User $user, User $account): bool
    {
        return $user->can('manage-accounts');
    }
}
