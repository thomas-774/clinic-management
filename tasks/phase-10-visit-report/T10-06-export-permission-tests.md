# T10-06 — Tests: visit file permissions and contents

**Phase:** 10 · **Area:** Backend · **Size:** M · **Depends on:** T10-05
**Plan refs:** §2 · FR-K.2, FR-K.3, FR-K.6 · VR-1 – VR-3 · §9.1

## Steps
- [x] Permissions: guest → 401; patient (even for their own visit) → 403; assistant → 403; deactivated user → 401.
  Note: each for pdf and docx. The deactivated case uses a real Sanctum token (deactivation is checked on the token, so `actingAs` would skip it): 200 before, 401 after `is_active = false`.
- [x] Privacy: give the patient a private and a patient-visible history entry and a current illness; neither format contains their text.
  Note: in ar and en; the other visit's work done is also checked to be absent.
- [x] Same values in both formats (VR-3): build one visit, export pdf and docx, and compare the extracted amounts, work done and payment count.
  Note: in ar and en, every "1,500.00"-style amount of both files is compared as a sorted list; names, phone and drug names too; payments by their method labels and dates.
- [x] Always current (VR-1): export, add an installment through the API, export again → paid and remaining changed, overall outstanding changed.
- [x] Prescriptions: a prescription of another visit of the same patient is not in the file; one linked to this visit is, without drug notes or warnings.
- [x] Nothing is stored: `Storage::fake()` stays empty after an export.
  Note: both the `local` and `public` disks.

## Done when
- [x] All of the above are Pest tests and pass with the full backend suite.
  Note: `tests/Feature/VisitExportPrivacyTest.php` (20 tests), reusing `visitReportFixture()`, `pdfText()` and a docx text helper. Backend 637 passed, frontend 227 passed, oxlint and Pint clean.
