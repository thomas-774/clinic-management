# T5-08 — Frontend: MoneyField and VisitForm

**Phase:** 5 · **Area:** Frontend · **Size:** M · **Depends on:** T5-03
**Plan refs:** FR-D.1 – FR-D.4 · §7.3 VisitForm

## Steps
- [x] `MoneyField`: numeric input, 2 decimals, EGP suffix; `utils/formatMoney`.
- [x] `/doctor/visits/new?appointment=:id` (and `?patient=:id` for walk-ins): shows patient name + appointment time.
  - Note: the plan has no "get one appointment" endpoint, so the schedule hands the appointment to the form when navigating (T5-09); after a reload the form finds it in today's schedule. `formatMoney` already existed in `utils/format.js`; money maths uses whole piastres in `utils/money.js`.
- [x] Fields: Work done today (textarea), Total cost, Amount paid now, Method.
- [x] **Remaining** read-only, live `total − paid`, red when > 0; block submit if paid > total.
- [x] On success: toast + go to the patient's details page. The server's numbers are final.

## Done when
- [x] Typing 1500 / 1000 shows 500 in red; saving creates the visit.
