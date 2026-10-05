# T9-05 — Prescriptions API

**Phase:** 9 · **Area:** Backend · **Size:** M · **Depends on:** T9-03, T9-04, T9-06
**Plan refs:** §6.3 · FR-J.4, FR-J.5, FR-J.7 · RX-1, RX-2, RX-5

## Steps
- [x] `PrescriptionService::save()` in one transaction: checks RX-1 / RX-5, copies `drug_name` and `drug_form` from the drug when `drug_id` is given (RX-2), writes items with `position` 1..n; used by both create and update (update replaces the items).
  Note: a catalogue line always takes the drug's current name / form, even if the request also sends a `drug_name`. An update re-copies them, so editing a prescription brings its catalogue lines up to date; only saving freezes them. The lines are sorted by their request index first, because Laravel's `validated()` can return nested arrays with their keys out of order (`PrescriptionServiceTest` covers this). If no `issued_on` is sent, a new prescription is dated today and an edited one keeps its date.
- [x] `StorePrescriptionRequest`: `visit_id` (nullable, must belong to the patient), `issued_on` (date, default today), `notes` (max 1000), `items` 1–15, each `drug_id` (exists) **or** `drug_name` (max 150), `instructions` required (max 255).
  Note: the same request is used for `PUT`, where the patient is the prescription's patient. `issued_on` must be `Y-m-d`. Field names and the RX-1 / RX-5 messages are localized (ar / en).
- [x] `POST /doctor/patients/{id}/prescriptions`, `GET /doctor/patients/{id}/prescriptions` (newest first, with item names).
  Note: the list returns `id`, `issued_on`, `visit_id`, `notes` and `drug_names` in line order, unpaginated (one patient has few prescriptions).
- [x] `GET /doctor/prescriptions/{id}`: prescription, items, patient name and age (from date_of_birth), and a `print` block (clinic header fields and paper size from doctor_settings).
  Note: `age` is the age on the issue date (null without a date of birth). `print` = `doctor_name` (the doctor's account name), `doctor_title`, `clinic_name`, `clinic_address`, `clinic_phone`, `footer`, `paper`. No drug notes, warnings or prices (RX-4).
- [x] `PUT` and `DELETE /doctor/prescriptions/{id}`.
- [x] Include the prescriptions count in `GET /doctor/patients/{id}`.
  Note: `prescriptions_count` in PatientDetailResource (doctor only). Access also goes through a new `PatientPolicy::managePrescriptions` (doctor only), next to the `role:doctor` middleware.

## Done when
- [x] Feature tests: create with catalogue and free-text lines; 0 or 16 lines → 422; a line with neither drug_id nor drug_name → 422; another patient's visit_id → 422; renaming the drug afterwards leaves the issued line unchanged; update replaces lines; delete removes items.
  Note: `tests/Feature/Doctor/PrescriptionsTest.php` and `tests/Feature/Services/PrescriptionServiceTest.php`.
