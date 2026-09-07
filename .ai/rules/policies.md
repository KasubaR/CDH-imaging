---
paths:
  - 'app/Http/Controllers/Examination/ExaminationController.php,app/Http/Controllers/Transfer/TransferController.php,app/Policies/TransferPolicy.php'
---

# Policies

## Acknowledgement is mandatory before opening a transfer
ExaminationController::show() calls TransferLifecycleService::departmentMustAcknowledge() before anything else: if the viewer's department is a transfer recipient (not the referring/owning department) and hasn't acknowledged receipt yet, it redirects to department.index instead of rendering — Viewed is never marked in that case. So a recipient must POST transfers.acknowledge first; only then can they open the exam (which then marks Viewed) or download.

TransferController also exposes transfers.reject (TransferPolicy::reject, same recipient-department check as acknowledge/complete) with a required RejectionReason enum — a terminal branch alongside Completed/Recalled. transfers.recall (TransferPolicy::recall, sending department only) is allowed only before Acknowledged. transfers.forward creates a new Transfer on the same Examination. See [[transfer-lifecycle-synchronous-delivery-forward-only-per-recipient-events]] for the rest of the state machine.
