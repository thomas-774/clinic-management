# T1-04 — Migration: appointments (with active_slot)

**Phase:** 1 · **Area:** Backend / DB · **Size:** M · **Depends on:** T1-01
**Plan refs:** §5.1 appointments · §5.3 · BR-2

## Steps
- [ ] Columns: `doctor_id` FK, `patient_id` FK, `start_at` DATETIME, `end_at` DATETIME, `status` ENUM('booked','checked_in','completed','cancelled','no_show') default 'booked', `checked_in_at` / `cancelled_at` DATETIME nullable.
- [ ] Generated column `active_slot` DATETIME, `storedAs("CASE WHEN status IN ('booked','checked_in','completed') THEN start_at END")`.
- [ ] UNIQUE (`doctor_id`, `active_slot`).
- [ ] INDEX (`doctor_id`, `start_at`) and INDEX (`patient_id`).

## Done when
- [ ] Inserting two *booked* rows with the same doctor and start time fails.
- [ ] A *cancelled* row and a new *booked* row at the same time can coexist.
