---
paths:
  - 'app/Services/AuditLogService.php,app/Models/AuditLog.php,app/Enums/AuditAction.php,database/migrations/*audit_logs*,app/Http/Controllers/Admin/AuditLogController.php,resources/views/admin/audit-logs/**'
---

# Audit logs

## Questionnaire-shaped writers
`audit_logs` columns are `department_id`, `account_id` (users FK), `action`, `subject_type`/`subject_id`, `ip_address`, `user_agent`, `created_at` only — no `updated_at` / old_values. Human writes go through `AuditLogService::record()`; scheduled/system writes use `recordSystem()` (null `account_id` / `department_id`). Actions: uploaded/sent/received/viewed/downloaded/forwarded/recalled/rejected/deleted. Human-readable lines are derived at display time from action + department name, not stored as free text.

## Admin viewer: read-only, no policy class, plain GET filters
`admin.audit-logs.index` (`AuditLogController@index`) is the admin UI for the table above. Gated with `$this->authorize('view-audit-logs')` directly (the `Gate::define`'d ability, no `AuditLogPolicy` class — there's no per-record authorization difference between one log row and another, only a page-level admin check), mirroring how `DepartmentTransferMatrixController` gates itself.

Filters (`department_id`, `action`, `date_range` via the same today/7d/month buckets as `DepartmentController::applyDateRange`, `search` over account name/username) are plain GET params + a real page load — matches `department.index`'s convention, not AJAX. `paginate(50)`, eager-loads `['department', 'account']`. `audit_logs.created_at` has an index (the default sort + date-range filter column) added alongside this viewer since none existed before.

Row text is `{$log->account?->name ?? 'System'} {$log->action->label()}` (`AuditAction::label()` already returns phrases like "uploaded X-ray") plus `class_basename($log->subject_type) . ' #' . $log->subject_id` for the technical subject reference — no morph map is registered, so `subject_type` is the full class name and `class_basename()` is required to shorten it for display. Never render subject-specific details (old_values/new_values don't exist on this table).
