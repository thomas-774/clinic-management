# T1-11 — Frontend API client with token interceptor

**Phase:** 1 · **Area:** Frontend · **Size:** S · **Depends on:** T0-05, T1-09
**Plan refs:** §7.1 api/

## Steps
- [ ] `api/client.js`: a request interceptor adds `Authorization: Bearer <token>` from storage and `Accept-Language` from the current i18n language.
- [ ] Response interceptor: on 401, clear the token and redirect to `/login`.
- [ ] `api/auth.js`: `register`, `login`, `logout`, `me`.

## Done when
- [ ] Requests from a logged-in user carry the token, and an expired token sends the user to `/login`.
