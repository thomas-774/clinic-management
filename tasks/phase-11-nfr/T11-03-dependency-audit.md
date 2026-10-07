# T11-03 — Dependency audit (composer + npm)

**Phase:** 11 · **Area:** DevOps · **Size:** S · **Depends on:** —
**Plan refs:** §11.2 NFR-S.3

## Steps
- [ ] Run `composer audit` in `backend/` and `npm audit --omit=dev --audit-level=high` in `frontend/`; record the findings in this file.
- [ ] Fix every high / critical one (update within the current major, or record why it does not apply). Run both test suites after each update.
- [ ] Add `audit` scripts to both apps so T11-11's `check` and T7-08's CI can call them.
- [ ] Turn on Dependabot (or Renovate) for composer and npm, weekly, grouped — config file only; it acts once the repo is on GitHub (T0-02).

## Done when
- [ ] Both audits report no high or critical issues.
- [ ] Both test suites still pass.
