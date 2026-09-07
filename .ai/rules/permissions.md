---
paths:
  - 'app/Http/Controllers/Admin/PermissionController.php,resources/views/admin/permissions/**'
---

# Permissions

## Permissions overview is read-only — assignment stays on the account edit form
admin.permissions.index (PermissionController@index) is a users × permissions read-only grid, gated with `$this->authorize('manage-permissions')` directly (no policy class, same page-level-gate pattern as AuditLogController and DepartmentTransferMatrixController). It does NOT let you change anything — every row links to admin.accounts.edit, which is the one place permissions[] is actually synced (UserController::update()).

Admin accounts must be special-cased: their `permissions` pivot is always empty (cleared on save — see accounts.md) since User::hasPermission() short-circuits true for admins regardless of pivot rows. Rendering an admin row by checking pivot membership would incorrectly show them as holding nothing — the view instead checks `$account->isAdmin()` first and renders "All permissions (administrator)" (a colspan cell in the desktop table) instead of the per-permission checkmark columns.
