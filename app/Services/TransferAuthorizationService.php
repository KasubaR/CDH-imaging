<?php

namespace App\Services;

use App\Enums\Permission as PermissionEnum;
use App\Models\Department;
use App\Models\DepartmentPermission;
use App\Models\User;
use Illuminate\Support\Collection;

class TransferAuthorizationService
{
    /** @var array<string, DepartmentPermission|null> */
    private array $permissionCache = [];

    public function canTransfer(Department $from, Department $to): bool
    {
        if (! $from->is_active || ! $to->is_active) {
            return false;
        }

        if ($from->id === $to->id) {
            return false;
        }

        $permission = $this->findPermission($from, $to);

        return $permission !== null
            && $permission->can_send
            && $permission->can_receive;
    }

    public function canUserSendTo(User $user, Department $to): bool
    {
        if ($user->isAdmin()) {
            return $to->is_active;
        }

        if (! $user->hasPermission(PermissionEnum::Send)) {
            return false;
        }

        $from = $user->department;

        if ($from === null) {
            return false;
        }

        return $this->canTransfer($from, $to);
    }

    public function canUserReceiveFrom(User $user, Department $from): bool
    {
        if ($user->isAdmin()) {
            return $from->is_active;
        }

        if (! $user->hasPermission(PermissionEnum::Receive)) {
            return false;
        }

        $to = $user->department;

        if ($to === null) {
            return false;
        }

        return $this->canTransfer($from, $to);
    }

    public function canUserForward(User $user, Department $to): bool
    {
        if ($user->isAdmin()) {
            return $to->is_active;
        }

        if (! $user->hasPermission(PermissionEnum::Forward)) {
            return false;
        }

        $from = $user->department;

        if ($from === null) {
            return false;
        }

        return $this->canTransfer($from, $to);
    }

    public function canUserSendAtAll(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->hasPermission(PermissionEnum::Send)) {
            return false;
        }

        $from = $user->department;

        if ($from === null || ! $from->is_active) {
            return false;
        }

        return DepartmentPermission::query()
            ->where('from_department_id', $from->id)
            ->where('can_send', true)
            ->where('can_receive', true)
            ->whereHas('toDepartment', fn ($query) => $query->where('is_active', true))
            ->exists();
    }

    public function canUserReceiveAtAll(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->hasPermission(PermissionEnum::Receive)) {
            return false;
        }

        $to = $user->department;

        if ($to === null || ! $to->is_active) {
            return false;
        }

        return DepartmentPermission::query()
            ->where('to_department_id', $to->id)
            ->where('can_send', true)
            ->where('can_receive', true)
            ->whereHas('fromDepartment', fn ($query) => $query->where('is_active', true))
            ->exists();
    }

    /**
     * @return Collection<int, Department>
     */
    public function allowedDestinationsFor(User $user): Collection
    {
        return $this->allowedDestinations($user, PermissionEnum::Send);
    }

    /**
     * @return Collection<int, Department>
     */
    public function allowedForwardDestinationsFor(User $user): Collection
    {
        return $this->allowedDestinations($user, PermissionEnum::Forward);
    }

    /**
     * @return Collection<int, Department>
     */
    private function allowedDestinations(User $user, PermissionEnum $permission): Collection
    {
        if ($user->isAdmin()) {
            return Department::query()
                ->where('is_active', true)
                ->when($user->department_id, fn ($query) => $query->whereKeyNot($user->department_id))
                ->orderBy('name')
                ->get();
        }

        if (! $user->hasPermission($permission)) {
            return collect();
        }

        $from = $user->department;

        if ($from === null || ! $from->is_active) {
            return collect();
        }

        return DepartmentPermission::query()
            ->with('toDepartment')
            ->where('from_department_id', $from->id)
            ->where('can_send', true)
            ->where('can_receive', true)
            ->whereHas('toDepartment', fn ($query) => $query->where('is_active', true))
            ->get()
            ->pluck('toDepartment')
            ->filter()
            ->sortBy('name')
            ->values();
    }

    private function findPermission(Department $from, Department $to): ?DepartmentPermission
    {
        $cacheKey = $from->id.':'.$to->id;

        if (array_key_exists($cacheKey, $this->permissionCache)) {
            return $this->permissionCache[$cacheKey];
        }

        $this->permissionCache[$cacheKey] = DepartmentPermission::query()
            ->where('from_department_id', $from->id)
            ->where('to_department_id', $to->id)
            ->first();

        return $this->permissionCache[$cacheKey];
    }
}
