# T8-04 — Assistant: patients API (contact + money only)

**Phase:** 8 · **Area:** Backend · **Size:** M · **Depends on:** T8-01, T8-03
**Plan refs:** §2 · §6.3 · FR-I.2, FR-I.3

## Steps
- [x] Add the assistant route group in `routes/api.php` (`/assistant/*`, `role:assistant`, names `assistant.`).
- [x] Move the name/phone search from `Doctor/PatientController@index` into a `Patient` scope and use it from both controllers.
  Note: `Patient::forList($search)`; patient creation (user + patient + initial password) also moved to `PatientAccountService` for both controllers. The assistant list reuses `PatientListItemResource` (id, name, phone, last visit date).
- [x] `Assistant/PatientController`: `index` (search, paginated), `store` (`StoreAssistantPatientRequest` = StorePatientRequest without `current_illness`; returns `initial_password` once), `show`, `update` (name, phone, address, date_of_birth, gender only).
- [x] `AssistantPatientResource`: contact fields + outstanding balance + visits via `AssistantVisitResource` (id, visit_date, total, paid, remaining, payment status, payments with method, paid_at, recorded_by_name). **No** `work_done`, `current_illness` or `history`.
  Note: also includes `next_appointment`, so the front desk can see the coming booking.
- [x] `PatientPolicy`: viewAny, create, view and update allow `isAssistant()`; `manageHistory` stays doctor-only.

## Done when
- [x] Tests: search; create (illness not accepted); show has balance + visits; JSON never contains `work_done` / `current_illness` / `history`; update cannot change the illness.
