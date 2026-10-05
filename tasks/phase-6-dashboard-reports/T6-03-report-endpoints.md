# T6-03 — Report endpoints

**Phase:** 6 · **Area:** Backend · **Size:** M · **Depends on:** T6-02
**Plan refs:** §6.3 · FR-H.1 – FR-H.4

## Steps
- [ ] `GET /doctor/reports/summary?period=day|week|month` → `{ patients_seen, revenue, outstanding, from, to }`.
- [ ] `GET /doctor/reports/payments?from=&to=` → paginated table rows.
- [ ] `GET /doctor/reports/daily-revenue?month=YYYY-MM` → `[{ date, revenue }]`.
- [ ] Feature tests: response shape, invalid period → 422, patient → 403.

## Done when
- [ ] All three endpoints return correct numbers for the seeded data.
