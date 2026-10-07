# T11-03 — Dependency audit (composer + npm)

**Phase:** 11 · **Area:** DevOps · **Size:** S · **Depends on:** —
**Plan refs:** §11.2 NFR-S.3

## Steps
- [x] Run `composer audit` in `backend/` and `npm audit --omit=dev --audit-level=high` in `frontend/`; record the findings in this file.
  Findings (Oct 7, 2026, Composer 2.10.2 / npm 12.0.1):
  - `composer audit`: "No security vulnerability advisories found."; no abandoned packages reported.
  - `npm audit --omit=dev --audit-level=high`: "found 0 vulnerabilities". The full `npm audit` (dev dependencies included) also finds 0.
- [x] Fix every high / critical one (update within the current major, or record why it does not apply). Run both test suites after each update.
  Note: nothing to fix, so no package was updated.
- [x] Add `audit` scripts to both apps so T11-11's `check` and T7-08's CI can call them.
  Note: named `audit:deps` in both, because Composer does not allow a script with the name of its own `audit` command. `composer audit:deps` runs `composer audit --locked` (checks `composer.lock`, works without `vendor/`); `npm run audit:deps` runs `npm audit --omit=dev --audit-level=high` through `frontend/scripts/audit-deps.mjs`, which drops the inherited `npm_config_*` variables — npm 12 rejects an inherited `allow-scripts` setting inside `npm run` (seen on the dev PC, whose global npm config sets it).
- [x] Turn on Dependabot (or Renovate) for composer and npm, weekly, grouped — config file only; it acts once the repo is on GitHub (T0-02).
  Note: `.github/dependabot.yml`: `/backend` (composer) and `/frontend` (npm), weekly on Saturday (Cairo), minor + patch updates grouped into one PR per app; major updates come as separate PRs.

## Done when
- [x] Both audits report no high or critical issues.
- [x] Both test suites still pass.
