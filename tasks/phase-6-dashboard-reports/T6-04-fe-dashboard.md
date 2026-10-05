# T6-04 — Frontend: Dashboard

**Phase:** 6 · **Area:** Frontend · **Size:** M · **Depends on:** T6-03, T4-09
**Plan refs:** FR-H.1, FR-H.2 · §7.2

## Steps
- [x] `StatCard` component (label, value, sub-text).
- [x] Three groups (Today / This week / This month), each with patients seen + revenue.
- [x] A separate "Outstanding balances" card (not added into revenue).
- [x] Today's queue: compact list of today's appointments with status and quick actions (reuse from Schedule).
  - Note: reuses the Schedule `DayView` with a new `compact` prop (no phone numbers), so Arrived / No-show / Cancel / Start visit behave exactly as on the Schedule.

## Done when
- [x] Recording a payment updates today's revenue after a refresh.
  - Note: visit and payment mutations now also invalidate the `['doctor','reports']` queries, so the numbers are fresh even without a reload.
