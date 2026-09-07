---
paths:
  - 'app/Http/Controllers/Admin/UserController.php,app/Policies/UserPolicy.php,app/Http/Requests/Admin/StoreUserRequest.php,app/Http/Requests/Admin/UpdateUserRequest.php,app/Http/Requests/Admin/ResetUserPasswordRequest.php,resources/views/admin/accounts/**'
---

# Accounts

## Admin account management: "account" is the URL/route term, User is the model
Admin CRUD for provisioning users lives at UserController (mirrors ExaminationTypeController's index/create/store/edit/update/activate/deactivate shape), gated by UserPolicy -> $user->can('manage-accounts') (the gate already existed in AppServiceProvider, unused until this). Routes/views use "account" (admin.accounts.*, /admin/accounts, route param {account}) matching CLAUDE.md's domain language ("every account is provisioned by an admin") and the existing audit_logs.account_id column — the model and controller class stay `User`/`UserController`.

No hard delete — only is_active toggling via activate/deactivate (restrictOnDelete FKs would block a real delete anyway). Self-lockout guards live in the controller, not the policy: UserController::deactivate() refuses to deactivate $request->user()->id, and update() refuses to change your own account's role away from Admin. Both fail soft (redirect back with a status message), not an exception.

department_id is required only when role=staff (Rule::requiredIf in Store/UpdateUserRequest) — an admin account can have a department (cosmetic) or none. Permission checkboxes (permissions[]) live on the same create/edit form and sync via User::permissions() — they're persisted even if role=admin is later selected in the same submit is prevented; UserController::update() explicitly clears permissions (sync([])) when role becomes admin, since User::hasPermission() already short-circuits true for admins and stale rows would be misleading. Username validation is `regex:/^[a-zA-Z0-9_.-]+$/` (not `alpha_dash`) specifically because the seeded 'rad.staff' username contains a dot, which alpha_dash rejects.

Password reset is a separate action/route/form (admin.accounts.reset-password) on the edit page, not a field on the main update form — keeps the "change identity fields" and "change credential" concerns/requests separate.
