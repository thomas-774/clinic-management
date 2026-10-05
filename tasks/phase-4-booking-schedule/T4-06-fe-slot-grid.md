# T4-06 — Frontend: SlotGrid component and useSlots hook

**Phase:** 4 · **Area:** Frontend · **Size:** S · **Depends on:** T3-06
**Plan refs:** §7.3 SlotGrid

## Steps
- [x] `useSlots(date)` hook (TanStack Query, key `['slots', date]`).
- [x] `SlotGrid`: a button per slot (formatted time), selected state, loading skeleton, "No free slots on this day" empty state.
- [x] `utils/formatTime`, `utils/formatDate` with dayjs.
  - Note: `formatTime` / `formatDate` already existed in `utils/format.js` (Intl, Cairo time zone, from T2-07) and are reused; dayjs does the calendar maths in `utils/dates.js` (`addDays`, `weekStart`, `dateRange`).

## Done when
- [x] The grid renders slots for a chosen date and highlights the selected one.
