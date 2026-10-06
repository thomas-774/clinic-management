# T10-06 — Tests: visit file permissions and contents

**Phase:** 10 · **Area:** Backend · **Size:** M · **Depends on:** T10-05
**Plan refs:** §2 · FR-K.2, FR-K.3, FR-K.6 · VR-1 – VR-3 · §9.1

## Steps
- [ ] Permissions: guest → 401; patient (even for their own visit) → 403; assistant → 403; deactivated user → 401.
- [ ] Privacy: give the patient a private and a patient-visible history entry and a current illness; neither format contains their text.
- [ ] Same values in both formats (VR-3): build one visit, export pdf and docx, and compare the extracted amounts, work done and payment count.
- [ ] Always current (VR-1): export, add an installment through the API, export again → paid and remaining changed, overall outstanding changed.
- [ ] Prescriptions: a prescription of another visit of the same patient is not in the file; one linked to this visit is, without drug notes or warnings.
- [ ] Nothing is stored: `Storage::fake()` stays empty after an export.

## Done when
- [ ] All of the above are Pest tests and pass with the full backend suite.
