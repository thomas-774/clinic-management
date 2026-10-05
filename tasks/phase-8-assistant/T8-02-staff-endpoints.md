# T8-02 — Doctor: staff (assistant) endpoints

**Phase:** 8 · **Area:** Backend · **Size:** M · **Depends on:** T8-01
**Plan refs:** §6.3 · FR-I.1

## Steps
- [x] Extract `initialPassword()` from `Doctor/PatientController` into a small reusable helper (trait or service) and use it in both places.
  Note: `App\Support\InitialPassword::generate()`.
- [x] `Doctor/StaffController`: `GET /doctor/staff` (assistants only), `POST /doctor/staff` (name, phone, optional email → returns `initial_password` once), `PUT /doctor/staff/{user}` (name, phone, `is_active`, `reset_password` flag → returns the new password once).
  Note: every PUT field is optional, so the Activate switch can send `is_active` alone; the list shows active assistants first.
- [x] Form requests reuse `ValidatesPhone`; phone unique across users.
- [x] `{user}` must be an assistant (404 otherwise — patients and the doctor cannot be edited here).
- [x] Deactivating or resetting the password revokes the assistant's tokens.
- [x] Localized messages in ar + en.

## Done when
- [x] Feature tests: create, list, edit, deactivate (tokens gone), reset password; patient or assistant calling `/doctor/staff` → 403.
