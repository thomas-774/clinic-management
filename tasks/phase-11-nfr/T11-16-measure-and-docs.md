# T11-16 — Measure every NFR and update the docs

**Phase:** 11 · **Area:** Docs · **Size:** S · **Depends on:** T11-01 … T11-15
**Plan refs:** §11.2

## Steps
- [ ] Re-run every measurement: `clinic:bench` (P.1), query-count tests (P.2), bundle size (P.4), Lighthouse (P.5, U.2), coverage (Q.1, Q.2), `check` commands (Q.3, Q.4), audits (S.3), axe (U.1).
- [ ] Add a "Measured" column to the tables in §11.2 with the numbers and the date.
- [ ] Hand to Phase 7: the Nginx headers / CSP and PCOV for T7-05, `APP_KEY` handling for T7-07, the `check` commands for T7-08, the audit log and encryption for the UAT script in T7-09.
- [ ] Tick Phase 11 in §8 and in the task README.

## Done when
- [ ] Every NFR in §11.2 shows a measured value that meets its target, or a written exception in §11.3.
