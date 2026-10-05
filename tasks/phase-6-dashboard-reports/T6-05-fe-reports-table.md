# T6-05 — Frontend: Reports page, filter and payments table

**Phase:** 6 · **Area:** Frontend · **Size:** M · **Depends on:** T6-03, T2-08
**Plan refs:** FR-H.3 · §7.2

## Steps
- [x] Period filter: Today / This week / This month / Custom range (kept in the URL).
  - Note: `?period=day|week|custom` (+ `from`, `to` for custom, `page`); no `period` means This month. A custom range with the end before the start shows a message and sends no request.
- [x] `DataTable` of payments: date, patient (link to details), visit total, paid, remaining.
- [x] Totals row at the bottom.
  - Note: `DataTable` got an optional `footer` prop. The totals come from the API's `meta.totals`, so they cover the whole range, not only the page on screen; the remaining total counts each visit once.

## Done when
- [x] Switching the period updates the table and totals.
