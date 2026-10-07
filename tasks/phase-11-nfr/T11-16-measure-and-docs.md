# T11-16 — Measure every NFR and update the docs

**Phase:** 11 · **Area:** Docs · **Size:** S · **Depends on:** T11-01 … T11-15
**Plan refs:** §11.2

## Steps
- [x] Re-run every measurement: `clinic:bench` (P.1), query-count tests (P.2), bundle size (P.4), Lighthouse (P.5, U.2), coverage (Q.1, Q.2), `check` commands (Q.3, Q.4), audits (S.3), axe (U.1).
  - Note: all measured on Oct 7, 2026 on the dev machine (results below). P.3 was measured again too: an EXPLAIN of every query behind the benchmarked endpoints. The security NFRs were measured by their feature tests. NFR-S.1's SPA half had not been measured before (T11-01's open browser check). `npm run a11y:audit` now also counts CSP violations on every page and dialog and fails on any.
- [x] Add a "Measured" column to the tables in §11.2 with the numbers and the date.
- [x] Hand to Phase 7: the Nginx headers / CSP and PCOV for T7-05, `APP_KEY` handling for T7-07, the `check` commands for T7-08, the audit log and encryption for the UAT script in T7-09.
  - Note: the Nginx headers / CSP (T11-01), `APP_KEY` (T11-06, in T7-05 and T7-07) and the `check` commands (T11-11, T7-08) were already handed over by their tasks. Added now: in T7-05, how to check the deployed CSP, the Laravel scheduler cron (nothing in Phase 7 set it up, and `audit:prune` needs it), and "no PCOV on the servers"; in T7-08, a note on the browser audits; in T7-09, a UAT step for the audit log and the encryption, and a step for the checks Phase 11 left for a person (NVDA, the download under the CSP, the Activity walkthrough).
- [x] Tick Phase 11 in §8 and in the task README.
  - Note: the README has no phase checkbox. It gets a status line under Phase 11 and T11-16 ticked. T11-01 and T11-05 stay unticked: each has one logged-in browser check left, now in T7-09.

## Done when
- [x] Every NFR in §11.2 shows a measured value that meets its target, or a written exception in §11.3.
  - Note: all 20 meet their target. Two have a written exception in §11.3. NFR-P.3: patient search reads every `users` row, because `LIKE '%term%'` cannot use an index; p95 22.6 ms against 300. NFR-U.1: the NVDA pass needs a person.

## Results (Oct 7, 2026)

| NFR | How | Result |
| --- | --- | --- |
| P.1 | `clinic:bench` on `clinic_bench` (10,000 patients, 54,523 visits, 70,407 payments), `config:cache` + `route:cache`, 100 calls per endpoint (how to run: T11-09) | 20 endpoints OK. Slowest: report outstanding p50 135.7 / p95 148.8 / p99 155.2 ms. Every other endpoint p95 ≤ 22.6 ms. Export PDF p95 68.7 ms, Word 47.6 ms |
| P.2 | `tests/Feature/Performance/QueryCountTest.php` | 23 endpoints, same query count at 5 and 50 rows |
| P.3 | `clinic:bench --iterations=1` with every SELECT captured, then `EXPLAIN` on the same dataset (script kept outside the repo, as in T11-08) | 105 distinct queries. Full scans on growing tables only in the benchmark's own setup queries (picking random test users and patients) and in patient search (`users`, 9,951 rows). Drug search reads its 128-row catalogue |
| P.4 | `npm run check:size` (in `npm run check`) | Patient pages 162.6 kB gzip in 19 files (budget 200); largest chunk `Reports` 363.3 kB (budget 500) |
| P.5, U.2 | `npm run mobile:audit` (Lighthouse 13.5, Moto G Power, Slow 4G) | Performance 98, Accessibility 100, Best Practices 100 on all 10 runs; LCP 1.89 – 2.10 s; CLS ≤ 0.003; no sideways scroll, small tap target or small input at 360 px |
| Q.1 | `composer check` | 792 tests; lines 98.9 %, `app/Services` 99.2 % |
| Q.2 | `npm run check` (Vitest V8) | 313 tests; lines 95.97 %, branches 87.69 %; `src/utils` 100 % / 88.33 %; `src/hooks` 98.24 % / 95.65 % |
| Q.3, Q.4 | `composer check`, `npm run check` | Both exit 0; Pint clean; oxlint "Found 0 warnings and 0 errors" |
| S.1 | `SecurityHeadersTest` (10 runs, 7 cases); `npm run a11y:audit` | API headers pass. SPA: 0 CSP violations on 46 page runs and 4 dialogs |
| S.2 | `RateLimitsTest` | 12 runs (7 cases) pass |
| S.3 | `composer audit`, `npm audit --omit=dev --audit-level=high` | No advisories; 0 vulnerabilities |
| S.4, S.5 | `AuditLogTest`, `Doctor/AuditLogsTest` | 20 and 13 runs pass |
| S.6 | `EncryptMedicalFieldsTest`, `tests/Migrations` | 9 runs pass |
| S.7 | `docs/security/asvs-l1.md`; `tests/Feature/Security` | 120 items: 71 Pass, 7 Deploy, 28 N/A, 14 Accepted, 0 open; 46 runs pass |
| U.1 | `npm run a11y:audit`, `npm run check:contrast` | 0 axe violations (any impact) on 23 pages × ar / en and 4 dialogs; reflow 0 px at 320 / 640; no Tab stop without a ring; palette passes |
| T.1, T.2 | `tests/Unit/Architecture`, `ClinicContextTest`; ADR 0001 | 10 runs pass; ADR written |

The CSP check in `a11y:audit` was tried with an inline `<script>` injected on load: the run reported `script-src-elem inline` and exited 1. In a fresh profile, Edge's own component extensions raise a `script-src eval` violation on some pages (`sourceFile` = `chrome-extension:…`, about 120 ms after navigation). These are not the app, so the listener skips extension sources. The app build has no `eval` or `new Function`.
