# T7-01 — Feature tests: permissions on every endpoint

**Phase:** 7 · **Area:** Backend / Tests · **Size:** M · **Depends on:** all backend tasks
**Plan refs:** §9.1 Feature · §9.2

## Steps
- [ ] A dataset-driven test listing every route from §6.3, checking:
  - [ ] No token → 401.
  - [ ] Wrong role → 403.
  - [ ] Doctor routes reject patients; patient routes reject the doctor.
- [ ] Happy path + one 422 case for every write endpoint not already covered.
- [ ] Confirm no patient endpoint ever returns a private history entry or `details`.

## Done when
- [ ] Every route in §6.3 appears in at least one test.
