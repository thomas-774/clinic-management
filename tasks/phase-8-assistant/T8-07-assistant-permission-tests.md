# T8-07 — Tests: assistant permissions and privacy

**Phase:** 8 · **Area:** Backend / Tests · **Size:** M · **Depends on:** T8-02 … T8-06
**Plan refs:** §9.1 · §9.2 · FR-I.1 – I.6

## Test cases
- [x] Assistant → every `/doctor/*` route → 403 (data-driven over the route list).
- [x] Doctor and patient → every `/assistant/*` route → 403.
- [x] No `/assistant/*` response contains `work_done`, `current_illness`, `history` or private history text.
- [x] Assistant cases added to `RoleMiddlewareTest` and `PatientPolicyTest`.
  Note: `PatientPolicyTest` gained its assistant case in T8-04; the inactive-assistant cases live in `Auth/AccountStatusTest.php` (T8-01).
- [x] Inactive assistant: login fails, old token → 401.
- [x] End-to-end API flow: assistant creates a patient → books → Arrived → doctor saves the visit (total 1500, paid 0) → assistant sees it waiting → pays 1000 → remaining 500 → the doctor's report shows 1000 recorded by the assistant.

## Done when
- [x] All cases green with the full suite.
