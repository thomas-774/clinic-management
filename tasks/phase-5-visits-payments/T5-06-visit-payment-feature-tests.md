# T5-06 — Feature tests: visits and payments

**Phase:** 5 · **Area:** Backend / Tests · **Size:** M · **Depends on:** T5-03, T5-04, T5-05
**Plan refs:** §8 Phase 5 · §9.1

## Test cases
- [ ] Create a visit 1500 / 1000 → 201, remaining 500, appointment completed.
- [ ] Add a payment of 500 → `paid`.
- [ ] Add a payment of 600 on a 500 balance → 422.
- [ ] Walk-in visit (no appointment) → 201.
- [ ] Second visit for the same appointment → 422.
- [ ] Patient token on any `/doctor/visits` route → 403.
- [ ] `remaining` in the request body is ignored (the server always computes it).

## Done when
- [ ] All tests pass.
