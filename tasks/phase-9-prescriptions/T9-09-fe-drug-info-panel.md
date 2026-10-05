# T9-09 — Frontend: DrugInfoPanel (side note)

**Phase:** 9 · **Area:** Frontend · **Size:** S · **Depends on:** T9-08
**Plan refs:** FR-J.3

## Steps
- [x] `components/DrugInfoPanel.jsx` for a drug id (showing the search result while the full drug loads): trade name, form · pack, category badge; **Uses**; **Warnings** in a red box (hidden when empty); **Active ingredients** as a list with their notes; **Suggested dose**; a "Use suggested dose" button → `onUseDose(text)`.
  Note: props are `drugId`, `preview` (used only if its id matches `drugId`), `freeText` and `onUseDose`. The button is hidden when the drug has no suggested dose. Texts keep the PDF's line breaks and render with `dir="auto"`; ingredient names are in `<bdi>`. A load error shows the usual retry box. The 11 category names are in the new `drugCategory` i18n section (T9-13 can reuse it).
- [x] Empty state ("Pick or highlight a drug to see its note") and a note for free-text lines ("Not in the catalogue").
- [x] Sticky beside the form on wide screens; below the line on narrow screens.
  Note: the panel is `lg:sticky lg:top-4` and takes a `className`; putting it beside the lines (wide) or under the line being edited (narrow) is the form's layout, done in T9-10.

## Done when
- [x] Vitest: shows uses, warnings and ingredients; no warnings box when empty; the button sends the suggested dose.
  Note: `components/DrugInfoPanel.test.jsx` (10 tests, including the preview while loading, empty and free-text states, retry and following a new id).
