# T5-07 — Visits and balances in the profile endpoints

**Phase:** 5 · **Area:** Backend · **Size:** S · **Depends on:** T5-01, T2-02, T2-04
**Plan refs:** FR-C.5, FR-D.5, FR-D.7, FR-B.4

## Steps
- [x] `GET /doctor/patients/{id}`: fill `visits` (newest first, each with payments, paid, remaining, status) + `outstanding_balance`.
- [x] `GET /patient/profile`: fill `outstanding_balance` and a list of visits with an unpaid balance (date, remaining). Do **not** expose `work_done` unless the client wants it (confirm).
  - Note: `work_done` is not exposed to the patient (only `unpaid_visits: [{ id, visit_date, remaining }]`). Still to confirm with the client whether patients should see it.
- [x] Avoid N+1 queries (eager load + `withSum`).

## Done when
- [x] Both endpoints show the same outstanding balance for a patient.
