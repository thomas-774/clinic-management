# T4-02 — Patient appointment endpoints

**Phase:** 4 · **Area:** Backend · **Size:** M · **Depends on:** T4-01
**Plan refs:** FR-E.3, FR-E.4 · BR-5 · §6.3

## Steps
- [x] `POST /patient/appointments` `{ start_at }` → `BookAppointmentRequest` → BookingService → 201 `AppointmentResource`.
- [x] `GET /patient/appointments` → own appointments split into `upcoming` and `past`; each upcoming one has `can_cancel` (boolean) so the frontend does not repeat the cut-off rule.
- [x] `PATCH /patient/appointments/{id}/cancel` → own appointment only (policy), status must be `booked` and `now <= start_at − cancel_cutoff_hours` (BR-5; doctor setting, default 2 h), otherwise 422 "Too late to cancel online, please call the clinic" → status cancelled, `cancelled_at` set.

## Done when
- [x] A patient can book, list and cancel; cancelling someone else's appointment returns 403/404.
