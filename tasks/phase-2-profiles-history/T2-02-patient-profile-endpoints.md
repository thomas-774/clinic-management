# T2-02 — Patient profile endpoints

**Phase:** 2 · **Area:** Backend · **Size:** M · **Depends on:** T2-01
**Plan refs:** FR-B.1 – FR-B.3, FR-B.5 · §6.3

## Steps
- [x] `GET /patient/profile`: name, phone, address, current illness, simple history (only `patient_visible = true`, newest first).
- [x] Add `next_appointment` and `outstanding_balance` keys returning `null` / `0` for now (filled in by T4-11 and T5-07).
- [x] `PATCH /patient/profile` with `UpdateOwnProfileRequest`: only `phone` (unique) and `address`; any other field is ignored.

## Done when
- [x] A feature test shows a patient cannot change `current_illness` or `name` through PATCH.
