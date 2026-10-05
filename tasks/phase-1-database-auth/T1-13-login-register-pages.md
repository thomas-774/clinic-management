# T1-13 — Login and Register pages

**Phase:** 1 · **Area:** Frontend · **Size:** M · **Depends on:** T1-12
**Plan refs:** FR-A.1, FR-A.2 · §7.2

## Steps
- [x] Login form: phone/email + password; shows server errors.
- [x] Register form: name, phone, email (optional), password + confirmation, address; shows 422 field errors.
- [x] After success, redirect by role (`/patient` or `/doctor`).

## Done when
- [x] The seeded doctor and a newly registered patient can both log in and land in the right area.
