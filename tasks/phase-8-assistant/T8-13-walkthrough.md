# T8-13 — Demo data and front-desk walkthrough

**Phase:** 8 · **Area:** Full stack · **Size:** S · **Depends on:** T8-01 … T8-12
**Plan refs:** §8 Phase 8 · §9.1

## Steps
- [ ] The seeder adds one demo assistant account.
- [ ] Manual walkthrough in the browser (ar and en, desktop and phone width):
  1. Doctor → Settings → Staff → create an assistant.
  2. Assistant logs in → `/assistant`, registers a walk-in patient, books today, marks Arrived.
  3. Doctor → Start visit → total 1500, paid now 0.
  4. Assistant → Waiting to pay shows 1500 → records 1000 → the patient page shows 500 remaining and no work done anywhere.
  5. Doctor → Reports shows the 1000 recorded by the assistant.
  6. Doctor deactivates the assistant → the session ends and login fails.

## Done when
- [ ] Every step works; backend and frontend suites and oxlint are green.
