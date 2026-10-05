# T8-06 — Assistant: schedule, booking and check-in

**Phase:** 8 · **Area:** Backend · **Size:** S · **Depends on:** T8-04
**Plan refs:** §4.3 · FR-I.6

## Steps
- [x] Extract the lock + `canTransitionTo` + `transitionTo` block of `Doctor/ScheduleController@updateStatus` into `AppointmentService::changeStatus()`; the doctor controller uses it.
  Note: `AppointmentService` also has `schedule()` (the day/range list) and `ScheduleRangeRequest` holds the ?from=&to= rules, both shared by the doctor and assistant controllers.
- [x] `Assistant/ScheduleController`: `index` (same as the doctor's, via `User::clinicDoctor()->doctorAppointments()`), `store` (reuse `BookForPatientRequest` + `BookingService::forClinic()->book()`), `updateStatus` limited to `checked_in`, `no_show` and `cancelled`.
- [x] `/slots` middleware → `role:patient,doctor,assistant`.
- [x] Check that `AppointmentResource` exposes no medical fields to the assistant.

## Done when
- [x] Tests: assistant lists today's appointments, books for a patient (double booking → 409), marks Arrived / No-show / Cancelled; `completed` → 422; slots readable by the assistant.
