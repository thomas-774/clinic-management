# T9-14 — Clinic printer setup and prescription walkthrough

**Phase:** 9 · **Area:** Full stack · **Size:** S · **Depends on:** T9-01 … T9-13
**Plan refs:** §7.3 Printing · §8 Phase 9 · §9.1

## Steps
- [x] Seed demo prescriptions for two demo patients.
  Note: `PrescriptionSeeder` (run last by `DatabaseSeeder`) writes one prescription for each of the first two patients through `PrescriptionService`, so the lines get their snapshots: Augmentin 1 g, Brufen 400 mg and a free-text Panadol Extra (3 days ago, with notes), and Flagyl 500 mg with Hexitol (10 days ago). Patients that already have a prescription are skipped, so it can be run again (`php artisan db:seed --class=PrescriptionSeeder`). Covered in `SeederTest`.
- [x] Add a "Printing prescriptions" section to the README / the doctor's user guide:
  Note: there is no separate user guide yet, so it is a README section; it also covers the browser's paper size / margins / scale and three troubleshooting cases (dialog still shows, URL on the edges, two sheets).
  1. Connect the printer to the clinic PC, set it as the **Windows default printer** and load A5 (or A4) paper.
  2. Normal mode: **Print** opens the browser dialog; choose the printer once and the browser remembers it. Turn off "Headers and footers".
  3. One-click mode: a desktop shortcut that starts Chrome/Edge with `--kiosk-printing` and the clinic URL prints straight to the default printer with no dialog. Every other window of that browser must be closed first, or the flag is ignored.
- [x] Manual walkthrough (ar and en): doctor opens a checked-in patient → visit → Write prescription → types "flag", reads the side note, picks Flagyl 500 mg, uses the suggested dose, adds a free-text line → Save & Print → one clean page → reprint from PatientDetails → hide Flagyl in Settings → it is no longer suggested, but the old prescription still prints it.
  Note (2026-10-06): run in the real app (dev API + Vite) with a script driving headless Edge over the DevTools protocol, with `window.print` replaced by a counter: Schedule → Start visit → save the visit → "Write prescription for this visit" → type "flag" → ↓ to Flagyl 500 mg (the side note shows it while it is highlighted) → Enter → "Use suggested dose" → Enter → "Zinc lozenges" used as written → Save & Print → `Page.printToPDF` → Back to patient → Reprint → Settings → Drugs → Hide Flagyl 500 mg → a new form's "flagyl" search no longer offers it → the old prescription still prints it. 13/13 checks passed in ar and in en; Flagyl was shown again afterwards. The walkthrough found a blank second sheet on longer prescriptions, fixed in `fix(T9-11)` before this task was finished (both walkthrough PDFs are now one A5 page). Printing on a physical printer and `--kiosk-printing` could not be tried here (no printer on this machine); they are documented for the clinic PC.

## Done when
- [x] Every walkthrough step works; backend and frontend suites and oxlint are green.
- [x] The printing guide is in the repo.
