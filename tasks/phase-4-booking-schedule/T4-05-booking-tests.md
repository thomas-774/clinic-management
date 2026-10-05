# T4-05 — Tests: booking rules and double booking

**Phase:** 4 · **Area:** Backend / Tests · **Size:** M · **Depends on:** T4-02, T4-03
**Plan refs:** §4.2 · §9.1 Concurrency

## Test cases
- [x] Booking a valid slot → 201, with `end_at` = start + duration.
- [x] Booking a time that is not a generated slot (e.g. 17:10) → 409.
- [x] Booking a past slot or a day off → 409.
- [x] Second future booking by the same patient → 422 (BR-4).
- [x] With `clinic.max_active_appointments` set to 2 in the test, a second booking → 201 and a third → 422.
- [x] Two patients booking the same slot → the first gets 201, the second 409.
- [x] Simulate the race: insert a conflicting row after validation but before insert (mock or DB hook) → the unique index produces 409, not 500.
- [x] Cancel, then rebook the same slot by another patient → 201.
- [x] Cancel 3 h before start with a 2 h cut-off → 200; cancel 1 h before start → 422; cancel after start → 422.
- [x] The doctor can still cancel inside the cut-off window → 200.

## Done when
- [x] All booking tests pass.
