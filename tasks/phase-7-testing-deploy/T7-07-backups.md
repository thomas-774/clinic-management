# T7-07 — Daily database backups

**Phase:** 7 · **Area:** DevOps · **Size:** S · **Depends on:** T7-06
**Plan refs:** §9.2

## Steps
- [ ] Daily `mysqldump` cron (or `spatie/laravel-backup`) to off-server storage (S3 / Backblaze / Google Drive).
- [ ] Keep 14 daily + 4 weekly copies.
- [ ] Test a restore into staging once.

## Done when
- [ ] A backup from last night exists off-server and has been restored successfully once.
