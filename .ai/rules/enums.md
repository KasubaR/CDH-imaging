---
paths:
  - 'app/Services/TransferLifecycleService.php,app/Models/Transfer.php,app/Models/TransferRecipient.php,app/Enums/TransferStatus.php,app/Enums/TransferRecipientStatus.php'
---

# Enums

## Transfer lifecycle: synchronous delivery, forward-only per-recipient events
UPLOADED/VALIDATED aren't modeled as states — an Image row existing (post verifyGenuineImage()) already implies both. From SENT onward, `App\Enums\TransferStatus` (transfers.status) is Sent/Delivered/Completed, and `App\Enums\TransferRecipientStatus` (transfer_recipients.status) is the per-department Pending→Delivered→Acknowledged→Viewed→Downloaded→Completed pipeline, with terminal branches Rejected (any non-terminal stage) and Recalled (only before Acknowledged). All driven through `App\Services\TransferLifecycleService` — don't write to these status/timestamp columns directly.

Delivery is synchronous (no queue between departments): `send()` marks every recipient Delivered in the same request. `forward()` creates a new Transfer on the same Examination (same Image rows — never copies files) and audits as Forwarded. Viewed is tracked automatically in `ExaminationController::show()`; Downloaded automatically in `ImageController::download()`. Acknowledge/Complete/Reject/Recall are explicit user actions. `complete()` requires the recipient already reached Viewed. Recall is blocked once acknowledged — the recipient must Reject instead (structured `RejectionReason` enum). Rejected/Recalled recipient rows no longer grant image access.

Each event only stamps its own timestamp column and never regresses/backfills a skipped stage — status always reflects the furthest stage actually reached, and Viewed/Downloaded are intentionally recorded as separate, independent events. The parent Transfer rolls up automatically (`syncTransferAggregate`): delivered_at once every recipient is delivered, completed_at once every recipient is terminal (Completed, Rejected, or Recalled).
