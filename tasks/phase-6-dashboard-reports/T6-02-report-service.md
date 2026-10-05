# T6-02 — ReportService aggregates and tests

**Phase:** 6 · **Area:** Backend · **Size:** M · **Depends on:** T6-01, T5-01
**Plan refs:** §4.4 PR-4, PR-5 · FR-H.1 – FR-H.4 · §9.1

## Steps
- [ ] `revenue(from, to)` = SUM(payments.amount) WHERE `paid_at` in range (PR-4).
- [ ] `patientsSeen(from, to)` = COUNT DISTINCT patient_id of visits in range whose appointment is completed, or walk-ins (PR-5).
- [ ] `outstanding()` = total remaining across all visits.
- [ ] `payments(from, to)` rows: date, patient, visit total, paid, remaining.
- [ ] `dailyRevenue(month)`: one row per day of the month, with 0 for days with no payments.
- [ ] Tests: an installment paid in November counts in November, not in the visit's month; period edges (23:59 / 00:00).

## Done when
- [ ] ReportService tests pass.
