# T11-06 — Encrypt medical text at rest

**Phase:** 11 · **Area:** Backend · **Size:** L · **Depends on:** T11-04
**Plan refs:** §11.2 NFR-S.6 · §11.3

## Steps
- [x] Confirm nothing queries the fields below in SQL (`where`, `like`, `orderBy`, reports, exports). Checked Oct 7, 2026: none do; recheck before starting.
  Note: rechecked Oct 7, 2026 — no `where` / `like` / `orderBy` / raw `DB::table` read on them in `app/`, `database/` or `routes/`; reports and the visit file read them through the models. One test did (`PrivacyTest`: `where('title', 'Asthma')`); it now matches in PHP.
- [x] Migration: change `medical_history_entries.title` (string 150) and `prescription_items.instructions` (string 255) to `text` — ciphertext is several times longer than the plain value. Keep length limits in the Form Requests instead.
  Note: `2026_10_07_120000_widen_encrypted_medical_columns`. The limits were already in the Form Requests (title 150, details 5,000, current illness 2,000, work done 5,000, notes 1,000, instructions 255); the longest (5,000 characters, up to 4 bytes each) is about 36 kB of ciphertext, inside `text`'s 64 kB.
- [x] Add the `encrypted` cast to `Patient::current_illness`, `MedicalHistoryEntry::title` / `details`, `Visit::work_done`, `Prescription::notes`, `PrescriptionItem::instructions`.
- [x] Data migration that encrypts existing rows in chunks (`chunkById(500)`), skipping values that already decrypt; `down()` decrypts them back. Run it on a copy of the dev DB first.
  Note: `2026_10_07_120100_encrypt_medical_text`, using `App\Support\MedicalEncryption` (straight table updates: no audit rows, no casts, `updated_at` unchanged), in a transaction. Run on a copy of the dev DB (Oct 7, 2026; 12 patients, 31 history entries, 11 visits, 7 prescriptions, 13 lines = 87 values) with a throwaway key: up → down → up left 0 mismatches against the original and every record readable through the models; the copy was dropped afterwards.
- [x] Check every reader still gets plain text: API Resources, `VisitReportService` (PDF / Word), the prescription print page, seeders and factories, the demo data (T7-04).
  Note: all read through the models. Covered by the existing suites (visit file contents T10-06, prescription print, seeders) plus the new API round-trip test. T7-04's demo data is not built yet; it must go through the models too (written into its own task when it starts).
- [x] Key handling, written into T7-05 / T7-07: `APP_KEY` stored in the password manager and the backup runbook (a backup without the key cannot be read); rotation by moving the old key to `APP_PREVIOUS_KEYS` and re-saving rows with a command `clinic:reencrypt`.
  Note: `php artisan clinic:reencrypt` decrypts with the current or a previous key and saves with the current one, all or nothing. The test suite has its own fixed test-only `APP_KEY` in `phpunit.xml`.

## Done when
- [x] Test: after saving, a raw `DB::table(...)->value(...)` on each field is not the plain text, and the model returns the plain text. (`tests/Feature/EncryptMedicalFieldsTest.php`)
- [x] Test: Arabic text round-trips unchanged, incl. the visit file PDF / Word contents (T10-06 tests still pass).
- [x] Migration up → down → up on a seeded DB leaves every value readable. (`tests/Migrations/EncryptMedicalTextMigrationTest.php` — its own suite, a fresh database per test, because MySQL commits column-type changes at once; also run on a copy of the dev DB, above.)
- [x] `clinic:reencrypt` after a key rotation leaves every value readable with the new key only.
- [x] Both test suites still pass.

**Dev setup after this task:** the local `.env` has no `APP_KEY` yet. Before using the app locally: `php artisan key:generate`, then `php artisan migrate` (encrypts the dev data with that key). Keep that key: the dev data cannot be read without it.
