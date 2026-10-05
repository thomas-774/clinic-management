# T5-07 — Visits and balances in the profile endpoints

**Phase:** 5 · **Area:** Backend · **Size:** S · **Depends on:** T5-01, T2-02, T2-04
**Plan refs:** FR-C.5, FR-D.5, FR-D.7, FR-B.4

## Steps
- [ ] `GET /doctor/patients/{id}`: fill `visits` (newest first, each with payments, paid, remaining, status) + `outstanding_balance`.
- [ ] `GET /patient/profile`: fill `outstanding_balance` and a list of visits with an unpaid balance (date, remaining). Do **not** expose `work_done` unless the client wants it (confirm).
- [ ] Avoid N+1 queries (eager load + `withSum`).

## Done when
- [ ] Both endpoints show the same outstanding balance for a patient.
