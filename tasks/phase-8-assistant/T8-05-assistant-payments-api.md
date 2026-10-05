# T8-05 — Assistant: waiting-to-pay list and record payment

**Phase:** 8 · **Area:** Backend · **Size:** S · **Depends on:** T8-04
**Plan refs:** §4.4 PR-1 – PR-3 · FR-I.4, FR-I.5

## Steps
- [x] `GET /assistant/visits/unpaid?date=` (default today): visits of that date with remaining > 0, with patient name/phone, total, paid, remaining; uses `withSum('payments', 'amount')`, no N+1.
  Note: the "still owes" filter is the `Visit::unpaid()` scope (one SQL subquery); oldest visit first.
- [x] `POST /assistant/visits/{visit}/payments`: reuse `StorePaymentRequest` and `PaymentService::addPayment()` (row lock, amount ≤ remaining) with `recorded_by` = the assistant; returns `AssistantVisitResource`.

## Done when
- [x] Tests: the list shows only unpaid visits of the date; paying the rest removes the visit from the list; amount > remaining → 422; `recorded_by` saved; no `work_done` in responses.
