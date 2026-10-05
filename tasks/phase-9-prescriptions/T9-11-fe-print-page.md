# T9-11 — Frontend: prescription print page

**Phase:** 9 · **Area:** Frontend · **Size:** M · **Depends on:** T9-05, T9-06
**Plan refs:** §7.3 Printing · FR-J.5 · RX-4

## Steps
- [ ] Route `/doctor/prescriptions/:id/print` outside DoctorLayout (no sidebar), rendering `PrescriptionPrint`.
- [ ] Layout: header (clinic name, doctor name and title, address, phone), thin rule; patient name, age, date; "Rx" and the numbered lines (drug name and form in bold, instructions below); notes; signature line; footer text. Nothing else (RX-4).
- [ ] Print stylesheet: `@page { size: A5; margin: 10mm }` (A4 when the setting says so, via a page class), black on white, no shadows, never split one line across pages.
- [ ] On load (after the data and fonts are ready) call `window.print()` once; "Print again" and "Back to patient" buttons show on screen only (`print:hidden`).
- [ ] The Arabic interface prints RTL; drug names and instructions keep `dir="auto"`.

## Done when
- [ ] Vitest: renders header, patient, all lines and notes; never renders warnings or ingredients; calls `window.print` once.
- [ ] Headless Edge `--print-to-pdf` of the page gives one A5 page with no app chrome, in ar and en.
