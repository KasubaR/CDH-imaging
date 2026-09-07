<?php

namespace App\Policies;

use App\Enums\Permission as PermissionEnum;
use App\Models\Examination;
use App\Models\Transfer;
use App\Models\User;

class ExaminationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission(PermissionEnum::ViewImages);
    }

    public function view(User $user, Examination $examination): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->hasPermission(PermissionEnum::ViewImages)) {
            return false;
        }

        $departmentId = $user->department_id;

        if ($departmentId === null) {
            return false;
        }

        if ($examination->referring_department_id === $departmentId) {
            return true;
        }

        return Transfer::query()
            ->where('examination_id', $examination->id)
            ->where(function ($query) use ($departmentId) {
                $query->where('from_department_id', $departmentId)
                    ->orWhereHas('recipients', fn ($recipientQuery) => $recipientQuery->where('department_id', $departmentId));
            })
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission(PermissionEnum::Upload);
    }
}
