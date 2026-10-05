# T3-02 — Working hours endpoints

**Phase:** 3 · **Area:** Backend · **Size:** M · **Depends on:** T1-10
**Plan refs:** FR-G.1 · §6.3

## Steps
- [x] `GET /doctor/working-hours`: always returns 7 days as `{ day_of_week, ranges: [{ start_time, end_time }] }`, sorted by start time; a day with no rows has `ranges: []` (day off).
- [x] `PUT /doctor/working-hours`: same shape as GET; inside a transaction, delete the doctor's rows and insert one row per range.
- [x] Validation: `end_time > start_time` for each range; ranges on the same day must not overlap; `day_of_week` 0–6 with no duplicates.

## Done when
- [x] Saving Sat–Thu 17:00–21:00, Tuesday 10:00–13:00 + 17:00–21:00, and Friday off round-trips correctly; `end < start` or overlapping ranges return 422.
