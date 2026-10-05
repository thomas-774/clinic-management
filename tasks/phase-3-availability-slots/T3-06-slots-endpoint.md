# T3-06 — GET /slots endpoint

**Phase:** 3 · **Area:** Backend · **Size:** S · **Depends on:** T3-04
**Plan refs:** FR-E.2 · §6.3

## Steps
- [x] `GET /slots?date=YYYY-MM-DD` for patient and doctor roles.
- [x] Response: `{ data: [{ start_at, end_at }], meta: { date, duration } }`.
- [x] Validation: a valid date; 422 if missing or badly formatted.

## Done when
- [x] The endpoint returns the same slots as the unit tests for the seeded hours.
