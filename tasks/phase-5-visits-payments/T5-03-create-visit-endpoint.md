# T5-03 — POST /doctor/visits (visit + first payment)

**Phase:** 5 · **Area:** Backend · **Size:** M · **Depends on:** T5-01, T4-04
**Plan refs:** FR-D.1 – FR-D.5 · §6.4

## Steps
- [x] `StoreVisitRequest`: `patient_id`, `appointment_id` (nullable; must belong to that patient, be checked_in or booked, and have no visit yet), `work_done`, `total_amount` ≥ 0, `paid_now` 0…total, `method`.
- [x] In one transaction: create the Visit (`visit_date` = today), create a Payment if `paid_now > 0`, set the appointment to `completed`.
- [x] `VisitResource`: id, date, work_done, total_amount, paid, remaining, payment_status, payments[].
- [x] Returns 201 with the shape in §6.4.
  - Note: amounts come back as 2-decimal strings (`"500.00"`), like `outstanding_balance` elsewhere in the API, so no float rounding can creep in (PR-6). A visit for a still-booked appointment checks it in first, then completes it, keeping the §4.3 lifecycle.

## Done when
- [x] The §6.4 example returns `remaining: 500.00, payment_status: "partially_paid"` and the appointment is completed.
