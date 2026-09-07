---
paths:
  - 'app/Console/Commands/BackupDatabaseCommand.php,app/Console/Commands/BackupImagesCommand.php,app/Console/Commands/PruneBackupsCommand.php,app/Services/Backup/**,config/cdh.php,config/filesystems.php,routes/console.php'
---

# Encrypted backups

## Two separate strategies (Phase 31)
MySQL dump (`backup:database`) does **not** include X-ray binaries. Image backup (`backup:images`) archives only `xrays/originals` and `xrays/thumbnails` on the `local` disk — never `xrays/chunks/`.

Both write encrypted `.enc` files to the `backups` disk (`storage/app/private/backups/{database,images}/`) using AES-256-CBC via `App\Services\Backup\BackupEncryption` and `BACKUP_ENCRYPTION_KEY`. Plaintext temp files under `storage/app/private/backups-tmp/` must be deleted after each run.

## Schedule
- `backup:database` daily 01:00
- `backup:images` daily 01:30
- `backup:prune` daily 02:00 (retention: `cdh.backup.retention_days`, default 14)

Production needs `schedule:run` cron (and a queue worker for uploads); local `composer run dev` does not replace that.
