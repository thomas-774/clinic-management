# T8-01 — Assistant role and account status

**Phase:** 8 · **Area:** Backend · **Size:** S · **Depends on:** T1-09, T1-10
**Plan refs:** §2 · §5.1 users · FR-I.1

## Steps
- [x] New migration: `users.role` enum → `('patient','doctor','assistant')`; add `users.is_active` BOOLEAN default true.
- [x] `UserRole::Assistant`; `User::isAssistant()`; `is_active` cast to bool; factory states `->assistant()` and `->inactive()`.
- [x] `AuthController@login`: an inactive user gets the same generic "wrong credentials" error as a wrong password.
- [x] A request with the token of a user who is no longer active is rejected (401).
  Note: done with `Sanctum::authenticateAccessTokensUsing` in AppServiceProvider, so a deactivated account is cut off at once; T8-02 also deletes the tokens.
- [x] Login and `/me` return the `assistant` role.

## Done when
- [x] Assistant can log in and `/me` returns `role: assistant`.
- [x] Inactive user cannot log in; an existing token stops working.
- [x] All existing tests still pass.
