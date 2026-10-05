# T8-12 — Frontend: Settings → Staff (doctor)

**Phase:** 8 · **Area:** Frontend · **Size:** S · **Depends on:** T8-02
**Plan refs:** §7.2 · FR-I.1

## Steps
- [ ] New section in `pages/doctor/settings/`: assistants table (name, phone, active), Add (initial password shown once), Edit, Activate/Deactivate (with `ConfirmDialog`), Reset password.
- [ ] ar + en keys.

## Done when
- [ ] Vitest: creating an assistant shows the password once; Deactivate calls PUT with `is_active: false`.
