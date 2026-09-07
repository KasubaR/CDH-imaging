---
paths:
  - 'app/Enums/UserRole.php,app/Models/User.php,app/Policies/ImagePolicy.php,app/Policies/ExaminationPolicy.php,app/Http/Controllers/Admin/UserController.php'
---

# Admin

## UserRole::Moic — cross-department image viewer, not a fourth admin
Three roles now: Admin, Staff, Moic ("Medical Officer in Charge"). Moic is deliberately NOT a blanket bypass like Admin — it still needs ViewImages (and usually Download) explicitly assigned via the normal permissions[] pivot on the account edit form, exactly like Staff. What it gets instead of a permission is a *scope* change: `User::canViewAllDepartments()` (true for Admin OR Moic) is checked in ImagePolicy::view()/download() and ExaminationPolicy::view() AFTER the hasPermission() check, to skip the usual referring-department/transfer-participation restriction (`userCanAccessExamination()`). Moic gets none of Admin's other implicit bypasses — no upload/send/receive/forward, no delete-images, no manage-* gates (those all still check isAdmin() only, unchanged) — so it never sees Accounts/Departments/Audit Log/Permissions/Settings, and PermissionController's "All permissions (administrator)" special-case does NOT apply to it (its pivot rows are real and rendered normally).

department_id is optional for Moic just like Admin (UserController's syncsPermissions() helper — `! $account->isAdmin()` — decides who gets permissions synced vs cleared; Moic falls on the "synced" side alongside Staff). Because Moic has no department, `User::canAccessDepartment()` had to be widened to `canViewAllDepartments()` too — EnsureAccountIsActive middleware calls it on every request and was logging Moic out immediately before this fix. The self-lockout guard in UserController::update() blocks an admin from changing their own role to *any* non-Admin value now (was Staff-only before Moic existed).
