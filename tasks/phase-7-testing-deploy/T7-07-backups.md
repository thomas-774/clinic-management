# T7-07 — Daily database backups

**Phase:** 7 · **Area:** DevOps · **Size:** S · **Depends on:** T7-06
**Plan refs:** §9.2

## Steps
- [ ] Daily `mysqldump` cron (or `spatie/laravel-backup`) to off-server storage (S3 / Backblaze / Google Drive).
- [ ] Keep 14 daily + 4 weekly copies.
- [ ] Test a restore into staging once.
- [ ] `APP_KEY` goes with the backups (T11-06, NFR-S.6): medical text (current illness, history, work done, prescription notes and instructions) is encrypted with it, so a backup without the key cannot be read. Keep production's `APP_KEY` in the clinic's password manager and write in the restore runbook where it is; never store it next to the dumps. The restore test must use the production key and open a patient's history.
- [ ] Key rotation runbook: put the new key in `APP_KEY` and the old one in `APP_PREVIOUS_KEYS` (comma-separated), deploy, run `php artisan clinic:reencrypt`, check a patient page, then remove the old key from `APP_PREVIOUS_KEYS`. Keep the old key with the backups made before the rotation.

## Done when
- [ ] A backup from last night exists off-server and has been restored successfully once.
