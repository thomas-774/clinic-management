# T1-08 — API response and error conventions

**Phase:** 1 · **Area:** Backend · **Size:** S · **Depends on:** T0-03
**Plan refs:** §6.2 Responses, Time zone

## Steps
- [ ] Every successful response goes through an API Resource with the shape `{ data, message }`.
- [ ] Validation errors return 422 `{ message, errors: { field: [...] } }` (Laravel default; confirm it).
- [ ] 401 / 403 / 404 / 409 return JSON, not HTML, for `/api/*` (exception handling in `bootstrap/app.php`).
- [ ] Dates are serialized as ISO 8601 in Africa/Cairo time.
- [ ] `SetLocale` middleware on `/api/*`: reads `Accept-Language` (`ar` | `en`, default `ar`) and sets the app locale; add Arabic translation files for validation messages (e.g. `laravel-lang/lang`) so 422 messages come back in Arabic.
- [ ] Create `config/clinic.php` with `max_active_appointments` => 1 (used by T4-01).

## Done when
- [ ] Calling a protected route without a token returns a JSON 401.
- [ ] A validation error with `Accept-Language: ar` returns Arabic messages, and with `en` returns English messages.
