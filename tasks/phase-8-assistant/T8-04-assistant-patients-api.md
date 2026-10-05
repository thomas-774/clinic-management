# T8-04 — Assistant: patients API (contact + money only)

**Phase:** 8 · **Area:** Backend · **Size:** M · **Depends on:** T8-01, T8-03
**Plan refs:** §2 · §6.3 · FR-I.2, FR-I.3

## Steps
- [ ] Add the assistant route group in `routes/api.php` (`/assistant/*`, `role:assistant`, names `assistant.`).
- [ ] Move the name/phone search from `Doctor/PatientController@index` into a `Patient` scope and use it from both controllers.
- [ ] `Assistant/PatientController`: `index` (search, paginated), `store` (`StoreAssistantPatientRequest` = StorePatientRequest without `current_illness`; returns `initial_password` once), `show`, `update` (name, phone, address, date_of_birth, gender only).
- [ ] `AssistantPatientResource`: contact fields + outstanding balance + visits via `AssistantVisitResource` (id, visit_date, total, paid, remaining, payment status, payments with method, paid_at, recorded_by_name). **No** `work_done`, `current_illness` or `history`.
- [ ] `PatientPolicy`: viewAny, create, view and update allow `isAssistant()`; `manageHistory` stays doctor-only.

## Done when
- [ ] Tests: search; create (illness not accepted); show has balance + visits; JSON never contains `work_done` / `current_illness` / `history`; update cannot change the illness.
