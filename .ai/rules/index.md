# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| app/Http/Controllers/Admin/UserController.php,app/Policies/UserPolicy.php,app/Http/Requests/Admin/StoreUserRequest.php,app/Http/Requests/Admin/UpdateUserRequest.php,app/Http/Requests/Admin/ResetUserPasswordRequest.php,resources/views/admin/accounts/** | .ai/rules/accounts.md |
| app/Services/AuditLogService.php,app/Models/AuditLog.php,app/Enums/AuditAction.php,database/migrations/*audit_logs*,app/Http/Controllers/Admin/AuditLogController.php,resources/views/admin/audit-logs/** | .ai/rules/audit.md |
| app/Console/Commands/BackupDatabaseCommand.php,app/Console/Commands/BackupImagesCommand.php,app/Console/Commands/PruneBackupsCommand.php,app/Services/Backup/**,config/cdh.php,config/filesystems.php,routes/console.php | .ai/rules/backups.md |
| app/Services/ThumbnailGenerator.php,app/Http/Controllers/Image/** | .ai/rules/controllers-image.md |
| app/Services/DashboardStatsService.php,app/Http/Controllers/Department/DepartmentController.php,app/Http/Controllers/Admin/DashboardController.php,resources/views/department/index.blade.php,resources/views/admin/dashboard.blade.php | .ai/rules/dashboards.md |
| app/Http/Controllers/Department/DepartmentController.php,resources/views/department/*.blade.php | .ai/rules/department.md |
| app/Http/Controllers/Admin/DepartmentController.php,app/Http/Controllers/Admin/DepartmentTransferMatrixController.php,app/Policies/DepartmentPolicy.php,app/Http/Requests/Admin/StoreDepartmentRequest.php,app/Http/Requests/Admin/UpdateDepartmentRequest.php,resources/views/admin/departments/** | .ai/rules/departments.md |
| app/Services/TransferLifecycleService.php,app/Models/Transfer.php,app/Models/TransferRecipient.php,app/Enums/TransferStatus.php,app/Enums/TransferRecipientStatus.php | .ai/rules/enums.md |
| resources/css/pages/viewer.css,resources/js/viewer.js,resources/views/examinations/show.blade.php | .ai/rules/examinations.md |
| app/Services/ImageDeletionService.php,app/Console/Commands/PurgeExpiredImagesCommand.php,config/cdh.php,routes/console.php,app/Policies/ImagePolicy.php,app/Http/Controllers/Image/**,app/Http/Requests/Image/DestroyImageRequest.php | .ai/rules/image-deletion.md |
| app/Http/Controllers/Image/**,app/Http/Requests/Image/** | .ai/rules/image.md |
| app/Services/ThumbnailGenerator.php,app/Http/Controllers/Image/**,app/Jobs/GenerateThumbnail.php | .ai/rules/jobs.md |
| app/Models/UploadSession.php,app/Http/Controllers/Image/UploadChunkController.php,app/Enums/UploadSessionStatus.php,app/Console/Commands/PurgeStaleUploadSessionsCommand.php,resources/js/upload.js | .ai/rules/js.md |
| app/Http/Controllers/Image/**,app/Http/Requests/Image/**,database/migrations/*images*,app/Models/Image.php | .ai/rules/models.md |
| app/Services/NotificationService.php,app/Models/Notification.php,app/Http/Controllers/Notification/**,app/View/Components/Layout/Topbar.php,resources/views/layout/topbar.blade.php | .ai/rules/notifications.md |
| app/Http/Controllers/Admin/PermissionController.php,resources/views/admin/permissions/** | .ai/rules/permissions.md |
| app/Http/Controllers/Examination/ExaminationController.php,app/Http/Controllers/Transfer/TransferController.php,app/Policies/TransferPolicy.php | .ai/rules/policies.md |
| app/Services/ImageIngestionService.php,app/Http/Controllers/Image/**,app/Http/Requests/Image/** | .ai/rules/requests-image.md |
| app/Services/SettingsService.php,app/Models/Setting.php,app/Http/Controllers/Admin/SettingsController.php,app/Http/Requests/Admin/UpdateSettingsRequest.php,resources/views/admin/settings/**,database/migrations/*settings* | .ai/rules/settings.md |
| app/Http/Controllers/Image/UploadChunkController.php,app/Services/ImageIngestionService.php,app/Jobs/GenerateThumbnail.php,app/Models/UploadSession.php,resources/js/upload.js,resources/views/images/create.blade.php | .ai/rules/upload-chunks.md |
