# T3-04 — SlotService::generate()

**Phase:** 3 · **Area:** Backend · **Size:** M · **Depends on:** T3-01, T3-02, T3-03
**Plan refs:** §4.1 (steps 1–6) · FR-G.4

## Steps
- [ ] `SlotService::generate(Carbon $date): array` of `[start, end]` pairs, following §4.1:
  1. Working-hour ranges for that weekday; return empty if there are none or the whole day is blocked.
  2. Read duration D.
  3. For each range separately, loop while `cursor + D <= end_time` (slots never cross a break).
  4. Drop overlaps with appointments in status booked / checked_in / completed.
  5. Drop overlaps with blocked time ranges.
  6. If the date is today, drop slots that have already started.
- [ ] Also return empty for dates in the past or beyond `booking_window_days`.
- [ ] Use an **overlap** check (`a.start < b.end && b.start < a.end`), not just equal start times, so old bookings made with a different duration are respected (FR-G.4).
- [ ] `SlotService::isAvailable(Carbon $start): bool` for booking validation (BR-1).

## Done when
- [ ] T3-05 tests pass.
