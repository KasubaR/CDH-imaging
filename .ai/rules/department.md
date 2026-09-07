---
paths:
  - 'app/Http/Controllers/Department/DepartmentController.php,resources/views/department/*.blade.php'
---

# Department

## Department inbox is TransferRecipient rows, not Examination rows
department.index queries TransferRecipient::where('department_id', $user->department_id) — one row per (transfer, department), not one per examination — and is always scoped to the current user's own department (no cross-department browsing; a user with no department_id sees an empty inbox). Status column/filter uses inbox wording (New/Received/Viewed/Downloaded/Completed/Rejected) via TransferRecipientStatus::inboxLabel()/badgeVariant() — "New" collapses both Pending and Delivered.

Filters: "Department" = the examination's referring_department_id (home dept); "Sender" = transfer.from_department_id (who sent this particular transfer — can differ from home dept once forwarding exists). Both are plain GET query params handled in DepartmentController::applyFilters(); there's no live/AJAX filtering, just a `<form method="get">` and a real page load, matching PatientController::index's pattern (see [[transfer-lifecycle-synchronous-delivery-forward-only-per-recipient-events]], [[acknowledgement-is-mandatory-before-opening-a-transfer]]).
