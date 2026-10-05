# T1-09 — Auth endpoints (register, login, logout, me)

**Phase:** 1 · **Area:** Backend · **Size:** M · **Depends on:** T1-06, T1-08
**Plan refs:** FR-A.1 – FR-A.3 · §6.3 · §9.2

## Steps
- [x] `RegisterRequest`: name, phone (unique), email (optional, unique), password (confirmed, min 8), address.
- [x] `POST /auth/register`: creates the User (role patient) and the Patient in one transaction; returns token + user.
- [x] `POST /auth/login`: accepts phone **or** email + password; returns a Sanctum token + role.
- [x] `POST /auth/logout`: revokes the current token.
- [x] `GET /me`: current user + role (+ patient id for patients).
- [x] Rate-limit login to 5 attempts per minute.
- [x] Feature tests: register, login by phone, login by email, wrong password, logout, rate limit.

## Done when
- [x] All auth feature tests pass.
