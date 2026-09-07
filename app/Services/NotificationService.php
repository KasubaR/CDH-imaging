<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\RejectionReason;
use App\Models\Notification;
use App\Models\Transfer;
use App\Models\TransferRecipient;
use App\Models\User;

/**
 * Writes in-app notification rows into the custom `notifications` table.
 * No SMS, WhatsApp, email, or Laravel notification channels — Phase 19 is in-app only.
 */
class NotificationService
{
    public function notifyTransferReceived(Transfer $transfer): void
    {
        $transfer->loadMissing([
            'examination.patient',
            'examination.examinationType',
            'fromDepartment',
            'recipients',
        ]);

        $departmentIds = $transfer->recipients->pluck('department_id')->unique()->values()->all();

        if ($departmentIds === []) {
            return;
        }

        $patientName = $transfer->examination?->patient?->patient_name ?? 'Unknown patient';
        $examinationName = $transfer->examination?->examinationType?->name ?? 'Unknown examination';
        $fromDepartment = $transfer->fromDepartment?->name ?? 'Unknown department';

        $title = NotificationType::TransferReceived->label();
        $body = "Patient: {$patientName}\nExamination: {$examinationName}\nFrom: {$fromDepartment}";
        $data = [
            'transfer_id' => $transfer->id,
            'examination_id' => $transfer->examination_id,
            'from_department_id' => $transfer->from_department_id,
        ];

        $this->fanOut(
            departmentIds: $departmentIds,
            excludeUserId: $transfer->sent_by,
            type: NotificationType::TransferReceived,
            title: $title,
            body: $body,
            data: $data,
        );
    }

    public function notifyTransferRejected(Transfer $transfer, TransferRecipient $recipient): void
    {
        $transfer->loadMissing([
            'examination.patient',
            'examination.examinationType',
            'fromDepartment',
        ]);

        if ($transfer->from_department_id === null) {
            return;
        }

        $patientName = $transfer->examination?->patient?->patient_name ?? 'Unknown patient';
        $examinationName = $transfer->examination?->examinationType?->name ?? 'Unknown examination';
        $reason = RejectionReason::tryFrom((string) $recipient->rejection_reason)?->label()
            ?? ($recipient->rejection_reason ?: 'Unspecified');

        $title = NotificationType::TransferRejected->label();
        $body = "Patient: {$patientName}\nExamination: {$examinationName}\nReason: {$reason}";
        $data = [
            'transfer_id' => $transfer->id,
            'examination_id' => $transfer->examination_id,
            'transfer_recipient_id' => $recipient->id,
        ];

        $this->fanOut(
            departmentIds: [$transfer->from_department_id],
            excludeUserId: null,
            type: NotificationType::TransferRejected,
            title: $title,
            body: $body,
            data: $data,
        );
    }

    public function notifyTransferRecalled(Transfer $transfer, TransferRecipient $recipient): void
    {
        $transfer->loadMissing([
            'examination.patient',
            'examination.examinationType',
            'fromDepartment',
        ]);

        $patientName = $transfer->examination?->patient?->patient_name ?? 'Unknown patient';
        $examinationName = $transfer->examination?->examinationType?->name ?? 'Unknown examination';
        $fromDepartment = $transfer->fromDepartment?->name ?? 'Unknown department';

        $title = NotificationType::TransferRecalled->label();
        $body = "Patient: {$patientName}\nExamination: {$examinationName}\nFrom: {$fromDepartment}";
        $data = [
            'transfer_id' => $transfer->id,
            'examination_id' => $transfer->examination_id,
            'transfer_recipient_id' => $recipient->id,
        ];

        $this->fanOut(
            departmentIds: [$recipient->department_id],
            excludeUserId: $transfer->sent_by,
            type: NotificationType::TransferRecalled,
            title: $title,
            body: $body,
            data: $data,
        );
    }

    /**
     * @param  list<int>  $departmentIds
     * @param  array<string, mixed>  $data
     */
    private function fanOut(
        array $departmentIds,
        ?int $excludeUserId,
        NotificationType $type,
        string $title,
        string $body,
        array $data,
    ): void {
        $query = User::query()
            ->whereIn('department_id', $departmentIds)
            ->where('is_active', true);

        if ($excludeUserId !== null) {
            $query->whereKeyNot($excludeUserId);
        }

        foreach ($query->get(['id']) as $user) {
            Notification::query()->create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'read_at' => null,
            ]);
        }
    }
}
