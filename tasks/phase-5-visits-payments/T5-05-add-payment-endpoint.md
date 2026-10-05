# T5-05 — POST /doctor/visits/{id}/payments (installments)

**Phase:** 5 · **Area:** Backend · **Size:** S · **Depends on:** T5-01
**Plan refs:** FR-D.6 · PR-2, PR-4

## Steps
- [x] `StorePaymentRequest`: `amount` > 0 and ≤ remaining, `method`, `paid_at` (default now).
- [x] Lock the visit row (`lockForUpdate`) while validating and inserting so two payments cannot overshoot.
- [x] Return the updated `VisitResource`.

## Done when
- [x] An installment on an old visit lowers its remaining balance.
