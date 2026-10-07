# T11-07 — OWASP ASVS Level 1 review

**Phase:** 11 · **Area:** Backend + Frontend · **Size:** M · **Depends on:** T11-01, T11-02, T11-06
**Plan refs:** §11.2 NFR-S.7 · §9.2

## Steps
- [x] Go through every ASVS 4.0.3 Level 1 item and record pass / fail / not applicable with a one-line reason in `docs/security/asvs-l1.md`.
  Note: besides Pass / N/A / Accepted, the file uses "Pass (fixed)" for items fixed here and "Deploy" for items met by a server setting, which were added to the T7-05 checklist (TLS versions and ciphers, dotfiles, body size, `APP_DEBUG=false`, access-log retention).
- [x] IDOR sweep: a test that lists every route with an `{id}` / bound model and checks a user of each role can't read or change another patient's record (extends T7-01; reuse its helpers if T7-01 is done first).
  Note: `tests/Feature/Security/IdorSweepTest.php` (T7-01 is not done yet, so it has its own helpers). It fails when a new id route is added without being listed. In one clinic the doctor and the assistant may open any patient (§2), so for them the sweep checks the other area (403) and records owned by another doctor, a non-assistant on `/staff/{id}` and a history entry under the wrong patient (404); the routes they may use on any patient are listed in `CLINIC_WIDE`.
- [x] Mass assignment: every model uses `#[Fillable]` with no role / money / `doctor_id` field a client can set; a test posts extra fields to each write route and checks they are ignored.
  Note: `role`, `doctor_id`, `total_amount` etc. stay in `#[Fillable]` because the server's own code sets them; what keeps them out of a client's reach is that every controller passes only `validated()` / `safe()->only()`. `tests/Feature/Security/MassAssignmentTest.php` covers all 27 POST / PUT / PATCH routes (and fails when a new one is added) and was checked by breaking two controllers on purpose.
- [x] With `APP_DEBUG=false`: a forced 500 returns `{ message }` only, no stack trace or SQL. (`tests/Feature/Security/ErrorDisclosureTest.php`)
- [x] Token storage: record the risk of the token in `localStorage` (XSS reads it) and why the CSP in T11-01 is the mitigation for now. ("Token storage" in `docs/security/asvs-l1.md`; A-4 in §11.3.)
- [x] Fix every fail, or write it into §11.3 as accepted with the reason.
  Note: fixed — the Word visit file did not escape XML (a `&` in the work done made a corrupt file; found by this review), no upper limit on the register password (`max:128`), no way to show the password (`PasswordField` on Login / Register), no `Content-Disposition` and no CSP on API responses (`SecurityHeaders`). Accepted in §11.3 as A-1 … A-5: password length 8 / no breach check / no meter, bcrypt's 72-byte limit, no account self-service (change password, forced change of an initial password, notifications, re-auth for a phone change), the token in `localStorage`, and no MFA / data export / privacy notice in v1.

## Done when
- [x] `docs/security/asvs-l1.md` has no open fail.
- [x] The IDOR and mass-assignment tests pass.
- [x] Both test suites still pass.
