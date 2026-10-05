# T8-10 — Frontend: assistant patients list and patient page

**Phase:** 8 · **Area:** Frontend · **Size:** M · **Depends on:** T8-04, T8-05, T8-08
**Plan refs:** §7.2 · FR-I.2, FR-I.3, FR-I.4, FR-I.6

## Steps
- [ ] `pages/assistant/PatientsList.jsx`: search (`useDebouncedValue`), `DataTable`, "New patient" reusing `NewPatientModal` with a `hideMedical` prop (no current illness) and the assistant API; the initial password is shown once.
- [ ] `pages/assistant/PatientPage.jsx`: contact info with edit, outstanding balance, visits money table (`PaymentStatusBadge`, Record payment), "Book appointment" reusing `BookForPatientModal` + `SlotGrid`.

## Done when
- [ ] Vitest: the new-patient form has no illness field; the patient page shows money but no work done; recording a payment works.
