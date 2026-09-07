---
paths:
  - 'app/Services/DashboardStatsService.php,app/Http/Controllers/Department/DashboardController.php,app/Http/Controllers/Department/DepartmentController.php,app/Http/Controllers/Admin/DashboardController.php,resources/views/department/dashboard.blade.php,resources/views/department/index.blade.php,resources/views/admin/dashboard.blade.php'
---

# Dashboards

## Department dashboard (`department.dashboard`)
Staff land here after login. KPI strip **X-RAY TRANSFER** and **Recent Transfers** live on this page only — not on the inbox. Scoped to `$user->department_id` via `DashboardStatsService::departmentTransferStats()` / `departmentRecentTransfers()`.

| KPI | Definition |
| --- | --- |
| New | Inbound recipients with status `pending` or `delivered` |
| Received today | Inbound recipients with `acknowledged_at` today |
| Sent today | Outbound `Transfer` rows where `from_department_id` = dept and `sent_at` today (fallback `created_at` if `sent_at` null) |
| Pending | Inbound recipients with status `pending` only |
| Completed | Inbound recipients with status `completed` |

**Recent Transfers** = latest 10 inbound `TransferRecipient` rows (patient, examination, inbox status).

`department.index` is the filterable inbox list only.

## Admin dashboard (`admin.dashboard`)
Live KPIs via `DashboardStatsService::adminOverview()`:

- **Departments** — active department count
- **Images today** — `images.created_at` today
- **Transfers today** — `transfers.sent_at` today (fallback `created_at`)
- **Failed transfers** — `transfer_recipients` with status `rejected` and `rejected_at` today (no Failed enum; Recalled is not counted)
- **Storage used** — `SUM(images.file_size)` formatted as GB

**Activity** — departments ranked by outbound transfer count today (`from_department_id`).

No mock chart, dept tiles, or fake audit trail on the admin dashboard.
