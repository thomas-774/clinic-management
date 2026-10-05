# T5-06 — Feature tests: visits and payments

**Phase:** 5 · **Area:** Backend / Tests · **Size:** M · **Depends on:** T5-03, T5-04, T5-05
**Plan refs:** §8 Phase 5 · §9.1

## Test cases
- [x] Create a visit 1500 / 1000 → 201, remaining 500, appointment completed.
- [x] Add a payment of 500 → `paid`.
- [x] Add a payment of 600 on a 500 balance → 422.
- [x] Walk-in visit (no appointment) → 201.
- [x] Second visit for the same appointment → 422.
- [x] Patient token on any `/doctor/visits` route → 403.
- [x] `remaining` in the request body is ignored (the server always computes it).

## Done when
- [x] All tests pass.
