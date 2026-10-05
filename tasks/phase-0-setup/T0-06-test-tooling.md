# T0-06 — Set up test tooling

**Phase:** 0 · **Area:** Tooling · **Size:** S · **Depends on:** T0-03, T0-04
**Plan refs:** §9.1

## Steps
- [ ] Backend: install Pest; configure a separate MySQL test database in `.env.testing` / `phpunit.xml`.
- [ ] Backend: one sample feature test hitting `/api/v1/health`.
- [ ] Frontend: install Vitest + React Testing Library + jsdom; one sample component test.
- [ ] Add `test` scripts to README.

## Done when
- [ ] `php artisan test` and `npm run test` both pass.
