# T10-09 — Frontend: PDF / Word buttons on the visit timeline

**Phase:** 10 · **Area:** Frontend · **Size:** S · **Depends on:** T10-07
**Plan refs:** FR-K.4, FR-K.6

## Steps
- [x] In `pages/doctor/VisitTimeline.jsx`, add **PDF** and **Word** small buttons to each visit's action row (doctor's PatientDetails only — the assistant's patient page never gets them).
  Note: a new `canDownload` prop (only `PatientDetails` passes it) renders a `VisitFileButtons` component, so the assistant's timeline never mounts the export hook. Each visit has its own mutation.
- [x] Buttons have an accessible label with the visit date ("Download visit of 7 Oct 2026 as PDF") and show a spinner/disabled state while that visit's file downloads.
  Note: `aria-busy` and a small spinner on the clicked button; both buttons of that visit are disabled while it downloads, other visits stay usable.
- [x] The file reflects the current balance, so after "Add payment" the next download shows the new remaining (no client cache of the blob).
  Note: every click calls the API again (no blob kept), and the server sends `Cache-Control: no-store`.

## Done when
- [x] Vitest: each visit has both buttons; clicking calls the export with the right id and format; the buttons are absent where the timeline is rendered without the doctor actions.
  Note: 4 new tests in `VisitTimeline.test.jsx` (also: busy state, a second download after a payment hits the API again, the 403 message). `VisitTimeline` is only rendered by the doctor's and the assistant's patient pages; the assistant one is the "without doctor actions" case.
- [x] Assistant pages tests still pass and contain no download buttons.
  Note: `pages/assistant/Patients.test.jsx` now also asserts no PDF / Word buttons on a visit. Frontend 252 passed, backend 637 passed, oxlint clean.
