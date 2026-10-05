# T5-02 — Unit tests for PaymentService

**Phase:** 5 · **Area:** Backend / Tests · **Size:** S · **Depends on:** T5-01
**Plan refs:** §8 Phase 5 test · §9.1

## Test cases
- [x] Total 1500, paid 1000 → remaining 500, `partially_paid`.
- [x] A second payment of 500 → remaining 0, `paid`.
- [x] A payment of 600 when remaining is 500 → rejected.
- [x] Payment of 0 or a negative amount → rejected.
- [x] Lowering the total below the amount already paid → rejected.
- [x] No payments → `unpaid`.
- [x] Decimal precision: 0.10 + 0.20 adds up to exactly 0.30.
- [x] Outstanding across 3 visits = sum of their remaining amounts.

## Done when
- [x] All cases are green.
