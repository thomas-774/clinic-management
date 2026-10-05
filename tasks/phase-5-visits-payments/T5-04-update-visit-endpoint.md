# T5-04 — PUT /doctor/visits/{id}

**Phase:** 5 · **Area:** Backend · **Size:** S · **Depends on:** T5-03
**Plan refs:** FR-D.2, FR-D.3 · PR-2

## Steps
- [x] `UpdateVisitRequest`: `work_done`, `total_amount` (must be ≥ amount already paid).
- [x] Returns the updated `VisitResource` with the recalculated remaining.

## Done when
- [x] Lowering the total below the paid amount returns 422.
