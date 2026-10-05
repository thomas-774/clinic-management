# T2-01 — Patient resources and PatientPolicy

**Phase:** 2 · **Area:** Backend · **Size:** M · **Depends on:** T1-10
**Plan refs:** §2 · §6.1 Policies, Resources · §9.2

## Steps
- [x] `PatientPolicy`: a patient can view/update only their own Patient; the doctor can view/update any.
- [x] `PatientResource` (name, phone, address, illness, …).
- [x] Two history resources:
  - `SimpleHistoryEntryResource`: date, title, short description; **never** includes `details` of private entries.
  - `DetailedHistoryEntryResource`: all fields, including `type`, `details` and `patient_visible`.

## Done when
- [x] The policy unit tests pass (own patient allowed, other patient denied).
