# T9-13 — Frontend: Settings → Drugs and Settings → Prescription

**Phase:** 9 · **Area:** Frontend · **Size:** M · **Depends on:** T9-03, T9-06, T9-11
**Plan refs:** FR-J.6

## Steps
- [ ] Settings → **Drugs** tab: search box, category filter, "show hidden" toggle, DataTable (trade name, form, category, status); Add / Edit modal (all fields, ingredients as add/remove rows); Hide / Show toggle.
- [ ] Settings → **Prescription** tab: clinic name, doctor title, address, phone, footer and paper size A5/A4, with a live preview using `PrescriptionPrint` and sample lines.
- [ ] i18n keys in both files.

## Done when
- [ ] Vitest: adding a drug posts the right body; hiding calls PUT with `is_active: false`; the header form saves and the preview updates while typing.
