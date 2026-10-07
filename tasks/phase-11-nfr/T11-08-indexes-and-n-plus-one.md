# T11-08 — Indexes and N+1 guard

**Phase:** 11 · **Area:** Backend · **Size:** M · **Depends on:** —
**Plan refs:** §11.2 NFR-P.2, NFR-P.3 · §5.3

## Steps
- [ ] `Model::preventLazyLoading(! app()->isProduction())` in `AppServiceProvider`; run the suite and fix every lazy load it finds with `with()` / `load()`.
- [ ] Migration: index `visits.visit_date` (used by `ReportService` since revenue counts by visit date), and `payments (visit_id, paid_at)` if `EXPLAIN` shows the balance queries need it.
- [ ] `EXPLAIN` each query behind: patients list + search, patient details, schedule day / week, slots, today's queue, reports (day / week / month), drug search, visit export. Record the plan in this file; add an index for any full table scan on a table that grows.
- [ ] Query-count tests: for each list endpoint, the number of queries with 5 rows equals the number with 50 rows (`DB::enableQueryLog()` helper in `tests/Pest.php`).

## Done when
- [ ] The full suite runs with lazy loading blocked.
- [ ] Query-count tests pass for patients list, schedule, today's queue, patient details, prescriptions list and reports.
- [ ] No full table scan on `visits`, `payments`, `appointments` or `patients` in the recorded plans.
- [ ] Both test suites still pass.
