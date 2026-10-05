# T3-03 — Blocked times endpoints

**Phase:** 3 · **Area:** Backend · **Size:** S · **Depends on:** T1-10
**Plan refs:** FR-G.3 · §6.3

## Steps
- [ ] `GET /doctor/blocked-times?from=&to=`: list (defaults to today onward).
- [ ] `POST /doctor/blocked-times`: date (today or later), optional start/end time (both or neither; end > start), reason.
- [ ] `DELETE /doctor/blocked-times/{id}`.

## Done when
- [ ] A whole-day block and a time-range block can both be created and deleted.
