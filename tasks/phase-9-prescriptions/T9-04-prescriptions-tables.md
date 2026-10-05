# T9-04 — Prescriptions tables and models

**Phase:** 9 · **Area:** Backend · **Size:** S · **Depends on:** T9-01, T1-05
**Plan refs:** §5.1 prescriptions, prescription_items · §5.2 · §5.3 · RX-2

## Steps
- [x] Migration `prescriptions`: patient_id, doctor_id, visit_id (nullable, null on delete), issued_on, notes (nullable); INDEX `(patient_id, issued_on)`.
- [x] Migration `prescription_items`: prescription_id (cascade), drug_id (nullable, null on delete), drug_name, drug_form (nullable), instructions, position.
- [x] Models `Prescription` (belongsTo patient, doctor, visit; hasMany items ordered by position) and `PrescriptionItem` (belongsTo prescription, drug); `Patient::prescriptions()`, `Visit::prescriptions()`.
- [x] Factories for both.
  Note: also `Drug::prescriptionItems()` (§5.2 drugs 1—N prescription_items). `PrescriptionFactory` has `forVisit($visit)` (same patient, RX-5) and `withItems($n)`; `PrescriptionItemFactory` defaults to a free-text line and has `forDrug($drug)` (copies name / form, RX-2). `doctor_id` cascades on delete like the other doctor-owned tables.

## Done when
- [x] Migrations run up and down; deleting a prescription deletes its items.
  Note: checked with `migrate` → `migrate:rollback --step=2` → `migrate` on the dev DB; `tests/Feature/Database/PrescriptionModelTest.php` covers the columns, index, cascade and the null-on-delete links.
- [x] A factory prescription with 3 items loads them in position order.
