# T1-10 — EnsureRole middleware and route groups

**Phase:** 1 · **Area:** Backend · **Size:** S · **Depends on:** T1-09
**Plan refs:** §2 · §6.2 Roles

## Steps
- [x] `EnsureRole` middleware registered under the `role` alias (`role:doctor`, `role:patient`).
- [x] `routes/api.php`: group `/api/v1/patient/*` (auth:sanctum + role:patient) and `/api/v1/doctor/*` (auth:sanctum + role:doctor).
- [x] Feature test: a patient token on a doctor route gets 403, and the reverse.

## Done when
- [x] The role tests pass.
