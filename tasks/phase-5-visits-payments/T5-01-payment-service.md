# T5-01 — PaymentService (balances and rules)

**Phase:** 5 · **Area:** Backend · **Size:** M · **Depends on:** T1-06
**Plan refs:** §4.4 PR-1 – PR-3, PR-6 · FR-D.4, FR-D.7

## Steps
- [ ] `paid(Visit)` = SUM(payments.amount); `remaining(Visit)` = total − paid (PR-1).
- [ ] `status(Visit)`: `paid` (remaining = 0), `partially_paid`, `unpaid` (PR-3).
- [ ] `outstandingFor(Patient)` = sum of remaining across all visits (FR-D.7).
- [ ] Guards (PR-2): payment `amount > 0` and `<= remaining`; new total `>= paid`.
- [ ] Use `bcmath` or integer piastres internally; never float maths.
- [ ] Eloquent: `withSum('payments', 'amount')` scope to avoid N+1 queries on lists.

## Done when
- [ ] T5-02 tests pass.
