# T9-14 — Clinic printer setup and prescription walkthrough

**Phase:** 9 · **Area:** Full stack · **Size:** S · **Depends on:** T9-01 … T9-13
**Plan refs:** §7.3 Printing · §8 Phase 9 · §9.1

## Steps
- [ ] Seed demo prescriptions for two demo patients.
- [ ] Add a "Printing prescriptions" section to the README / the doctor's user guide:
  1. Connect the printer to the clinic PC, set it as the **Windows default printer** and load A5 (or A4) paper.
  2. Normal mode: **Print** opens the browser dialog; choose the printer once and the browser remembers it. Turn off "Headers and footers".
  3. One-click mode: a desktop shortcut that starts Chrome/Edge with `--kiosk-printing` and the clinic URL prints straight to the default printer with no dialog. Every other window of that browser must be closed first, or the flag is ignored.
- [ ] Manual walkthrough (ar and en): doctor opens a checked-in patient → visit → Write prescription → types "flag", reads the side note, picks Flagyl 500 mg, uses the suggested dose, adds a free-text line → Save & Print → one clean page → reprint from PatientDetails → hide Flagyl in Settings → it is no longer suggested, but the old prescription still prints it.

## Done when
- [ ] Every walkthrough step works; backend and frontend suites and oxlint are green.
- [ ] The printing guide is in the repo.
