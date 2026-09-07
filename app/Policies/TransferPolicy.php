<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Department;
use App\Models\Transfer;
use App\Models\User;
use App\Services\TransferAuthorizationService;

class TransferPolicy
{
    public function __construct(
        private readonly TransferAuthorizationService $transferAuthorization,
    ) {}

    public function create(User $user, Department $toDepartment): bool
    {
        return $this->transferAuthorization->canUserSendTo($user, $toDepartment);
    }

    public function view(User $user, Transfer $transfer): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->hasPermission(Permission::ViewImages)) {
            return false;
        }

        $departmentId = $user->department_id;

        if ($departmentId === null) {
            return false;
        }

        if ($transfer->from_department_id === $departmentId) {
            return true;
        }

        return $transfer->recipients()
            ->where('department_id', $departmentId)
            ->exists();
    }

    public function forward(User $user, Transfer $transfer, Department $toDepartment): bool
    {
        if (! $this->view($user, $transfer)) {
            return false;
        }

        return $this->transferAuthorization->canUserForward($user, $toDepartment);
    }

    public function acknowledge(User $user, Transfer $transfer): bool
    {
        return $this->canActAsRecipient($user, $transfer);
    }

    public function complete(User $user, Transfer $transfer): bool
    {
        return $this->canActAsRecipient($user, $transfer);
    }

    public function reject(User $user, Transfer $transfer): bool
    {
        return $this->canActAsRecipient($user, $transfer);
    }

    public function recall(User $user, Transfer $transfer): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $departmentId = $user->department_id;

        if ($departmentId === null || $transfer->from_department_id !== $departmentId) {
            return false;
        }

        return $user->hasPermission(Permission::Send) || $user->hasPermission(Permission::Forward);
    }

    private function canActAsRecipient(User $user, Transfer $transfer): bool
    {
        if (! $this->isRecipientDepartment($user, $transfer)) {
            return false;
        }

        $fromDepartment = $transfer->fromDepartment;

        if ($fromDepartment === null) {
            return false;
        }

        return $this->transferAuthorization->canUserReceiveFrom($user, $fromDepartment);
    }

    private function isRecipientDepartment(User $user, Transfer $transfer): bool
    {
        $departmentId = $user->department_id;

        if ($departmentId === null) {
            return false;
        }

        return $transfer->recipients()
            ->where('department_id', $departmentId)
            ->exists();
    }
}
