# T8-01 — Assistant role and account status

**Phase:** 8 · **Area:** Backend · **Size:** S · **Depends on:** T1-09, T1-10
**Plan refs:** §2 · §5.1 users · FR-I.1

## Steps
- [ ] New migration: `users.role` enum → `('patient','doctor','assistant')`; add `users.is_active` BOOLEAN default true.
- [ ] `UserRole::Assistant`; `User::isAssistant()`; `is_active` cast to bool; factory states `->assistant()` and `->inactive()`.
- [ ] `AuthController@login`: an inactive user gets the same generic "wrong credentials" error as a wrong password.
- [ ] A request with the token of a user who is no longer active is rejected (401).
- [ ] Login and `/me` return the `assistant` role.

## Done when
- [ ] Assistant can log in and `/me` returns `role: assistant`.
- [ ] Inactive user cannot log in; an existing token stops working.
- [ ] All existing tests still pass.
