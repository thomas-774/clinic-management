# T0-01 — Confirm open questions with the client

**Phase:** 0 · **Area:** Planning · **Size:** S · **Depends on:** — 
**Plan refs:** §10 Assumptions and Open Questions

## Goal
Lock down the decisions that change the schema or business rules before Phase 1 starts.

## Steps
- [x] Self-register vs. doctor-created patient accounts (affects FR-A.1). → **Both** (new FR-C.6).
- [x] One vs. many future appointments per patient (affects FR-E.5 / BR-4). → **One for now**, configurable.
- [x] Cancellation cut-off: any time before start, or X hours before (affects BR-5). → **Doctor setting, default 2 h**.
- [x] Different hours per weekday? A break inside the shift? (affects `working_hours` design). → **Yes to both** (several ranges per day).
- [x] Receptionist login in v1? (affects roles). → **No**, doctor only.
- [x] Interface language: Arabic, English, or both. → **Both, Arabic default** (RTL).
- [x] SMS/WhatsApp reminders needed? → **Not in v1**.
- [x] Record the answers in the plan (§10) and update any affected tasks.

## Done when
- [x] Every open question has a written answer.
- [x] Any changed rule is reflected in the plan and the task files.
