# T3-01 — Doctor settings endpoints

**Phase:** 3 · **Area:** Backend · **Size:** S · **Depends on:** T1-10
**Plan refs:** FR-G.2 · BR-5 · §6.3

## Steps
- [x] `GET /doctor/settings`: slot duration, booking window, cancellation cut-off.
- [x] `PUT /doctor/settings`: `slot_duration_minutes` (integer, 10–240), `booking_window_days` (1–90), `cancel_cutoff_hours` (integer, 0–72).

## Done when
- [x] Changing the duration to 60 and the cut-off to 24 is saved and returned.
