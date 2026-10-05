# T1-12 — AuthContext, ProtectedRoute, RoleRoute

**Phase:** 1 · **Area:** Frontend · **Size:** M · **Depends on:** T1-11
**Plan refs:** FR-A.3 · §7.1 auth/

## Steps
- [x] `AuthContext`: `user`, `role`, `login()`, `logout()`, `loading`; loads `/me` on app start when a token exists.
- [x] `ProtectedRoute`: redirects unauthenticated users to `/login`.
- [x] `RoleRoute`: allows only the given role and sends anyone else to their own home (`/patient` or `/doctor`).
- [x] Router skeleton with every route from §7.2 (placeholder pages).

## Done when
- [x] A patient opening `/doctor` is redirected to `/patient`, and the reverse.
