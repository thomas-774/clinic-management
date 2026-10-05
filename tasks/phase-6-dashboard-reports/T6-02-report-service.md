# T6-02 — ReportService aggregates and tests

**Phase:** 6 · **Area:** Backend · **Size:** M · **Depends on:** T6-01, T5-01
**Plan refs:** §4.4 PR-4, PR-5 · FR-H.1 – FR-H.4 · §9.1

## Steps
- [x] `revenue(from, to)` = SUM(payments.amount) WHERE `paid_at` in range (PR-4).
- [x] `patientsSeen(from, to)` = COUNT DISTINCT patient_id of visits in range whose appointment is completed, or walk-ins (PR-5).
- [x] `outstanding()` = total remaining across all visits.
  - Note: computed as SUM(total_amount) − SUM(payments.amount); this is exact because PR-2 stops payments going over a visit's total.
- [x] `payments(from, to)` rows: date, patient, visit total, paid, remaining.
  - Note: one row per payment: `paid` = that payment, `remaining` = what the visit still owes now. `payments()` returns a query (for pagination) and `paymentRow()` maps each payment.
- [x] `dailyRevenue(month)`: one row per day of the month, with 0 for days with no payments.
- [x] Tests: an installment paid in November counts in November, not in the visit's month; period edges (23:59 / 00:00).

## Done when
- [x] ReportService tests pass.
