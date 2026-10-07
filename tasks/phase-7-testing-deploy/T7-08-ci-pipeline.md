# T7-08 — CI with GitHub Actions (optional)

**Phase:** 7 · **Area:** DevOps · **Size:** S · **Depends on:** T0-06
**Plan refs:** §9.3 CI

## Steps
- [ ] Workflow on pull requests: backend job (PHP 8.3 + MySQL service, `php artisan test`) and frontend job (`npm ci`, `npm run test`, `npm run build`).
  Since T11-11 each job runs one command that holds every gate: backend `composer check` (Pint, Pest with coverage ≥ 80 % / `app/Services` ≥ 95 %, `composer audit`), frontend `npm run check` (oxlint, Vitest with coverage, build, bundle budget, `npm audit`). The backend job needs PCOV: `shivammathur/setup-php@v2` with `php-version: '8.3'` and `coverage: pcov`, plus the `clinic_testing` database on the MySQL service. Upload `backend/build/coverage` and `frontend/coverage` as artifacts if wanted.
- [ ] Require CI to pass before merging into `main`.

## Done when
- [ ] A PR shows both jobs green.
