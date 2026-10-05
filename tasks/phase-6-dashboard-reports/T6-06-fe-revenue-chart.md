# T6-06 — Frontend: daily revenue chart

**Phase:** 6 · **Area:** Frontend · **Size:** S · **Depends on:** T6-05
**Plan refs:** FR-H.4

## Steps
- [x] Install Recharts.
- [x] Bar chart of revenue per day for the selected month, with a month picker.
  - Note: the month is kept in the URL as `?month=YYYY-MM` (this month by default), independent of the payments table's period. A "Show as table" toggle lists the same daily numbers (accessibility / exact values).
- [x] Tooltip with the formatted EGP amount; empty state for a month with no data.

## Done when
- [x] The chart matches the daily-revenue endpoint for the seeded month.
  - Note: no visits or payments are seeded yet (T7-04); checked against the dev database (Oct 2026) by screenshot, and `RevenueChart.test.jsx` checks bars, total and table against a stubbed endpoint.
