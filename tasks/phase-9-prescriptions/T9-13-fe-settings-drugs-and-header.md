# T9-13 — Frontend: Settings → Drugs and Settings → Prescription

**Phase:** 9 · **Area:** Frontend · **Size:** M · **Depends on:** T9-03, T9-06, T9-11
**Plan refs:** FR-J.6

## Steps
- [x] Settings → **Drugs** tab: search box, category filter, "show hidden" toggle, DataTable (trade name, form, category, status); Add / Edit modal (all fields, ingredients as add/remove rows); Hide / Show toggle.
  Note: Settings had no tabs yet, so it now has three ARIA tabs — "Hours & booking" (everything that was there), "Drugs" and "Prescription" — kept in `?tab=` (← → / Home / End move between them). The tab is `pages/doctor/settings/DrugsTab.jsx` with `DrugFormModal.jsx`; hooks `useDrugList`, `useSaveDrug` and `useSetDrugActive` are in `hooks/useDrugs.js` and refresh every drug query, so a hidden drug drops out of the suggestions at once. The search waits 300 ms and a new filter goes back to page 1. Form shows form · pack. Empty ingredient rows are not sent; 1 to 10 rows.
- [x] Settings → **Prescription** tab: clinic name, doctor title, address, phone, footer and paper size A5/A4, with a live preview using `PrescriptionPrint` and sample lines.
  Note: `pages/doctor/settings/PrescriptionHeaderTab.jsx`. The preview uses the logged-in doctor's name, is drawn at real size and zoomed to 60 %, and switches between the A5 and A4 sheet. Empty fields are saved as null.
- [x] i18n keys in both files.
  Note: `settings.tabs`, `drugsAdmin` and `rxSettings`; category names reuse `drugCategory` (T9-09).

## Done when
- [x] Vitest: adding a drug posts the right body; hiding calls PUT with `is_active: false`; the header form saves and the preview updates while typing.
  Note: `pages/doctor/settings/DrugsTab.test.jsx` (7 tests, also search / section / hidden filters, 422 errors on fields and ingredients, editing and the tab keyboard) and `PrescriptionHeaderTab.test.jsx` (3 tests). The existing Settings tests pass unchanged on the first tab.
