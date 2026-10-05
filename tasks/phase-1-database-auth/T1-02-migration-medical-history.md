# T1-02 — Migration: medical_history_entries

**Phase:** 1 · **Area:** Backend / DB · **Size:** S · **Depends on:** T1-01
**Plan refs:** §5.1 medical_history_entries · §2 (visibility)

## Steps
- [ ] Columns: `patient_id` FK (indexed), `type` ENUM('condition','allergy','surgery','medication','note'), `title` VARCHAR(150), `details` TEXT nullable, `patient_visible` BOOLEAN default FALSE, `recorded_on` DATE.

## Done when
- [ ] The migration runs and `patient_visible` defaults to FALSE (private by default).
