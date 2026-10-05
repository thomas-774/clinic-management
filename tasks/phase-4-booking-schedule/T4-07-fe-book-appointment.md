# T4-07 — Frontend: BookAppointment page

**Phase:** 4 · **Area:** Frontend · **Size:** M · **Depends on:** T4-02, T4-06
**Plan refs:** FR-E.1 – FR-E.3, FR-E.5 · §7.2

## Steps
- [ ] Date picker limited to today … today + booking window.
- [ ] `SlotGrid` for the chosen date → confirm modal (date, time, duration).
- [ ] On 409: toast "Slot just taken", refetch slots.
- [ ] On 422 (already has an appointment): show a message with a link to My appointments.
- [ ] On success: redirect to My appointments with a success toast.

## Done when
- [ ] A patient can book from start to finish; a taken slot refreshes the grid.
