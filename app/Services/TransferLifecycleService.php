<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\TransferRecipientStatus;
use App\Enums\TransferStatus;
use App\Models\Examination;
use App\Models\Transfer;
use App\Models\TransferRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Drives the transfer lifecycle:
 *
 *   UPLOADED -> VALIDATED -> SENT -> DELIVERED -> ACKNOWLEDGED -> VIEWED -> DOWNLOADED -> COMPLETED
 *                                          \-> REJECTED (branch, from any non-terminal stage)
 *                                          \-> RECALLED (branch, only before Acknowledged)
 *
 * UPLOADED/VALIDATED precede a Transfer entirely (an Image row existing, having passed
 * ImageController::verifyGenuineImage(), already implies both — see .ai/rules/image.md). Everything
 * from SENT onward is tracked here: SENT/DELIVERED on the Transfer itself, and
 * ACKNOWLEDGED/VIEWED/DOWNLOADED/COMPLETED/REJECTED/RECALLED per TransferRecipient (one department
 * can lag or race another). A recipient's status only ever moves forward, and each stage's
 * timestamp is recorded only when that stage's real event happens — nothing is inferred or
 * backfilled from a later one.
 *
 * Acknowledging is mandatory: ExaminationController::show() calls departmentMustAcknowledge()
 * before markViewed() ever runs, so a recipient department must acknowledge receipt before it can
 * open the transfer. acknowledged_at is the "received_at" of the acknowledgement questionnaire;
 * received_by additionally records who acknowledged it.
 *
 * Forwarding creates a new Transfer on the same Examination (same Image rows) — never copies files.
 */
class TransferLifecycleService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AuditLogService $auditLog,
    ) {
        //
    }

    /**
     * Send an examination to one or more departments. Delivery is synchronous — there is no queue
     * between departments in this system — so every recipient is marked Delivered immediately.
     *
     * @param  list<int>  $toDepartmentIds
     */
    public function send(
        Examination $examination,
        User $sender,
        array $toDepartmentIds,
        ?string $message = null,
        AuditAction $auditAction = AuditAction::Sent,
    ): Transfer {
        if ($sender->department_id === null) {
            throw new RuntimeException('The sender must have an assigned department to send a transfer.');
        }

        return DB::transaction(function () use ($examination, $sender, $toDepartmentIds, $message, $auditAction): Transfer {
            $transfer = Transfer::query()->create([
                'examination_id' => $examination->id,
                'from_department_id' => $sender->department_id,
                'sent_by' => $sender->id,
                'message' => $message,
                'status' => TransferStatus::Sent,
                'sent_at' => now(),
            ]);

            foreach (array_unique($toDepartmentIds) as $departmentId) {
                $recipient = $transfer->recipients()->create([
                    'department_id' => $departmentId,
                    'status' => TransferRecipientStatus::Pending,
                ]);

                $this->recordEvent($recipient, TransferRecipientStatus::Delivered);
            }

            $transfer = $transfer->fresh('recipients');

            $this->notifications->notifyTransferReceived($transfer);
            $this->auditLog->record($sender, $auditAction, $transfer);

            return $transfer;
        });
    }

    /**
     * A recipient department acknowledges receipt. Mandatory before it can view the transfer (see
     * departmentMustAcknowledge()). Records both when (acknowledged_at, via recordEvent()) and who
     * (received_by) — filled in once and never overwritten by a later re-acknowledge.
     */
    public function acknowledge(TransferRecipient $recipient, User $receivedBy): TransferRecipient
    {
        $recipient = $this->recordEvent($recipient, TransferRecipientStatus::Acknowledged);

        if ($recipient->acknowledged_at !== null && $recipient->received_by === null) {
            $recipient->update(['received_by' => $receivedBy->id]);
            $recipient = $recipient->fresh();
        }

        $this->auditLog->record($receivedBy, AuditAction::Received, $recipient);

        return $recipient;
    }

    /**
     * Forward an examination onward from a department that already holds it. Creates a new
     * Transfer on the same Examination (same Image rows) — never copies files.
     *
     * @param  list<int>  $toDepartmentIds
     */
    public function forward(Transfer $original, User $sender, array $toDepartmentIds, ?string $message = null): Transfer
    {
        $original->loadMissing('examination');

        return $this->send(
            examination: $original->examination,
            sender: $sender,
            toDepartmentIds: $toDepartmentIds,
            message: $message,
            auditAction: AuditAction::Forwarded,
        );
    }

    /**
     * A recipient department refuses the transfer — e.g. wrong destination or wrong patient.
     * A branch, not a further pipeline stage: only reachable from a non-terminal status, and once
     * Rejected a recipient cannot be acknowledged/viewed/downloaded/completed afterward.
     */
    public function reject(TransferRecipient $recipient, User $rejectedBy, ?string $reason = null): TransferRecipient
    {
        if ($recipient->status->isTerminal()) {
            throw new RuntimeException('This transfer has already been resolved and cannot be rejected.');
        }

        $recipient->update([
            'status' => TransferRecipientStatus::Rejected,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $transfer = $recipient->transfer()->firstOrFail();
        $this->syncTransferAggregate($transfer);

        $recipient = $recipient->fresh();

        $this->notifications->notifyTransferRejected(
            $transfer->fresh(['fromDepartment', 'examination.patient', 'examination.examinationType']),
            $recipient,
        );
        $this->auditLog->record($rejectedBy, AuditAction::Rejected, $recipient);

        return $recipient;
    }

    /**
     * Sender recalls a transfer before the destination has acknowledged it. After acknowledgement
     * the recipient must Reject instead — recall is intentionally unavailable then.
     */
    public function recall(TransferRecipient $recipient, User $recalledBy): TransferRecipient
    {
        if ($recipient->status->isTerminal()) {
            throw new RuntimeException('This transfer has already been resolved and cannot be recalled.');
        }

        if ($recipient->status->order() >= TransferRecipientStatus::Acknowledged->order()) {
            throw new RuntimeException('This transfer has already been acknowledged and cannot be recalled.');
        }

        $recipient->update([
            'status' => TransferRecipientStatus::Recalled,
            'recalled_at' => now(),
        ]);

        $transfer = $recipient->transfer()->firstOrFail();
        $this->syncTransferAggregate($transfer);

        $recipient = $recipient->fresh();

        $this->notifications->notifyTransferRecalled(
            $transfer->fresh(['fromDepartment', 'examination.patient', 'examination.examinationType']),
            $recipient,
        );
        $this->auditLog->record($recalledBy, AuditAction::Recalled, $recipient);

        return $recipient;
    }

    /**
     * Acknowledging is mandatory, so viewing only registers once the recipient has acknowledged —
     * a no-op otherwise (the real gate is departmentMustAcknowledge(), checked by
     * ExaminationController::show() before this is ever reached in the normal flow).
     */
    public function markViewed(TransferRecipient $recipient): TransferRecipient
    {
        if ($recipient->status->order() < TransferRecipientStatus::Acknowledged->order()) {
            return $recipient;
        }

        return $this->recordEvent($recipient, TransferRecipientStatus::Viewed);
    }

    public function markDownloaded(TransferRecipient $recipient): TransferRecipient
    {
        return $this->recordEvent($recipient, TransferRecipientStatus::Downloaded);
    }

    /**
     * A department closes out its copy of the transfer. Requires the recipient to have at least
     * viewed it first — downloading a file is optional (the viewer displays images inline), but
     * closing out something never opened would misrepresent the audit trail.
     */
    public function complete(TransferRecipient $recipient): TransferRecipient
    {
        if ($recipient->status->isTerminal() && $recipient->status !== TransferRecipientStatus::Completed) {
            throw new RuntimeException('A rejected or recalled transfer cannot be marked complete.');
        }

        if ($recipient->status->order() < TransferRecipientStatus::Viewed->order()) {
            throw new RuntimeException('The transfer must be viewed before it can be marked complete.');
        }

        return $this->recordEvent($recipient, TransferRecipientStatus::Completed);
    }

    /**
     * Whether `$user`'s department must acknowledge receipt before it may open this examination:
     * true only when the department is an actual recipient (not the referring/owning department)
     * and every recipient row it holds for this examination is still short of Acknowledged.
     */
    public function departmentMustAcknowledge(Examination $examination, User $user): bool
    {
        if ($user->department_id === null || $examination->referring_department_id === $user->department_id) {
            return false;
        }

        $recipients = $this->recipientsFor($examination, $user);

        if ($recipients->isEmpty()) {
            return false;
        }

        return $recipients->every(
            fn (TransferRecipient $recipient) => $recipient->status->order() < TransferRecipientStatus::Acknowledged->order(),
        );
    }

    /**
     * Mark every TransferRecipient row belonging to `$user`'s department, for transfers of this
     * examination, as Viewed. A no-op for the sending department (it holds no recipient row) and
     * for a user with no department.
     */
    public function trackExaminationViewed(Examination $examination, User $user): void
    {
        $this->recipientsFor($examination, $user)->each(
            fn (TransferRecipient $recipient) => $this->markViewed($recipient),
        );
    }

    /**
     * Mark every TransferRecipient row belonging to `$user`'s department, for transfers carrying
     * this image's examination, as Downloaded.
     */
    public function trackImageDownloaded(Examination $examination, User $user): void
    {
        $this->recipientsFor($examination, $user)->each(
            fn (TransferRecipient $recipient) => $this->markDownloaded($recipient),
        );
    }

    /**
     * @return Collection<int, TransferRecipient>
     */
    private function recipientsFor(Examination $examination, User $user): Collection
    {
        if ($user->department_id === null) {
            return new Collection;
        }

        return TransferRecipient::query()
            ->where('department_id', $user->department_id)
            ->whereNotIn('status', [
                TransferRecipientStatus::Rejected,
                TransferRecipientStatus::Recalled,
            ])
            ->whereHas('transfer', fn ($query) => $query->where('examination_id', $examination->id))
            ->get();
    }

    /**
     * Record that `$stage` happened for `$recipient`: stamp its timestamp column (only if not
     * already set — never overwrite a real event) and advance `status` if this stage is further
     * along than where the recipient already was. Never regresses and never touches any other
     * stage's column.
     */
    private function recordEvent(TransferRecipient $recipient, TransferRecipientStatus $stage): TransferRecipient
    {
        if ($recipient->status->isTerminal()) {
            return $recipient;
        }

        $attributes = [];

        $column = $stage->timestampColumn();
        if ($column !== null && $recipient->{$column} === null) {
            $attributes[$column] = now();
        }

        if ($stage->order() > $recipient->status->order()) {
            $attributes['status'] = $stage;
        }

        if ($attributes !== []) {
            $recipient->update($attributes);
        }

        $this->syncTransferAggregate($recipient->transfer()->firstOrFail());

        return $recipient->fresh();
    }

    /**
     * Roll per-recipient progress up into the parent Transfer: it becomes Delivered once every
     * recipient has been delivered to, and Completed once every recipient has reached a terminal
     * status (Completed, Rejected, or Recalled — the sender's job is done either way). Each
     * transition fires at most once (guarded by the corresponding *_at column already being null).
     */
    private function syncTransferAggregate(Transfer $transfer): void
    {
        /** @var Collection<int, TransferRecipient> $recipients */
        $recipients = $transfer->recipients()->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $attributes = [];

        if ($transfer->delivered_at === null && $recipients->every(fn (TransferRecipient $r) => $r->delivered_at !== null)) {
            $attributes['delivered_at'] = now();
            $attributes['status'] = TransferStatus::Delivered;
        }

        if ($transfer->completed_at === null && $recipients->every(fn (TransferRecipient $r) => $r->status->isTerminal())) {
            $attributes['completed_at'] = now();
            $attributes['status'] = TransferStatus::Completed;
        }

        if ($attributes !== []) {
            $transfer->update($attributes);
        }
    }
}
