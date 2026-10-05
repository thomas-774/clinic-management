# T8-03 — Who recorded each payment

**Phase:** 8 · **Area:** Backend + Frontend · **Size:** S · **Depends on:** T8-01
**Plan refs:** §5.1 payments · FR-I.5 · FR-H.3

## Steps
- [x] Migration: `payments.recorded_by` nullable FK → users (null on delete).
- [x] `Payment::recordedBy()` relation; `PaymentService::addPayment()` takes the recording user; `VisitController@store` sets it for `paid_now`.
- [x] `GET /doctor/reports/payments` returns `recorded_by_name`.
- [x] The Reports payments table shows a "Recorded by" column (ar + en keys).

## Done when
- [x] Tests: a doctor payment stores the doctor's id; the report row includes the name; old rows with null show "—".
