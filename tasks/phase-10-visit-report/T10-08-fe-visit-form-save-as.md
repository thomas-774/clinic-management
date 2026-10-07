# T10-08 — Frontend: "Also save as" on the visit form

**Phase:** 10 · **Area:** Frontend · **Size:** M · **Depends on:** T10-07
**Plan refs:** §7.2 VisitForm · §7.3 · FR-K.1

## Steps
- [x] In `pages/doctor/VisitForm.jsx`, next to **Save**, a small radio group (segmented buttons) "Also save as: None · PDF · Word", keyboard-usable and labelled for screen readers.
  Note: real radio inputs (visually segmented) in a `<fieldset>` with the legend "Also save as", so Tab reaches the group and the arrow keys move the choice.
- [x] The choice is read from and written to localStorage `clinic.visitFile` (`none` | `pdf` | `docx`), wrapped in try/catch; default `none`.
  Note: an unknown stored value also falls back to None.
- [x] The Save button text follows the choice: "Save", "Save + PDF", "Save + Word".
  Note: the existing label is "Save visit" (and the existing tests must pass unchanged), so the labels are "Save visit", "Save visit + PDF", "Save visit + Word" (i18next context keys `visitForm.save_pdf` / `save_docx`).
- [x] On `POST /doctor/visits` success: when a format is chosen, call `useVisitExport` with the new visit id **before** navigating to the patient page; the visit toast still shows. If the download fails, still navigate, and show "Visit saved, but the file could not be downloaded — use the PDF / Word buttons on the visit." (FR-K.1).
  Note: `useVisitExport` got a `showErrors` option; the form passes `false`, so a failed download shows only this warning, not the hook's generic error too. Save stays disabled ("Saving…") while the file downloads.
- [x] Saving is never blocked or slowed by the file choice when it is None.
  Note: with None the form navigates straight away; the export hook is never called.

## Done when
- [x] Vitest: the choice is remembered across renders (localStorage) and falls back to None when storage throws; Save with PDF calls the export once with `format=pdf` after the visit is created; a failed export still navigates and shows the warning; None never calls the export.
  Note: `src/pages/doctor/VisitForm.saveAs.test.jsx` (7 tests; also checks the order create → export and that the visit toast still shows).
- [x] Existing `VisitForm.test.jsx` tests still pass unchanged.
  Note: unchanged, 9 passed. Frontend 248 passed, backend 637 passed, oxlint clean.
