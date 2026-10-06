# T10-10 — Walkthrough and docs

**Phase:** 10 · **Area:** Both · **Size:** S · **Depends on:** T10-08, T10-09
**Plan refs:** Phase 10 test line · FR-K.1 – FR-K.6

## Steps
- [ ] Walkthrough on the dev app (headless Edge over CDP, as in T9-14): check in a demo patient → visit form → choose PDF → Save → the file lands in the download folder; repeat with Word; then add an installment and download again from the timeline.
- [ ] Open each file: PDF rendered to PNG with PyMuPDF; `.docx` opened in Word (or converted with LibreOffice). Check ar and en: header, patient, work done, total / paid / remaining, payments, overall outstanding, prescriptions, signature line; nothing from the medical history.
- [ ] README: a short "Visit files" section (where files are saved, how to make the browser ask for a folder, the server extensions needed).
- [ ] Clean up walkthrough leftovers in the dev DB.
- [ ] Tick Phase 10 in the plan (§8) and in `tasks/README.md`.

## Done when
- [ ] Both formats open without warnings and show the same values as the patient page, in ar and en, before and after the installment.
- [ ] Both test suites and the linter are green.
