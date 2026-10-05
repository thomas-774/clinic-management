# T1-05 — Migrations: visits and payments

**Phase:** 1 · **Area:** Backend / DB · **Size:** S · **Depends on:** T1-04
**Plan refs:** §5.1 visits, payments · PR-6

## Steps
- [ ] `visits`: `patient_id` FK, `appointment_id` FK nullable UNIQUE, `visit_date` DATE, `work_done` TEXT, `total_amount` DECIMAL(10,2).
- [ ] `payments`: `visit_id` FK (cascade), `amount` DECIMAL(10,2), `method` ENUM('cash','card','wallet') default 'cash', `paid_at` DATETIME; INDEX (`paid_at`).
- [ ] No `remaining` column anywhere; it is always computed (§5.3).

## Done when
- [ ] Every money column is DECIMAL(10,2), never FLOAT.
