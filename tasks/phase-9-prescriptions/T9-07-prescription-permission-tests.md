# T9-07 — Tests: prescription permissions and privacy

**Phase:** 9 · **Area:** Backend · **Size:** M · **Depends on:** T9-03, T9-05, T9-06
**Plan refs:** §2 · §9.1 · §9.2 · FR-J.7 · RX-3

## Steps
- [ ] A data-driven test over every `/doctor/drugs*` and prescriptions route: patient → 403, assistant → 403, guest → 401.
- [ ] Patient and assistant responses (patient profile, assistant patient page) never contain prescriptions or drug data.
- [ ] Hidden drugs: not in search, still shown on old prescriptions.
- [ ] No drug API response includes a price field.

## Done when
- [ ] All of the above pass; backend suite green.
