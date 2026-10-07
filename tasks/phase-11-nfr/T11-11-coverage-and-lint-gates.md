# T11-11 — Coverage and lint gates, one `check` command

**Phase:** 11 · **Area:** DevOps · **Size:** M · **Depends on:** T11-03
**Plan refs:** §11.2 NFR-Q.1 – Q.4 · §9.1

## Steps
- [ ] Backend: install PCOV in Laragon's PHP 8.3 (and add it to the CI job in T7-08). `pest --coverage --min=80`; a second run on `app/Services` with `--min=95`. Add tests where they fall short.
- [ ] Fix Pint on the whole backend (`tests/Feature/HealthTest.php` fails today) so `pint --test` passes, not only `--dirty`.
- [ ] Frontend: `@vitest/coverage-v8`, thresholds in `vite.config.js` (lines 70, branches 60; `src/utils/**` and `src/hooks/**` 85). Add tests where they fall short.
- [ ] oxlint: fix the current warnings and run with `--deny-warnings`.
- [ ] `composer check` = Pint test + Pest with coverage + `composer audit`; `npm run check` = oxlint + Vitest with coverage + build + bundle size (T11-10) + `npm audit`. Use `--maxWorkers=4` for Vitest (parallel timeouts seen in Phase 10).
- [ ] Versioned pre-commit hook in `.githooks/pre-commit` (`git config core.hooksPath .githooks`, documented in the README): Pint on staged PHP, oxlint on staged JS, `vitest related` on staged files. Fast — no full suite.
- [ ] Give T7-08's CI the two `check` commands to run.

## Done when
- [ ] `composer check` and `npm run check` both exit 0 and fail when a threshold is broken (try once by lowering coverage on purpose).
- [ ] The hook blocks a commit with a lint error.
- [ ] Coverage numbers recorded here.
