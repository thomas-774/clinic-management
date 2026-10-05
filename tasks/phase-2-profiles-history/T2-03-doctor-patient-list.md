# T2-03 — Doctor: patient list with search and create patient

**Phase:** 2 · **Area:** Backend · **Size:** M · **Depends on:** T2-01
**Plan refs:** FR-C.1, FR-C.6 · §6.3

## Steps
- [x] `GET /doctor/patients?search=`: searches name or phone (LIKE), paginated (20 per page), sorted by name.
- [x] Each row: id, name, phone, last visit date (nullable).
- [x] `POST /doctor/patients` with `StorePatientRequest`: name, phone (unique), email (optional, unique), address, date of birth, gender, current illness (all optional except name, phone, address).
- [x] Create the User (role patient) and the Patient in one transaction, with a random 8-character initial password; return the patient plus `initial_password` **once** (it is never stored in plain text or returned again).
- [x] Feature tests: doctor creates a patient → 201 and the patient can log in with the returned password; duplicate phone → 422; a patient calling the endpoint → 403.

## Done when
- [x] Searching part of a phone number or a name returns the matching patients.
- [x] The doctor can create a patient who can then log in.
