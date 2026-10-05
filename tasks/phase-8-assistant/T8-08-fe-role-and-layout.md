# T8-08 — Frontend: assistant role, routes and layout

**Phase:** 8 · **Area:** Frontend · **Size:** S · **Depends on:** T8-01
**Plan refs:** §7.2 · FR-I.1

## Steps
- [x] `homePathFor('assistant')` → `/assistant` in `src/auth/useAuth.js`.
- [x] `AppRoutes.jsx`: `/assistant` under `<RoleRoute role="assistant">` with `layouts/AssistantLayout.jsx` (same structure as DoctorLayout; links Today, Patients, Schedule).
  Note: the sidebar moved into `layouts/SidebarLayout.jsx`; DoctorLayout and AssistantLayout only pass their links. The four assistant pages are placeholders until T8-09 – T8-11.
- [x] `src/api/assistant.js` with the `/assistant/*` calls.
- [x] i18n keys in ar.json and en.json (identical keys).

## Done when
- [x] Guard tests: an assistant lands on `/assistant`; an assistant opening `/doctor` is redirected; a doctor opening `/assistant` is redirected.
