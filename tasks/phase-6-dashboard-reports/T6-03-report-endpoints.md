# T6-03 — Report endpoints

**Phase:** 6 · **Area:** Backend · **Size:** M · **Depends on:** T6-02
**Plan refs:** §6.3 · FR-H.1 – FR-H.4

## Steps
- [x] `GET /doctor/reports/summary?period=day|week|month` → `{ patients_seen, revenue, outstanding, from, to }`.
- [x] `GET /doctor/reports/payments?from=&to=` → paginated table rows.
  - Note: 20 rows per page, newest first, default range today. `meta.totals` `{ count, paid, remaining }` covers the whole range (for the T6-05 totals row); the range itself is in `meta.range` because the paginator already uses `meta.from` / `meta.to`.
- [x] `GET /doctor/reports/daily-revenue?month=YYYY-MM` → `[{ date, revenue }]`.
- [x] Feature tests: response shape, invalid period → 422, patient → 403.

## Done when
- [x] All three endpoints return correct numbers for the seeded data.
  - Note: the seeders create no visits or payments yet (demo data is T7-04), so `ReportsTest` checks the numbers against its own fixed fixture (4 visits, 5 payments across day/week/month edges).
