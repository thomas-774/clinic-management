# T5-08 — Frontend: MoneyField and VisitForm

**Phase:** 5 · **Area:** Frontend · **Size:** M · **Depends on:** T5-03
**Plan refs:** FR-D.1 – FR-D.4 · §7.3 VisitForm

## Steps
- [ ] `MoneyField`: numeric input, 2 decimals, EGP suffix; `utils/formatMoney`.
- [ ] `/doctor/visits/new?appointment=:id` (and `?patient=:id` for walk-ins): shows patient name + appointment time.
- [ ] Fields: Work done today (textarea), Total cost, Amount paid now, Method.
- [ ] **Remaining** read-only, live `total − paid`, red when > 0; block submit if paid > total.
- [ ] On success: toast + go to the patient's details page. The server's numbers are final.

## Done when
- [ ] Typing 1500 / 1000 shows 500 in red; saving creates the visit.
