# T11-07 — OWASP ASVS Level 1 review

**Phase:** 11 · **Area:** Backend + Frontend · **Size:** M · **Depends on:** T11-01, T11-02, T11-06
**Plan refs:** §11.2 NFR-S.7 · §9.2

## Steps
- [ ] Go through every ASVS 4.0.3 Level 1 item and record pass / fail / not applicable with a one-line reason in `docs/security/asvs-l1.md`.
- [ ] IDOR sweep: a test that lists every route with an `{id}` / bound model and checks a user of each role can't read or change another patient's record (extends T7-01; reuse its helpers if T7-01 is done first).
- [ ] Mass assignment: every model uses `#[Fillable]` with no role / money / `doctor_id` field a client can set; a test posts extra fields to each write route and checks they are ignored.
- [ ] With `APP_DEBUG=false`: a forced 500 returns `{ message }` only, no stack trace or SQL.
- [ ] Token storage: record the risk of the token in `localStorage` (XSS reads it) and why the CSP in T11-01 is the mitigation for now.
- [ ] Fix every fail, or write it into §11.3 as accepted with the reason.

## Done when
- [ ] `docs/security/asvs-l1.md` has no open fail.
- [ ] The IDOR and mass-assignment tests pass.
- [ ] Both test suites still pass.
