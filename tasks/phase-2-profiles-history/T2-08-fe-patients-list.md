# T2-08 — Frontend: PatientsList (doctor)

**Phase:** 2 · **Area:** Frontend · **Size:** M · **Depends on:** T2-03, T1-14
**Plan refs:** FR-C.1, FR-C.6 · §7.2

## Steps
- [ ] Reusable `DataTable` component (columns, rows, empty state, pagination).
- [ ] Search box with 300 ms debounce, kept in the URL (`?search=`).
- [ ] Clicking a row opens `/doctor/patients/:id`.
- [ ] "New patient" button opens a modal form (name, phone, address, optional fields); on success show the initial password once with a copy button, then refresh the list.

## Done when
- [ ] The doctor can find a patient by name or phone and open them.
- [ ] The doctor can add a patient and sees the initial password to give them.
