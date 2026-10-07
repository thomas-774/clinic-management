# T11-09 — 5-year dataset and API response-time budget

**Phase:** 11 · **Area:** Backend · **Size:** M · **Depends on:** T11-06, T11-08
**Plan refs:** §11.2 NFR-P.1 · §11.1

## Steps
- [x] `PerformanceSeeder` (never run by `db:seed` by default): 10,000 patients, 5 years of appointments and visits at up to 50 a day, payments with installments, history entries, prescriptions — encrypted fields included, since decryption costs time.
  Note: `database/seeders/PerformanceSeeder.php`, bulk inserts with the ids set in PHP, ~13 s. It moves the doctor to 15-minute slots, 10:00–22:00 on six days (48 slots), since the demo's 45-minute 17:00–21:00 day fits only 5. Per working day 30–48 appointments (85 % completed with a visit, 8 % cancelled, 7 % no-show) plus 0–3 walk-ins, never over 50 visits; the next 30 days hold 10–25 booked, one per patient (BR-4). Payments: 70 % paid at once, 20 % in 2–3 installments, 10 % still owing; 30 % of visits have a prescription of 1–3 catalogue drugs; 0–6 history entries per patient. Work done, current illness, history title / details, prescription notes and instructions are encrypted with `Crypt::encryptString`, as the `encrypted` cast stores them. It ends with `ANALYZE TABLE`: right after the bulk insert MySQL's index statistics are stale and it picked slow plans (reports 16 → 82 ms, the payments table 12 → 430 ms). Refuses to run in production. The day loop counts days by offset from today, not by comparing Carbons: Egypt's summer time starts at midnight, so from April on each day's "midnight" was 01:00 and today was taken for a future day (no visits today).
- [x] Command `clinic:bench` that calls each key endpoint 100 times as the right role through the HTTP kernel and prints p50 / p95 / p99 in ms: patients list + search, patient details, slots for a day, booking, schedule day, today's queue, visit save, reports day / week / month, drug search, visit export (PDF and Word).
  Note: `app/Console/Commands/RunBenchmark.php` (`--iterations=100`, `--only=<text>`), exits 1 when a budget is broken. Times the kernel's `handle()` + `terminate()` with 3 untimed warm-up calls; ids and search terms vary per call. Also measured: the assistant's patient details, today's queue and "Waiting to pay", schedule week, and the other three report endpoints (payments table, daily revenue, outstanding). The rate limits stay in the path with higher numbers. Not timed: the Sanctum token lookup (the user is set on the guard, as `actingAs` does) and the web server. Booking and visit save commit for real; the rows they create are deleted after each call, so runs repeat. Requests are in Arabic (`Accept-Language: ar`), the heavier export.
- [x] Fix anything over target: query first, then caching (only if needed) — e.g. the drug catalogue and closed past report periods in `Cache` with clear invalidation on write.
  Note: one endpoint was over: **report outstanding**, p95 ~480 ms (a 5-call first run). SQL was 75 ms; the rest was building a Visit, Patient and User model for each of ~5,600 unpaid visits. `ReportService::outstandingByPatient()` now reads plain rows and fetches name and phone for the owing patients in a second query (joining users into the first query made MySQL walk every patient's visits: 180 ms). Same output and order; `ReportsTest` unchanged and green. Now p95 130 ms. No cache was needed, so none was added.
- [x] Record the final numbers in this file and in §11.2 (T11-16).
  Note: numbers below. §11.2's baseline column is filled in by T11-16, which measures every NFR.

## How to run

```bash
# once: an empty database for it (never the dev or test database)
mysql -uroot -e "CREATE DATABASE clinic_bench CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON clinic_bench.* TO 'clinic'@'localhost'"

cd backend
export DB_DATABASE=clinic_bench          # overrides .env for these commands only
php artisan migrate:fresh --force
php artisan db:seed --class=PerformanceSeeder --force
php artisan config:cache && php artisan route:cache
php artisan clinic:bench                 # --only=report, --iterations=20
php artisan config:clear && php artisan route:clear   # or the dev app keeps pointing at clinic_bench
```

## Results (Oct 7, 2026)

Dev machine (Windows 11, PHP 8.3.33 ZTS from Laragon with OPcache on in the CLI, MySQL 8.4.3), `config:cache` + `route:cache`, `APP_DEBUG=true`, database cache store. Dataset: 10,000 patients, 61,967 appointments, 54,523 visits, 70,407 payments, 16,391 prescriptions, ~30,000 history entries. 100 calls per endpoint, ms.

| Endpoint | p50 | p95 | p99 | Budget p95 / p99 |
| --- | --- | --- | --- | --- |
| Patients list | 16.6 | 18.9 | 20.7 | 300 / 800 |
| Patients search | 12.1 | 19.9 | 21.6 | 300 / 800 |
| Patient details (doctor) | 5.0 | 6.3 | 9.7 | 300 / 800 |
| Patient details (assistant) | 5.2 | 6.7 | 7.1 | 300 / 800 |
| Slots for a day | 6.0 | 7.0 | 7.4 | 300 / 800 |
| Booking (patient) | 10.2 | 12.1 | 12.5 | 300 / 800 |
| Schedule day | 5.4 | 6.3 | 6.8 | 300 / 800 |
| Schedule week | 15.8 | 16.8 | 17.3 | 300 / 800 |
| Today's queue (assistant) | 5.7 | 6.6 | 7.1 | 300 / 800 |
| Waiting to pay (assistant) | 2.7 | 3.3 | 3.5 | 300 / 800 |
| Visit save | 6.7 | 8.2 | 8.8 | 300 / 800 |
| Report day | 15.7 | 16.8 | 17.0 | 300 / 800 |
| Report week | 16.4 | 17.3 | 18.3 | 300 / 800 |
| Report month | 16.4 | 17.3 | 17.4 | 300 / 800 |
| Report payments table (month) | 12.7 | 13.6 | 14.6 | 300 / 800 |
| Report daily revenue | 1.5 | 1.7 | 1.8 | 300 / 800 |
| Report outstanding (who owes what) | 125.1 | 130.0 | 134.6 | 300 / 800 |
| Drug search | 3.4 | 4.4 | 5.2 | 300 / 800 |
| Visit export PDF (Arabic) | 50.0 | 58.2 | 61.3 | 1500 / — |
| Visit export Word (Arabic) | 24.1 | 53.0 | 65.5 | 1500 / — |

The report summaries spend ~11 ms on the clinic-wide outstanding total (every visit and payment, read from the T11-08 indexes). The outstanding list is the slowest endpoint and grows with the number of unpaid visits, not with the period; at today's size it has a 2× margin to the budget.

## Done when
- [x] On the dataset, every listed endpoint has p95 < 300 ms and p99 < 800 ms; export p95 < 1.5 s, on the dev machine with `config:cache` and `route:cache`.
- [x] Any cache added has a test that a write clears it.
  Note: no cache was added (nothing needed one after the query fix).
- [x] Both test suites still pass.
  Note: `tests/Feature/Performance/PerformanceBenchTest.php`: the seeder is not in `db:seed`, a small dataset keeps PR-2, BR-4 and ≤ 50 visits a day and stores the medical text encrypted, today's past slots are done (the summer-time case), `clinic:bench` lists every endpoint with no "OVER" and leaves the row counts as they were, `--only`, both refuse production, percentiles.
