# T9-09 — Frontend: DrugInfoPanel (side note)

**Phase:** 9 · **Area:** Frontend · **Size:** S · **Depends on:** T9-08
**Plan refs:** FR-J.3

## Steps
- [ ] `components/DrugInfoPanel.jsx` for a drug id (showing the search result while the full drug loads): trade name, form · pack, category badge; **Uses**; **Warnings** in a red box (hidden when empty); **Active ingredients** as a list with their notes; **Suggested dose**; a "Use suggested dose" button → `onUseDose(text)`.
- [ ] Empty state ("Pick or highlight a drug to see its note") and a note for free-text lines ("Not in the catalogue").
- [ ] Sticky beside the form on wide screens; below the line on narrow screens.

## Done when
- [ ] Vitest: shows uses, warnings and ingredients; no warnings box when empty; the button sends the suggested dose.
