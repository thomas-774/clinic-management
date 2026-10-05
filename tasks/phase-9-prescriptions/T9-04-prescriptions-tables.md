# T9-04 — Prescriptions tables and models

**Phase:** 9 · **Area:** Backend · **Size:** S · **Depends on:** T9-01, T1-05
**Plan refs:** §5.1 prescriptions, prescription_items · §5.2 · §5.3 · RX-2

## Steps
- [ ] Migration `prescriptions`: patient_id, doctor_id, visit_id (nullable, null on delete), issued_on, notes (nullable); INDEX `(patient_id, issued_on)`.
- [ ] Migration `prescription_items`: prescription_id (cascade), drug_id (nullable, null on delete), drug_name, drug_form (nullable), instructions, position.
- [ ] Models `Prescription` (belongsTo patient, doctor, visit; hasMany items ordered by position) and `PrescriptionItem` (belongsTo prescription, drug); `Patient::prescriptions()`, `Visit::prescriptions()`.
- [ ] Factories for both.

## Done when
- [ ] Migrations run up and down; deleting a prescription deletes its items.
- [ ] A factory prescription with 3 items loads them in position order.
