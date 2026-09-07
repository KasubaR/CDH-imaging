---
paths:
  - 'app/Http/Controllers/Admin/DepartmentController.php,app/Http/Controllers/Admin/DepartmentTransferMatrixController.php,app/Policies/DepartmentPolicy.php,app/Http/Requests/Admin/StoreDepartmentRequest.php,app/Http/Requests/Admin/UpdateDepartmentRequest.php,resources/views/admin/departments/**'
---

# Departments

## Department admin CRUD vs. the transfer matrix are two separate controllers
Admin\DepartmentController (mirrors ExaminationTypeController/UserController: index/create/store/edit/update/activate/deactivate, gated by DepartmentPolicy -> manage-departments) only edits Department's own columns (name, code, can_send, can_receive, is_active).

Department.can_send/can_receive are NOT what TransferAuthorizationService checks — they're currently dead/unused by any authorization logic (grep confirms nothing reads them outside the model/factory). The routing TransferAuthorizationService actually enforces is the per-pair App\Models\DepartmentPermission (from_department_id, to_department_id, can_send, can_receive), edited separately at admin.departments.matrix / DepartmentTransferMatrixController (also gated by the manage-departments gate directly, no policy class — it's a page-level grid, not a single-record resource). The matrix view renders one checkbox per (from,to) ordered pair (self-pairs skipped); update() always sets both can_send+can_receive true together on check, and deletes the row entirely on uncheck (never leaves a false/false row lying around) — iterates only over real active Department ids server-side, so it never trusts client-submitted ids. If you need to change routing rules, use the matrix — editing Department.can_send/can_receive alone will have no effect on who can actually send/receive.
