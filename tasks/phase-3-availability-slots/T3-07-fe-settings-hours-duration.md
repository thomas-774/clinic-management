# T3-07 — Frontend: Settings, weekly hours and duration

**Phase:** 3 · **Area:** Frontend · **Size:** M · **Depends on:** T3-01, T3-02
**Plan refs:** FR-G.1, FR-G.2 · BR-5 · §7.2

## Steps
- [x] `/doctor/settings` page with a weekly grid: 7 rows (Sat first); each day lists its time ranges (start, end, remove) with an "Add range" button, and a "Day off" state when it has none.
- [x] Duration selector (15 / 30 / 45 / 60 / custom), booking window input and cancellation cut-off (hours) input.
- [x] Save buttons with success/error toasts; show 422 errors inline.

## Done when
- [x] The doctor adds a break to a day, changes duration and cut-off, reloads, and sees the saved values.
