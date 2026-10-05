# T2-05 — Medical history CRUD (doctor)

**Phase:** 2 · **Area:** Backend · **Size:** M · **Depends on:** T2-04
**Plan refs:** FR-C.4 · §6.3

## Steps
- [x] `POST /doctor/patients/{id}/history`: type, title, details, patient_visible, recorded_on.
- [x] `PUT /doctor/patients/{id}/history/{entryId}`: same fields.
- [x] `DELETE /doctor/patients/{id}/history/{entryId}`.
- [x] Return 404 if the entry does not belong to that patient (scoped route binding).

## Done when
- [x] Create, edit, delete and toggling visibility all work through the API.
