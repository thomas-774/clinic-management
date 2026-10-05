# T9-08 — Frontend: DrugSearch combobox

**Phase:** 9 · **Area:** Frontend · **Size:** M · **Depends on:** T9-03
**Plan refs:** §7.3 DrugSearch · FR-J.2

## Steps
- [x] `api/drugs.js` (search, get, list, create, update) and hooks `useDrugSearch(q)` (debounced 250 ms, enabled from 2 characters, keeps the previous results while loading) and `useDrug(id)`.
  Note: hooks are in `hooks/useDrugs.js`. `useDrugSearch` also returns `drugs` (empty under 2 characters) and `query` (the debounced text the results belong to). Search results are cached for 1 minute and a full drug for 5 minutes.
- [x] `components/DrugSearch.jsx`: input and dropdown (trade name with the typed part in bold, form · pack, short use); ↑ ↓ moves, Enter picks, Esc closes; `onHighlight(drug)` fires as the highlight moves (for the side note); a "use as written" option when nothing matches; ARIA combobox roles.
  Note: props are `onSelect(drug)`, `onFreeText(name)` and `onHighlight(drug | null)`; `onHighlight` gets `null` when the list closes. The first result starts highlighted, ↑ ↓ wrap around, the mouse can hover and click, and ↓ reopens a closed list. "Searching…" shows while the first results load. A drug that matched only by ingredient has no bold part. Also takes `inputRef`, `autoFocus`, `initialQuery`, `invalid` and `describedBy` for the prescription form (T9-10).
- [x] Works in RTL: the dropdown aligns to the input; drug names render with `dir="auto"`.
  Note: the list uses `inset-x-0`, so it spans the input in both directions; the input, names, form · pack and short use are `dir="auto"`, and the free-text name is wrapped in `<bdi>`.
- [x] i18n keys in ar.json and en.json.
  Note: the `drugSearch` section.

## Done when
- [x] Vitest: typing 1 character makes no request; typing "aug" shows results; ↓ then Enter picks the second result; Esc closes; no match offers the free-text option; onHighlight is called for the highlighted drug.
  Note: `components/DrugSearch.test.jsx` (10 tests, including debounce, ↑ wrap, mouse pick and Arabic).
