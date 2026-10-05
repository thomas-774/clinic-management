# T2-05 — Medical history CRUD (doctor)

**Phase:** 2 · **Area:** Backend · **Size:** M · **Depends on:** T2-04
**Plan refs:** FR-C.4 · §6.3

## Steps
- [ ] `POST /doctor/patients/{id}/history`: type, title, details, patient_visible, recorded_on.
- [ ] `PUT /doctor/patients/{id}/history/{entryId}`: same fields.
- [ ] `DELETE /doctor/patients/{id}/history/{entryId}`.
- [ ] Return 404 if the entry does not belong to that patient (scoped route binding).

## Done when
- [ ] Create, edit, delete and toggling visibility all work through the API.
