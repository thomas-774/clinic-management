# T1-03 — Migrations: doctor_settings, working_hours, blocked_times

**Phase:** 1 · **Area:** Backend / DB · **Size:** S · **Depends on:** T1-01
**Plan refs:** §5.1 · Module G

## Steps
- [x] `doctor_settings`: `doctor_id` FK UNIQUE, `slot_duration_minutes` SMALLINT default 45, `booking_window_days` SMALLINT default 30, `cancel_cutoff_hours` SMALLINT default 2.
- [x] `working_hours` (one row per time range, several per day allowed, no rows = day off): `doctor_id` FK, `day_of_week` TINYINT (0=Sun … 6=Sat), `start_time` TIME, `end_time` TIME; INDEX (`doctor_id`, `day_of_week`), **no** unique constraint.
- [x] `blocked_times`: `doctor_id` FK, `date` DATE, `start_time` / `end_time` TIME nullable (both NULL = whole day), `reason` VARCHAR(150) nullable; INDEX (`doctor_id`, `date`).

## Done when
- [x] All three tables exist with their FKs and indexes.
