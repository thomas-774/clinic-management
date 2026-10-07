# T10-10 — Walkthrough and docs

**Phase:** 10 · **Area:** Both · **Size:** S · **Depends on:** T10-08, T10-09
**Plan refs:** Phase 10 test line · FR-K.1 – FR-K.6

## Steps
- [x] Walkthrough on the dev app (headless Edge over CDP, as in T9-14): check in a demo patient → visit form → choose PDF → Save → the file lands in the download folder; repeat with Word; then add an installment and download again from the timeline.
  Note (2026-10-07): a script drove headless Edge over CDP against the dev API + Vite, with downloads allowed into a folder (`Browser.setDownloadBehavior`). Per language, on a checked-in appointment booked for today: visit form → PDF → Save visit + PDF → the PDF downloaded and the patient page opened; a walk-in visit → Word → Save visit + Word → the .docx downloaded; a prescription written for the first visit (API); Add payment 200 on the first visit (UI) → remaining 300 → the timeline's PDF and Word buttons downloaded both again. 9/9 checks in ar (Aubree Sporer) and 9/9 in en (Dayna Crooks). Headless Edge overwrites a download of the same name (the timeline PDF has the visit form PDF's name), so the script moves each file aside as it lands.
- [x] Open each file: PDF rendered to PNG with PyMuPDF; `.docx` opened in Word (or converted with LibreOffice). Check ar and en: header, patient, work done, total / paid / remaining, payments, overall outstanding, prescriptions, signature line; nothing from the medical history.
  Note: all 8 files (4 per language): every PDF is 1 page; each `.docx` opened in Word 16 over COM with no error and was saved as PDF; text extracted with PyMuPDF and the ar timeline PDF and Word pages checked by eye. LibreOffice is not installed, so it was not tried. A small difference between the formats: in Arabic the PDF draws amounts as "ج.م 300.00" and Word as "300.00 ج.م"; same values, left as is.
- [x] README: a short "Visit files" section (where files are saved, how to make the browser ask for a folder, the server extensions needed).
  Note: "Visit files (PDF / Word)" after "Printing prescriptions": what is in the file, the two ways to get it, the Downloads folder and how to make Chrome / Edge ask for a folder, the PHP extensions, the bundled font and the CORS header.
- [x] Clean up walkthrough leftovers in the dev DB.
  Note: visits 17–22 (payments cascade), prescriptions 12–14 and appointments 47–49 deleted; checked with SQL that nothing of the walkthrough is left.
- [x] Tick Phase 10 in the plan (§8) and in `tasks/README.md`.

## Done when
- [x] Both formats open without warnings and show the same values as the patient page, in ar and en, before and after the installment.
  Note: same values as the patient page (API) in each file: before the installment 1,500 / 1,000 / 500 with outstanding 500 (PDF) and 800 / 300 / 500 with outstanding 1,000 (Word); after it 1,500 / 1,200 / 300 with both payments, outstanding 800 and the two prescription lines (both formats). None of the patient's history titles or details or the current illness appear in any file.
- [x] Both test suites and the linter are green.
  Note: backend 637 passed, frontend 252 passed, oxlint and Pint (`--dirty`) clean.
