# T7-08 — CI with GitHub Actions (optional)

**Phase:** 7 · **Area:** DevOps · **Size:** S · **Depends on:** T0-06
**Plan refs:** §9.3 CI

## Steps
- [ ] Workflow on pull requests: backend job (PHP 8.3 + MySQL service, `php artisan test`) and frontend job (`npm ci`, `npm run test`, `npm run build`).
- [ ] Require CI to pass before merging into `main`.

## Done when
- [ ] A PR shows both jobs green.
