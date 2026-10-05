# T9-12 — Frontend: prescriptions on PatientDetails and visits

**Phase:** 9 · **Area:** Frontend · **Size:** S · **Depends on:** T9-10, T9-11
**Plan refs:** FR-J.7

## Steps
- [ ] PatientDetails: a "Prescriptions" card (date, drug names, linked visit date) with Open/Edit, Reprint and Delete (ConfirmDialog), and a "Write prescription" button.
- [ ] VisitTimeline: "Write prescription" on each visit (passes `?visit=`) and a small badge when a visit has prescriptions.
- [ ] After saving a VisitForm, offer "Write prescription for this visit".
- [ ] Empty / loading / error states via `QueryState`.

## Done when
- [ ] Vitest: the card lists prescriptions; Reprint opens the print route; the visit button links with `?visit=`.
