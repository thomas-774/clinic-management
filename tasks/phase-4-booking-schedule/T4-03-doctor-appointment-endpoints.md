# T4-03 — Doctor appointment endpoints (list and book for a patient)

**Phase:** 4 · **Area:** Backend · **Size:** S · **Depends on:** T4-01
**Plan refs:** FR-F.1, FR-F.2 · §6.3

## Steps
- [x] `GET /doctor/appointments?from=&to=` → ordered by `start_at`, with patient name and phone; default range = today.
- [x] `POST /doctor/appointments` `{ patient_id, start_at }` → same BookingService.

## Done when
- [x] The doctor sees today's list and can book a slot for a patient.
