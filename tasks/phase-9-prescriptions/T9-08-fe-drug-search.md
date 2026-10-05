# T9-08 — Frontend: DrugSearch combobox

**Phase:** 9 · **Area:** Frontend · **Size:** M · **Depends on:** T9-03
**Plan refs:** §7.3 DrugSearch · FR-J.2

## Steps
- [ ] `api/drugs.js` (search, get, list, create, update) and hooks `useDrugSearch(q)` (debounced 250 ms, enabled from 2 characters, keeps the previous results while loading) and `useDrug(id)`.
- [ ] `components/DrugSearch.jsx`: input and dropdown (trade name with the typed part in bold, form · pack, short use); ↑ ↓ moves, Enter picks, Esc closes; `onHighlight(drug)` fires as the highlight moves (for the side note); a "use as written" option when nothing matches; ARIA combobox roles.
- [ ] Works in RTL: the dropdown aligns to the input; drug names render with `dir="auto"`.
- [ ] i18n keys in ar.json and en.json.

## Done when
- [ ] Vitest: typing 1 character makes no request; typing "aug" shows results; ↓ then Enter picks the second result; Esc closes; no match offers the free-text option; onHighlight is called for the highlighted drug.
