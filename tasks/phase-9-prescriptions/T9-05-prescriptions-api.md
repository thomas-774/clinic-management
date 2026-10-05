# T9-05 — Prescriptions API

**Phase:** 9 · **Area:** Backend · **Size:** M · **Depends on:** T9-03, T9-04, T9-06
**Plan refs:** §6.3 · FR-J.4, FR-J.5, FR-J.7 · RX-1, RX-2, RX-5

## Steps
- [ ] `PrescriptionService::save()` in one transaction: checks RX-1 / RX-5, copies `drug_name` and `drug_form` from the drug when `drug_id` is given (RX-2), writes items with `position` 1..n; used by both create and update (update replaces the items).
- [ ] `StorePrescriptionRequest`: `visit_id` (nullable, must belong to the patient), `issued_on` (date, default today), `notes` (max 1000), `items` 1–15, each `drug_id` (exists) **or** `drug_name` (max 150), `instructions` required (max 255).
- [ ] `POST /doctor/patients/{id}/prescriptions`, `GET /doctor/patients/{id}/prescriptions` (newest first, with item names).
- [ ] `GET /doctor/prescriptions/{id}`: prescription, items, patient name and age (from date_of_birth), and a `print` block (clinic header fields and paper size from doctor_settings).
- [ ] `PUT` and `DELETE /doctor/prescriptions/{id}`.
- [ ] Include the prescriptions count in `GET /doctor/patients/{id}`.

## Done when
- [ ] Feature tests: create with catalogue and free-text lines; 0 or 16 lines → 422; a line with neither drug_id nor drug_name → 422; another patient's visit_id → 422; renaming the drug afterwards leaves the issued line unchanged; update replaces lines; delete removes items.
