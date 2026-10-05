# T5-02 — Unit tests for PaymentService

**Phase:** 5 · **Area:** Backend / Tests · **Size:** S · **Depends on:** T5-01
**Plan refs:** §8 Phase 5 test · §9.1

## Test cases
- [ ] Total 1500, paid 1000 → remaining 500, `partially_paid`.
- [ ] A second payment of 500 → remaining 0, `paid`.
- [ ] A payment of 600 when remaining is 500 → rejected.
- [ ] Payment of 0 or a negative amount → rejected.
- [ ] Lowering the total below the amount already paid → rejected.
- [ ] No payments → `unpaid`.
- [ ] Decimal precision: 0.10 + 0.20 adds up to exactly 0.30.
- [ ] Outstanding across 3 visits = sum of their remaining amounts.

## Done when
- [ ] All cases are green.
