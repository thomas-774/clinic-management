# T9-12 — Frontend: prescriptions on PatientDetails and visits

**Phase:** 9 · **Area:** Frontend · **Size:** S · **Depends on:** T9-10, T9-11
**Plan refs:** FR-J.7

## Steps
- [x] PatientDetails: a "Prescriptions" card (date, drug names, linked visit date) with Open/Edit, Reprint and Delete (ConfirmDialog), and a "Write prescription" button.
  Note: `pages/doctor/PatientPrescriptions.jsx`, under the Visits card. Open / Edit goes to the edit form, Reprint to the print page. A delete shows a toast and refreshes the list and the patient.
- [x] VisitTimeline: "Write prescription" on each visit (passes `?visit=`) and a small badge when a visit has prescriptions.
  Note: both come from new optional props (`patientId`, `prescriptionCounts`), so the assistant's patient page, which shares VisitTimeline, shows neither (FR-J.7). The counts come from the patient's prescriptions list.
- [x] After saving a VisitForm, offer "Write prescription for this visit".
  Note: VisitForm passes the new visit's id in the navigation state; PatientDetails then shows a banner with the link (`?visit=`) and a "Not now" button.
- [x] Empty / loading / error states via `QueryState`.
  Note: the list is only requested when the patient's `prescriptions_count` is above 0; otherwise the card shows "No prescriptions yet." straight away.

## Done when
- [x] Vitest: the card lists prescriptions; Reprint opens the print route; the visit button links with `?visit=`.
  Note: `pages/doctor/PatientPrescriptions.test.jsx` (7 tests, also delete / cancel, empty without a request, retry and the visit badge) and a new VisitForm test for the offer after saving.
