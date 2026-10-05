# T8-11 — Frontend: assistant schedule

**Phase:** 8 · **Area:** Frontend · **Size:** S · **Depends on:** T8-09
**Plan refs:** §7.2 · FR-I.6

## Steps
- [x] `pages/assistant/Schedule.jsx`: day/week toggle reusing `DayView` / `WeekView` with the assistant API, no "Start visit"; a "Book for patient" button.
  Note: /assistant/schedule renders the doctor's Schedule page; with WeekView now reading `useStaffApi()` too, every part of it calls the assistant API there.

## Done when
- [x] Vitest: actions call `/assistant/appointments/{id}/status`; there is no Start visit button.
