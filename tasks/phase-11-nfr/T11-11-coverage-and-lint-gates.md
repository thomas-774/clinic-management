# T11-11 — Coverage and lint gates, one `check` command

**Phase:** 11 · **Area:** DevOps · **Size:** M · **Depends on:** T11-03
**Plan refs:** §11.2 NFR-Q.1 – Q.4 · §9.1

## Steps
- [x] Backend: install PCOV in Laragon's PHP 8.3 (and add it to the CI job in T7-08). `pest --coverage --min=80`; a second run on `app/Services` with `--min=95`. Add tests where they fall short.
  Note: PCOV 1.0.12 (`php_pcov-1.0.12-8.3-ts-vs16-x64`, Laragon's PHP is the thread-safe build) in `ext/`, `extension=pcov` in its `php.ini`; steps in the README. Instead of running the suite twice (75 s each), `composer check` runs it once with `--min=80 --coverage-clover=build/coverage/clover.xml`, and `scripts/check-coverage.php` checks `app/Services` ≥ 95 % from that report. Nothing fell short, so no tests were added.
- [x] Fix Pint on the whole backend (`tests/Feature/HealthTest.php` fails today) so `pint --test` passes, not only `--dirty`.
- [x] Frontend: `@vitest/coverage-v8`, thresholds in `vite.config.js` (lines 70, branches 60; `src/utils/**` and `src/hooks/**` 85). Add tests where they fall short.
  Note: the 85 applies to lines, branches, functions and statements of each of the two folders. Nothing fell short.
- [x] oxlint: fix the current warnings and run with `--deny-warnings`.
  Note: there were none left; `npm run lint` is now `oxlint --deny-warnings --format=default` (`--format=default` because oxlint prints nothing when its output is not a terminal).
- [x] `composer check` = Pint test + Pest with coverage + `composer audit`; `npm run check` = oxlint + Vitest with coverage + build + bundle size (T11-10) + `npm audit`. Use `--maxWorkers=4` for Vitest (parallel timeouts seen in Phase 10).
  Note: `composer check` also clears the config cache first, as `composer test` does, and has no process timeout. New npm scripts: `test:coverage`, `check`.
- [x] Versioned pre-commit hook in `.githooks/pre-commit` (`git config core.hooksPath .githooks`, documented in the README): Pint on staged PHP, oxlint on staged JS, `vitest related` on staged files. Fast — no full suite.
  Note: `PHP=/path/to/php.exe` when `php` is not on the PATH of Git's shell (Laragon); without PHP it skips Pint with a warning rather than block every commit. `core.hooksPath` is set in this clone.
- [x] Give T7-08's CI the two `check` commands to run.
  Note: T7-08 is not built yet; its task file now says to run `composer check` / `npm run check` and to set up PHP with `coverage: pcov`.

## Coverage (Oct 7, 2026)

| | Statements | Branches | Functions | Lines |
| --- | --- | --- | --- | --- |
| Backend, `app/` (Pest + PCOV) | — | — | — | 98.9 % (min 80) |
| Backend, `app/Services` | — | — | — | 99.2 %, 541 / 545 (min 95) |
| Frontend, `src/` (Vitest + V8) | 92.6 % | 87.9 % (min 60) | 88.2 % | 96.0 % (min 70) |
| Frontend, `src/utils` | 97.8 % | 89.7 % | 100 % | 100 % (all min 85) |
| Frontend, `src/hooks` | 98.0 % | 95.7 % | 99.3 % | 98.2 % (all min 85) |

## Done when
- [x] `composer check` and `npm run check` both exit 0 and fail when a threshold is broken (try once by lowering coverage on purpose).
  Note: tried with an untested 40-method class in `app/Services` (`app/Services` fell to 86.5 %, `composer check` exit 1), Pest `--min=80` on a filtered run (1.9 %, exit 1), and an untested file in `src/utils` (`npm run test:coverage` exit 1 on all four `src/utils/**` thresholds). Removed afterwards.
- [x] The hook blocks a commit with a lint error.
  Note: blocked a staged JS file with `debugger` and an unused variable (oxlint warnings under `--deny-warnings`) and a staged PHP file Pint would reformat; both commits exited 1.
- [x] Coverage numbers recorded here.
