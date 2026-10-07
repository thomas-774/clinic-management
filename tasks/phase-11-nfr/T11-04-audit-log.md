# T11-04 — Audit log of medical and money records

**Phase:** 11 · **Area:** Backend · **Size:** L · **Depends on:** —
**Plan refs:** §11.2 NFR-S.4 · §2 · §9.2

## Steps
- [ ] Migration `audit_logs`: `id`, `user_id` (nullable FK, null on delete), `user_role`, `action` (`viewed`, `created`, `updated`, `deleted`, `exported`, `printed`), `auditable_type`, `auditable_id`, `patient_id` (nullable, indexed), `changed_fields` (JSON, names only), `ip`, `user_agent` (255), `created_at`. Indexes: `(patient_id, created_at)`, `(user_id, created_at)`, `created_at`. No `updated_at`.
- [ ] `AuditLog` model with no update / delete path (`booted()` throws on `updating` / `deleting`); only the prune command in T11-05 removes rows, with a query, not the model.
- [ ] `AuditLogger` service: `record(action, model, patient, changedFields = [])`, reading the user, role and IP from the request. Never stores field values.
- [ ] Writes: an `Auditable` trait (model events `created`, `updated` with `getChanges()` keys minus timestamps, `deleted`) on `Patient` (medical fields), `MedicalHistoryEntry`, `Visit`, `Payment`, `Prescription`, `PrescriptionItem`.
- [ ] Reads: explicit `AuditLogger::record('viewed', …)` in the doctor's patient detail, history, visit and prescription show endpoints; `exported` in the visit file endpoint (T10-05); `printed` when the print page loads a prescription. The assistant's patient page logs `viewed` too (contact and money only).
- [ ] Log once per request, not per model loaded (a list endpoint does not write one row per item).
- [ ] Write the audit row in the same transaction as the change it describes.

## Done when
- [ ] Feature tests: each write above creates exactly one row with the right action, user, patient and field names; `changed_fields` never holds a value (assert on a known illness string).
- [ ] Viewing a patient as doctor and as assistant each writes one `viewed` row; patient endpoints for the patient's own data are not logged.
- [ ] Updating or deleting an `AuditLog` through the model throws.
- [ ] A visit save with the audit write failing rolls back the visit.
- [ ] Both test suites still pass.
