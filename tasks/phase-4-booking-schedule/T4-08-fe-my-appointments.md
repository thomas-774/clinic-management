# T4-08 — Frontend: MyAppointments page

**Phase:** 4 · **Area:** Frontend · **Size:** S · **Depends on:** T4-02
**Plan refs:** FR-E.4 · BR-5 · §7.2

## Steps
- [ ] `AppointmentCard` (date, time, `StatusBadge`).
- [ ] "Upcoming" and "Past" sections.
- [ ] Cancel button on upcoming booked appointments where `can_cancel` is true, with a confirm modal; invalidates `profile` and `slots` queries.
- [ ] When `can_cancel` is false, show "Too late to cancel online — please call the clinic" instead of the button.

## Done when
- [ ] Cancelling frees the slot and the patient can book again.
