# T1-09 — Auth endpoints (register, login, logout, me)

**Phase:** 1 · **Area:** Backend · **Size:** M · **Depends on:** T1-06, T1-08
**Plan refs:** FR-A.1 – FR-A.3 · §6.3 · §9.2

## Steps
- [ ] `RegisterRequest`: name, phone (unique), email (optional, unique), password (confirmed, min 8), address.
- [ ] `POST /auth/register`: creates the User (role patient) and the Patient in one transaction; returns token + user.
- [ ] `POST /auth/login`: accepts phone **or** email + password; returns a Sanctum token + role.
- [ ] `POST /auth/logout`: revokes the current token.
- [ ] `GET /me`: current user + role (+ patient id for patients).
- [ ] Rate-limit login to 5 attempts per minute.
- [ ] Feature tests: register, login by phone, login by email, wrong password, logout, rate limit.

## Done when
- [ ] All auth feature tests pass.
