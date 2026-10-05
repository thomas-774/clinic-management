# T6-01 — Period helper and week-start config

**Phase:** 6 · **Area:** Backend · **Size:** S · **Depends on:** T1-06
**Plan refs:** §8 Phase 6 (week starts Saturday)

## Steps
- [x] Add `config/clinic.php` with `week_start => Carbon::SATURDAY`, `currency => 'EGP'`.
- [x] `Period::for('day'|'week'|'month', ?Carbon $ref): [from, to]` in Africa/Cairo time.
- [x] Unit tests: a Friday belongs to the week that started the previous Saturday; month boundaries (31st → 1st).

## Done when
- [x] Period tests pass.
