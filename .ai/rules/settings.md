---
paths:
  - 'app/Services/SettingsService.php,app/Models/Setting.php,app/Http/Controllers/Admin/SettingsController.php,app/Http/Requests/Admin/UpdateSettingsRequest.php,resources/views/admin/settings/**,database/migrations/*settings*'
---

# Settings

## SettingsService: DB-backed overrides for exactly 4 config('cdh.*') knobs
SettingsService is NOT a generic feature-flag system — it only manages 4 fixed keys (image_retention_months, image_max_bytes, upload_session_ttl_hours, backup_retention_days), each mapped to a config('cdh.*') fallback path in its private KEYS const. `get(key)` reads the `settings` table (key/value, no row = "unset") and falls back to config() when no row exists; `set(key, value)` upserts. Both throw InvalidArgumentException for an unlisted key — there's no way to accidentally manage a 5th setting without editing the service.

Every reader of these 4 values must go through SettingsService, never `config('cdh.image_max_bytes')` etc. directly — see the updated note in requests-image.md. Everything else in config/cdh.php (backup file paths, the encryption key, upload_chunk_max_bytes) stays env-only on purpose; don't add them to SettingsService without deciding that's actually wanted.

admin.settings.edit/update (SettingsController) is gated with `$this->authorize('manage-settings')` directly (no policy class — same page-level-gate pattern as AuditLogController/DepartmentTransferMatrixController/PermissionController). The form edits image_max_bytes as MB (`image_max_mb`) for admin convenience — UpdateSettingsRequest validates the MB value, the controller multiplies by 1024*1024 before calling `$settings->set('image_max_bytes', ...)`. Storage-used display reuses `DashboardStatsService::adminOverview()['storage_used']` rather than recomputing it.
