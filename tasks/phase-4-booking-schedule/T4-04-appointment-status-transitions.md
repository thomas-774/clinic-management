# T4-04 — Appointment status transitions

**Phase:** 4 · **Area:** Backend · **Size:** M · **Depends on:** T4-03
**Plan refs:** §4.3 lifecycle · FR-F.3, FR-F.4

## Steps
- [ ] `AppointmentStatus::canTransitionTo()` allowing only:
  - booked → checked_in / cancelled / no_show
  - checked_in → completed
- [ ] `PATCH /doctor/appointments/{id}/status` `{ status }`: rejects invalid transitions with 422.
- [ ] Set `checked_in_at` on checked_in and `cancelled_at` on cancelled.
- [ ] Note: `completed` is normally set by saving a visit (T5-03); allow manual completion as well, per FR-F.4.

## Done when
- [ ] Unit tests cover every allowed and every forbidden transition.
