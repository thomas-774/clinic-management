# T5-05 — POST /doctor/visits/{id}/payments (installments)

**Phase:** 5 · **Area:** Backend · **Size:** S · **Depends on:** T5-01
**Plan refs:** FR-D.6 · PR-2, PR-4

## Steps
- [ ] `StorePaymentRequest`: `amount` > 0 and ≤ remaining, `method`, `paid_at` (default now).
- [ ] Lock the visit row (`lockForUpdate`) while validating and inserting so two payments cannot overshoot.
- [ ] Return the updated `VisitResource`.

## Done when
- [ ] An installment on an old visit lowers its remaining balance.
