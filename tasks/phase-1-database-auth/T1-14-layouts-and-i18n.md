# T1-14 — Patient/Doctor layouts and i18n setup

**Phase:** 1 · **Area:** Frontend · **Size:** M · **Depends on:** T1-12
**Plan refs:** §7.1 layouts/ · §7.3 Language

## Steps
- [ ] `PatientLayout`: top bar with links (Home, Book, My appointments) and logout; works on mobile.
- [ ] `DoctorLayout`: sidebar (Dashboard, Schedule, Patients, Reports, Settings) and logout.
- [ ] Configure react-i18next with `ar` (default) and `en` (fallback); all UI text goes through `t()`.
- [ ] Language switcher in both layouts (and on Login/Register); the choice is saved in localStorage.
- [ ] Load an Arabic-friendly font (e.g. Cairo or Tajawal from Google Fonts).
- [ ] Set `dir="rtl"` and `lang="ar"` on `<html>` when Arabic is active (`ltr` / `en` for English); use Tailwind logical classes (`ms-`, `me-`, `ps-`, `pe-`).

## Done when
- [ ] The app opens in Arabic (RTL); both layouts render with navigation, and switching to English changes the text and direction and survives a reload.
