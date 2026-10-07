# T11-06 — Encrypt medical text at rest

**Phase:** 11 · **Area:** Backend · **Size:** L · **Depends on:** T11-04
**Plan refs:** §11.2 NFR-S.6 · §11.3

## Steps
- [ ] Confirm nothing queries the fields below in SQL (`where`, `like`, `orderBy`, reports, exports). Checked Oct 7, 2026: none do; recheck before starting.
- [ ] Migration: change `medical_history_entries.title` (string 150) and `prescription_items.instructions` (string 255) to `text` — ciphertext is several times longer than the plain value. Keep length limits in the Form Requests instead.
- [ ] Add the `encrypted` cast to `Patient::current_illness`, `MedicalHistoryEntry::title` / `details`, `Visit::work_done`, `Prescription::notes`, `PrescriptionItem::instructions`.
- [ ] Data migration that encrypts existing rows in chunks (`chunkById(500)`), skipping values that already decrypt; `down()` decrypts them back. Run it on a copy of the dev DB first.
- [ ] Check every reader still gets plain text: API Resources, `VisitReportService` (PDF / Word), the prescription print page, seeders and factories, the demo data (T7-04).
- [ ] Key handling, written into T7-05 / T7-07: `APP_KEY` stored in the password manager and the backup runbook (a backup without the key cannot be read); rotation by moving the old key to `APP_PREVIOUS_KEYS` and re-saving rows with a command `clinic:reencrypt`.

## Done when
- [ ] Test: after saving, a raw `DB::table(...)->value(...)` on each field is not the plain text, and the model returns the plain text.
- [ ] Test: Arabic text round-trips unchanged, incl. the visit file PDF / Word contents (T10-06 tests still pass).
- [ ] Migration up → down → up on a seeded DB leaves every value readable.
- [ ] `clinic:reencrypt` after a key rotation leaves every value readable with the new key only.
- [ ] Both test suites still pass.
