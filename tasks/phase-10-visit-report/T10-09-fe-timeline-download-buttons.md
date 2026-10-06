# T10-09 — Frontend: PDF / Word buttons on the visit timeline

**Phase:** 10 · **Area:** Frontend · **Size:** S · **Depends on:** T10-07
**Plan refs:** FR-K.4, FR-K.6

## Steps
- [ ] In `pages/doctor/VisitTimeline.jsx`, add **PDF** and **Word** small buttons to each visit's action row (doctor's PatientDetails only — the assistant's patient page never gets them).
- [ ] Buttons have an accessible label with the visit date ("Download visit of 7 Oct 2026 as PDF") and show a spinner/disabled state while that visit's file downloads.
- [ ] The file reflects the current balance, so after "Add payment" the next download shows the new remaining (no client cache of the blob).

## Done when
- [ ] Vitest: each visit has both buttons; clicking calls the export with the right id and format; the buttons are absent where the timeline is rendered without the doctor actions.
- [ ] Assistant pages tests still pass and contain no download buttons.
