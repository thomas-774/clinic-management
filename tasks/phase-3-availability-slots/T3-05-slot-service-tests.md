# T3-05 — Unit tests for SlotService

**Phase:** 3 · **Area:** Backend / Tests · **Size:** M · **Depends on:** T3-04
**Plan refs:** §4.1 worked examples · §9.1

## Test cases (hours 17:00–21:00)
- [ ] 45 min → 17:00, 17:45, 18:30, 19:15, 20:00 (20:45 is dropped).
- [ ] 60 min → 17:00, 18:00, 19:00, 20:00.
- [ ] 30 min → 8 slots, 17:00 … 20:30.
- [ ] Two ranges 10:00–13:00 and 17:00–21:00 at 60 min → 10:00, 11:00, 12:00, 17:00, 18:00, 19:00, 20:00.
- [ ] Two ranges 10:00–12:30 and 17:00–21:00 at 45 min → 10:00, 10:45, 11:30 (12:15 dropped), then 17:00 … 20:00.
- [ ] Day off (Friday, no rows) → empty.
- [ ] Whole-day block → empty.
- [ ] Time-range block 18:00–19:00 at 60 min → 17:00, 19:00, 20:00.
- [ ] An existing booked 17:45 appointment removes that slot; a cancelled one does not.
- [ ] Today at 18:10 (use `Carbon::setTestNow`) → only slots after 18:10.
- [ ] Old 60-min booking at 18:00 with duration now 45 → the overlapping 17:45 and 18:30 are removed.
- [ ] Date beyond the booking window → empty.

## Done when
- [ ] All cases are green.
