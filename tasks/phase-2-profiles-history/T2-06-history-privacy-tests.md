# T2-06 — Tests: history privacy and profile access

**Phase:** 2 · **Area:** Backend / Tests · **Size:** S · **Depends on:** T2-02, T2-05
**Plan refs:** §8 Phase 2 check · §9.2

## Steps
- [ ] An entry with `patient_visible = false` appears in `GET /doctor/patients/{id}` and **not** in `GET /patient/profile`.
- [ ] Patient A cannot access patient B's data (there is no patient route that takes an id; confirm none leak).
- [ ] A patient calling `/doctor/patients` gets 403.

## Done when
- [ ] All privacy tests pass.
