# T11-02 — Rate limits on writes, search and register

**Phase:** 11 · **Area:** Backend · **Size:** S · **Depends on:** —
**Plan refs:** §11.2 NFR-S.2 · §9.2

## Steps
- [ ] Named limiters in `AppServiceProvider` next to `login`: `writes` (60/min per user id), `search` (120/min per user id), `register` (5/min per IP). Reuse the translated `{ message }` response the `login` limiter already returns.
- [ ] Apply `throttle:writes` to every POST / PUT / PATCH / DELETE route group in `routes/api.php`; `throttle:search` to patient search, drug search and slots; `throttle:register` to `POST /register`.
- [ ] Keep the numbers in `config/clinic.php` (`rate_limits.*`) so they can be tuned without code.

## Done when
- [ ] Feature tests: the 61st write in a minute gets 429 with `Retry-After` and an Arabic / English message; a different user is not affected; register is limited by IP.
- [ ] A route-list check (test) fails if a write route has no `throttle:` middleware.
- [ ] Both test suites still pass.
