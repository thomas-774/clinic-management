# T2-04 — Doctor: patient details and update

**Phase:** 2 · **Area:** Backend · **Size:** M · **Depends on:** T2-01
**Plan refs:** FR-C.2, FR-C.3 · §6.3

## Steps
- [x] `GET /doctor/patients/{id}`: profile + **all** history entries (detailed resource). Add an empty `visits` array for now (filled in by T5-07).
- [x] `PUT /doctor/patients/{id}` with `UpdatePatientRequest`: name, phone, address, date of birth, gender, current illness.

## Done when
- [x] The doctor sees private entries; updating the illness shows up on the patient's own profile.
