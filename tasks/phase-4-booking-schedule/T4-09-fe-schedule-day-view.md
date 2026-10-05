# T4-09 — Frontend: Schedule day view with status actions

**Phase:** 4 · **Area:** Frontend · **Size:** M · **Depends on:** T4-03, T4-04
**Plan refs:** FR-F.1, FR-F.3, FR-F.4 · §7.3 Schedule

## Steps
- [ ] `StatusBadge` colours: Booked grey, Checked In blue, Completed green, No-show red, Cancelled muted.
- [ ] Day view (default today, previous/next day): time, patient name, phone, status.
- [ ] Actions by status: Booked → **Arrived**, **No-show**, **Cancel**; Checked In → **Start visit** (link added in T5-09).
- [ ] "Book for patient" button: pick a patient + slot.
- [ ] Auto-refresh every 60 s (`refetchInterval`).

## Done when
- [ ] The doctor can check in a patient and the badge turns blue.
