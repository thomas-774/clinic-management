# T3-09 — Frontend: live slot preview

**Phase:** 3 · **Area:** Frontend · **Size:** S · **Depends on:** T3-07
**Plan refs:** §7.3 Settings

## Steps
- [x] `utils/previewSlots(ranges, duration)`: same loop as §4.1 step 3, run for each range (no bookings or blocks).
- [x] Under each active day, show the chips the current (unsaved) values would produce, plus the slot count.
- [x] Vitest: 45 / 60 / 30 min examples from §4.1, plus the two-range example with a break.

## Done when
- [x] Changing the duration updates the preview instantly, before saving.
