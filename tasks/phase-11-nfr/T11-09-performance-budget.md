# T11-09 — 5-year dataset and API response-time budget

**Phase:** 11 · **Area:** Backend · **Size:** M · **Depends on:** T11-06, T11-08
**Plan refs:** §11.2 NFR-P.1 · §11.1

## Steps
- [ ] `PerformanceSeeder` (never run by `db:seed` by default): 10,000 patients, 5 years of appointments and visits at up to 50 a day, payments with installments, history entries, prescriptions — encrypted fields included, since decryption costs time.
- [ ] Command `clinic:bench` that calls each key endpoint 100 times as the right role through the HTTP kernel and prints p50 / p95 / p99 in ms: patients list + search, patient details, slots for a day, booking, schedule day, today's queue, visit save, reports day / week / month, drug search, visit export (PDF and Word).
- [ ] Fix anything over target: query first, then caching (only if needed) — e.g. the drug catalogue and closed past report periods in `Cache` with clear invalidation on write.
- [ ] Record the final numbers in this file and in §11.2 (T11-16).

## Done when
- [ ] On the dataset, every listed endpoint has p95 < 300 ms and p99 < 800 ms; export p95 < 1.5 s, on the dev machine with `config:cache` and `route:cache`.
- [ ] Any cache added has a test that a write clears it.
- [ ] Both test suites still pass.
