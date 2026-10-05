# T9-11 — Frontend: prescription print page

**Phase:** 9 · **Area:** Frontend · **Size:** M · **Depends on:** T9-05, T9-06
**Plan refs:** §7.3 Printing · FR-J.5 · RX-4

## Steps
- [x] Route `/doctor/prescriptions/:id/print` outside DoctorLayout (no sidebar), rendering `PrescriptionPrint`.
  Note: the page is `pages/doctor/PrescriptionPrintPage.jsx` (loading, 404, buttons, printing); the sheet itself is `components/PrescriptionPrint.jsx`, so Settings → Prescription (T9-13) can reuse it for the preview. The route stays inside the doctor-only RoleRoute and replaces T9-10's placeholder.
- [x] Layout: header (clinic name, doctor name and title, address, phone), thin rule; patient name, age, date; "Rx" and the numbered lines (drug name and form in bold, instructions below); notes; signature line; footer text. Nothing else (RX-4).
  Note: the sheet reads only these fields from the API's prescription, and empty header parts, a missing age and empty notes are left out.
- [x] Print stylesheet: `@page { size: A5; margin: 10mm }` (A4 when the setting says so, via a page class), black on white, no shadows, never split one line across pages.
  Note: `index.css` defines the named pages `rx-a5` / `rx-a4`; the sheet's `rx-paper-a5` / `rx-paper-a4` class picks one. Lines, notes, the signature and the footer are `break-inside: avoid`. The toast area is now `print:hidden` too, so the "Prescription saved." toast from Save & Print is never printed.
- [x] On load (after the data and fonts are ready) call `window.print()` once; "Print again" and "Back to patient" buttons show on screen only (`print:hidden`).
  Note: waits for `document.fonts.ready`; safe under StrictMode's double effect.
- [x] The Arabic interface prints RTL; drug names and instructions keep `dir="auto"`.
  Note: also fixes T9-10's "← patient" link, which now comes from i18n so the arrow points the right way in Arabic.

## Done when
- [x] Vitest: renders header, patient, all lines and notes; never renders warnings or ingredients; calls `window.print` once.
  Note: `pages/doctor/PrescriptionPrintPage.test.jsx` (6 tests, also A4 and empty parts, "Back to patient" and Arabic RTL).
- [x] Headless Edge `--print-to-pdf` of the page gives one A5 page with no app chrome, in ar and en.
  Note: printed over the DevTools protocol (`Page.printToPDF` with `preferCSSPageSize`, as the page needs a logged-in token): ar and en each gave one 148 × 210 mm page with only the prescription, and `window.print` was called once per load. With the setting on A4 it gave one 210 × 297 mm page.
  Note (fix, found in the T9-14 walkthrough): a longer prescription printed a blank second Letter-size sheet, because zero-height boxes after the sheet (the hidden toast area, an element the browser adds) stayed on the default page and Chrome breaks the page when the page name changes. The print page now also puts the `rx-paper-*` class on `<html>`; re-checked: one page in ar, en and A4.
