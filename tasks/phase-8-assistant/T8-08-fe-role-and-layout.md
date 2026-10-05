# T8-08 — Frontend: assistant role, routes and layout

**Phase:** 8 · **Area:** Frontend · **Size:** S · **Depends on:** T8-01
**Plan refs:** §7.2 · FR-I.1

## Steps
- [ ] `homePathFor('assistant')` → `/assistant` in `src/auth/useAuth.js`.
- [ ] `AppRoutes.jsx`: `/assistant` under `<RoleRoute role="assistant">` with `layouts/AssistantLayout.jsx` (same structure as DoctorLayout; links Today, Patients, Schedule).
- [ ] `src/api/assistant.js` with the `/assistant/*` calls.
- [ ] i18n keys in ar.json and en.json (identical keys).

## Done when
- [ ] Guard tests: an assistant lands on `/assistant`; an assistant opening `/doctor` is redirected; a doctor opening `/assistant` is redirected.
