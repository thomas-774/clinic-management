# T10-08 — Frontend: "Also save as" on the visit form

**Phase:** 10 · **Area:** Frontend · **Size:** M · **Depends on:** T10-07
**Plan refs:** §7.2 VisitForm · §7.3 · FR-K.1

## Steps
- [ ] In `pages/doctor/VisitForm.jsx`, next to **Save**, a small radio group (segmented buttons) "Also save as: None · PDF · Word", keyboard-usable and labelled for screen readers.
- [ ] The choice is read from and written to localStorage `clinic.visitFile` (`none` | `pdf` | `docx`), wrapped in try/catch; default `none`.
- [ ] The Save button text follows the choice: "Save", "Save + PDF", "Save + Word".
- [ ] On `POST /doctor/visits` success: when a format is chosen, call `useVisitExport` with the new visit id **before** navigating to the patient page; the visit toast still shows. If the download fails, still navigate, and show "Visit saved, but the file could not be downloaded — use the PDF / Word buttons on the visit." (FR-K.1).
- [ ] Saving is never blocked or slowed by the file choice when it is None.

## Done when
- [ ] Vitest: the choice is remembered across renders (localStorage) and falls back to None when storage throws; Save with PDF calls the export once with `format=pdf` after the visit is created; a failed export still navigates and shows the warning; None never calls the export.
- [ ] Existing `VisitForm.test.jsx` tests still pass unchanged.
