# T11-08 — Indexes and N+1 guard

**Phase:** 11 · **Area:** Backend · **Size:** M · **Depends on:** —
**Plan refs:** §11.2 NFR-P.2, NFR-P.3 · §5.3

## Steps
- [x] `Model::preventLazyLoading(! app()->isProduction())` in `AppServiceProvider`; run the suite and fix every lazy load it finds with `with()` / `load()`.
  Note: the suite found none; Laravel only flags a lazy load on a model fetched together with others, and most tests have one row. The query-count tests below run every list with 5 and 50 rows, so they hit that case. A check by removing one eager load: with the guard the request fails (500), without it the count test fails (9 → 54 queries).
- [x] Migration: index `visits.visit_date` (used by `ReportService` since revenue counts by visit date), and `payments (visit_id, paid_at)` if `EXPLAIN` shows the balance queries need it.
  Note: `2026_10_07_130000_add_report_and_list_indexes`. `visits (visit_date, patient_id, total_amount)` instead of `visit_date` alone: the extra columns let the outstanding totals read the index, not the rows. `payments (visit_id, amount)` instead of `(visit_id, paid_at)`: the balance queries sum `amount` per visit and never filter on `paid_at`, so with `amount` in the index they read the index only. Two more from the plans: `visits (patient_id, visit_date)` (a patient's visits newest first, the list's "last visit") and `users (name)` (the patients list's sort). MySQL drops the foreign key's own `payments.visit_id` index when the wider one is added, so `down()` puts it back first.
- [x] `EXPLAIN` each query behind: patients list + search, patient details, schedule day / week, slots, today's queue, reports (day / week / month), drug search, visit export. Record the plan in this file; add an index for any full table scan on a table that grows.
  Note: plans below. Besides the indexes, four queries changed: the "Waiting to pay" list used `whereDate('visit_date')`, which wraps the column in `date()` and cannot use an index (now `where`); the slots query had no lower bound on `start_at`, so it read every past appointment (now `start_at > day − 1`, safe because a slot is at most 240 minutes); the patients list breaks name ties by `users.id` instead of `patients.id`, so both sort keys are on `users` and MySQL reads the name index in order and stops at the page; the outstanding-by-patient report sums payments once per visit in a derived table instead of a subquery per visit (54 vs 84 ms on its own). `whereDate` on `blocked_times.date` in the slots query became `where` too.
- [x] Query-count tests: for each list endpoint, the number of queries with 5 rows equals the number with 50 rows (`DB::enableQueryLog()` helper in `tests/Pest.php`).
  Note: `queryCount()` in `tests/Pest.php`; `tests/Feature/Performance/QueryCountTest.php` covers 23 endpoints (doctor, assistant and patient lists and details, the four reports, drugs, staff, blocked times, activity log).

## Recorded plans (Oct 7, 2026)

Dataset: 10,000 patients, 63,680 appointments (40 a day, 5 years), 37,560 visits, 52,584 payments, 12,520 prescriptions, 300 drugs, built in `clinic_testing` and `ANALYZE`d; MySQL 8.4. Time = the SQL time of the request's SELECTs. `ref` / `range` = index lookup; `index` = the whole index is read, not the table; `ALL` = full table scan.

| Endpoint | Before: ms · tables scanned in full | After: ms | Access path after, on the tables that grow |
| --- | --- | --- | --- |
| Patients list | 131 · users (+ temporary table, filesort) | 10 | users `index` on `users_name_index` (20 entries, then stops); patients `eq_ref` by `user_id`; last visit `ref` on `visits (patient_id, visit_date)`. Total count reads `users_phone_unique` (`index`). |
| Patients search (name / phone) | 9 · users | 17 | users `ALL`: the search is `LIKE '%term%'` on name or phone, which no B-tree index can serve; patients `eq_ref`. See below. |
| Patient details | 2 · — | 2 | visits `ref (patient_id, visit_date)`, backward scan, no filesort; payments `ref (visit_id, amount)`, index only; history and prescriptions `ref` on `patient_id`. |
| Prescriptions list | 0 · — | 0 | prescriptions `ref (patient_id, issued_on)`, backward scan. |
| Schedule day = today's queue (doctor and assistant) | 2 · — | 2 | appointments `range (doctor_id, start_at)`, ~40 rows. |
| Schedule week | 4 · — | 4 | appointments `range (doctor_id, start_at)`, ~240 rows. |
| Waiting to pay (assistant) | 134 · visits | 0 | visits `ref (visit_date, …)`; payments `ref (visit_id, amount)`, index only. |
| Slots | 70 · — (read ~32,000 appointments through `(doctor_id, active_slot)`) | 2 | appointments `range (doctor_id, start_at)`, ~80 rows. |
| Reports day / week / month | 25 · visits, payments | 12 | the period: visits `range (visit_date, …)`, payments `ref (visit_id, amount)`; the outstanding total: `index` on both new indexes (see below). |
| Report payments table | 45 · visits | 14 | visits `range (visit_date, …)`; payments `ref (visit_id, amount)`. |
| Report outstanding (who owes what) | 165 · visits, payments | 69 | visits `index (visit_date, patient_id, total_amount)` in date order; payments `index (visit_id, amount)` summed once into a derived table. |
| Report daily revenue | 8 · visits | 1 | visits `range (visit_date, …)`; payments `ref (visit_id, amount)`. |
| Drug search | 3 · drugs (300 rows) | 2 | drugs `ALL`: contains-search on a 300-row catalogue that does not grow with the clinic. |
| Visit export | 2 · — | 3 | primary-key lookups; payments `ref (visit_id, amount)`; other visits `ref (patient_id)`. |
| Patient's appointments | 1 · — | 1 | appointments `ref (patient_id)`. |

No `ALL` remains on `visits`, `payments`, `appointments` or `patients`. Two kinds of read still cover a whole table, on purpose:

- **Clinic-wide totals** (the outstanding card on every report, FR-H.2, and the outstanding-by-patient list): "what is still owed" is the sum over every visit and every payment, so each one is read. They are read from the two narrow new indexes (`Using index`, ~37k + 53k entries, 11 ms for the card), never from the rows with their encrypted text. Storing a running balance would remove this, but §5.3 says the balance is computed, not stored.
- **Patient search** (`LIKE '%term%'` on `users.name` / `users.phone`): ~10,000 short rows, 17 ms. A prefix-only search or a FULLTEXT (ngram) index could use an index; not needed at this size.

## Done when
- [x] The full suite runs with lazy loading blocked.
- [x] Query-count tests pass for patients list, schedule, today's queue, patient details, prescriptions list and reports.
- [x] No full table scan on `visits`, `payments`, `appointments` or `patients` in the recorded plans.
- [x] Both test suites still pass.
