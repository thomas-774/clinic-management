# T11-02 — Rate limits on writes, search and register

**Phase:** 11 · **Area:** Backend · **Size:** S · **Depends on:** —
**Plan refs:** §11.2 NFR-S.2 · §9.2

## Steps
- [x] Named limiters in `AppServiceProvider` next to `login`: `writes` (60/min per user id), `search` (120/min per user id), `register` (5/min per IP). Reuse the translated `{ message }` response the `login` limiter already returns.
- [x] Apply `throttle:writes` to every POST / PUT / PATCH / DELETE route group in `routes/api.php`; `throttle:search` to patient search, drug search and slots; `throttle:register` to `POST /register`.
  Note: `throttle:writes` sits on the whole authenticated group (and on logout); the `writes` limiter returns `Limit::none()` for GET / HEAD, so reads are never counted and no write route can be added without it. `throttle:search` is on `/slots`, the doctor's and the assistant's patient list (it holds the search) and both drug endpoints (`/drugs/search` and the Settings → Drugs list with `?search=`). All of a user's searches share one 120/min budget. The 429 message is the new JSON key "Too many requests. Please try again in :seconds seconds." (ar + en); login keeps `auth.throttle`.
- [x] Keep the numbers in `config/clinic.php` (`rate_limits.*`) so they can be tuned without code.
  Note: also `login` (5), each overridable by `CLINIC_RATE_LIMIT_*` in `.env`; the limiters read the config per request.

## Done when
- [x] Feature tests: the 61st write in a minute gets 429 with `Retry-After` and an Arabic / English message; a different user is not affected; register is limited by IP.
- [x] A route-list check (test) fails if a write route has no `throttle:` middleware.
- [x] Both test suites still pass.
