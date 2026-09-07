<?php

namespace App\Policies;

use App\Enums\Permission as PermissionEnum;
use App\Enums\TransferRecipientStatus;
use App\Models\Examination;
use App\Models\Image;
use App\Models\Transfer;
use App\Models\User;

class ImagePolicy
{
    public function view(User $user, Image $image): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->hasPermission(PermissionEnum::ViewImages)) {
            return false;
        }

        return $this->userCanAccessExamination($user, $image->examination);
    }

    public function download(User $user, Image $image): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->hasPermission(PermissionEnum::Download)) {
            return false;
        }

        return $this->userCanAccessExamination($user, $image->examination);
    }

    public function upload(User $user, Examination $examination): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->hasPermission(PermissionEnum::Upload)) {
            return false;
        }

        return $this->userCanAccessExamination($user, $examination);
    }

    public function delete(User $user, Image $image): bool
    {
        return $user->isAdmin();
    }

    private function userCanAccessExamination(User $user, ?Examination $examination): bool
    {
        if ($examination === null) {
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
                    ->orWhereHas(
                        'recipients',
                        fn ($recipientQuery) => $recipientQuery
                            ->where('department_id', $departmentId)
                            ->whereNotIn('status', [
                                TransferRecipientStatus::Rejected->value,
                                TransferRecipientStatus::Recalled->value,
                            ]),
                    );
            })
            ->exists();
    }
}
