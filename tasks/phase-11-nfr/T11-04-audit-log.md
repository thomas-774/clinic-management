# T11-04 — Audit log of medical and money records

**Phase:** 11 · **Area:** Backend · **Size:** L · **Depends on:** —
**Plan refs:** §11.2 NFR-S.4 · §2 · §9.2

## Steps
- [x] Migration `audit_logs`: `id`, `user_id` (nullable FK, null on delete), `user_role`, `action` (`viewed`, `created`, `updated`, `deleted`, `exported`, `printed`), `auditable_type`, `auditable_id`, `patient_id` (nullable, indexed), `changed_fields` (JSON, names only), `ip`, `user_agent` (255), `created_at`. Indexes: `(patient_id, created_at)`, `(user_id, created_at)`, `created_at`. No `updated_at`.
  Note: also an `(auditable_type, auditable_id)` index, for "activity on this record". `user_role` is nullable like `user_id`. Actions are the `AuditAction` enum.
- [x] `AuditLog` model with no update / delete path (`booted()` throws on `updating` / `deleting`); only the prune command in T11-05 removes rows, with a query, not the model.
- [x] `AuditLogger` service: `record(action, model, patient, changedFields = [])`, reading the user, role and IP from the request. Never stores field values.
  Note: only staff (doctor, assistant) are logged. With no user (seeders, factories, console commands such as T11-06's data migration) or a patient acting on their own data, nothing is written. The same read of the same record is logged once per request (kept on the request's attributes).
- [x] Writes: an `Auditable` trait (model events `created`, `updated` with `getChanges()` keys minus timestamps, `deleted`) on `Patient` (medical fields), `MedicalHistoryEntry`, `Visit`, `Payment`, `Prescription`, `PrescriptionItem`.
  Note: on `Patient` every column change is logged (address, date of birth, gender, current illness), not only `current_illness`, so the assistant's contact edits show too; name and phone live on `users` and are not logged. Each model gives its patient with `auditPatientId()` — `Payment` and `PrescriptionItem` look it up with a query, not a lazy load (T11-08 will block lazy loading). A prescription save writes one row for the prescription plus one per line created; the old lines are removed with a query (`items()->delete()`) and a prescription's lines go with it by cascade, so those deletions have no rows of their own.
- [x] Reads: explicit `AuditLogger::record('viewed', …)` in the doctor's patient detail, history, visit and prescription show endpoints; `exported` in the visit file endpoint (T10-05); `printed` when the print page loads a prescription. The assistant's patient page logs `viewed` too (contact and money only).
  Note: there are no separate history or visit show endpoints — both are part of `GET /doctor/patients/{patient}`, which is logged once as `viewed` on the patient. The print page asks `GET /doctor/prescriptions/{id}?purpose=print` (frontend `usePrescription(id, { purpose: 'print' })`, never served from cache), logged as `printed`; the form's read is `viewed`. `exported` is written after the file is built, so a failed export is not logged.
- [x] Log once per request, not per model loaded (a list endpoint does not write one row per item).
  Note: list endpoints (patient lists, unpaid visits, prescriptions list, reports) are not logged at all.
- [x] Write the audit row in the same transaction as the change it describes.
  Note: the trait wraps `save()` and `delete()` in a transaction (a savepoint inside an outer one), so this holds for every write, also where the controller had no transaction (history entries).

## Done when
- [x] Feature tests: each write above creates exactly one row with the right action, user, patient and field names; `changed_fields` never holds a value (assert on a known illness string). (`tests/Feature/AuditLogTest.php`)
- [x] Viewing a patient as doctor and as assistant each writes one `viewed` row; patient endpoints for the patient's own data are not logged.
- [x] Updating or deleting an `AuditLog` through the model throws.
- [x] A visit save with the audit write failing rolls back the visit. (Also a history entry; removing the trait's transaction makes that test fail.)
- [x] Both test suites still pass.
