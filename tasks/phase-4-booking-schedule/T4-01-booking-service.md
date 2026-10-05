# T4-01 — BookingService (transactional booking)

**Phase:** 4 · **Area:** Backend · **Size:** L · **Depends on:** T3-04
**Plan refs:** §4.2 BR-1 – BR-4 · §6.2 Concurrency

## Steps
- [x] `BookingService::book(Patient $patient, Carbon $startAt): Appointment`.
- [x] Inside `DB::transaction`:
  - [x] BR-4: lock and count the patient's active future appointments (status booked / checked_in, `start_at > now`); if the count reaches `config('clinic.max_active_appointments')` (default 1) → 422 "You already have an upcoming appointment".
  - [x] BR-1: `SlotService::isAvailable($startAt)` (re-generated on the server) → 409 "Slot no longer available".
  - [x] BR-3: `end_at = start_at + current duration`.
  - [x] Insert; catch the unique-index violation (`active_slot`) → 409.
- [x] Custom exceptions (`SlotUnavailableException`, `ActiveAppointmentExistsException`) mapped to 409 / 422.

## Done when
- [x] T4-05 tests pass.
