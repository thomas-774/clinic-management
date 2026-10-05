# T9-01 — Drugs table and model

**Phase:** 9 · **Area:** Backend · **Size:** S · **Depends on:** T1-06
**Plan refs:** §5.1 drugs · §5.3 · FR-J.1 · RX-3

## Steps
- [ ] Migration `drugs`: trade_name, form, pack (nullable), category, active_ingredients (JSON), uses, warnings (nullable), suggested_dose (nullable), seed_key (nullable, UNIQUE), source_page (nullable), is_active (default true). **No price column.**
- [ ] INDEX `(is_active, trade_name)`.
- [ ] `DrugCategory` enum with the 11 PDF section keys (§5.1).
- [ ] `Drug` model: `active_ingredients` cast to array, `is_active` to bool, `category` to the enum; scope `active()`.
- [ ] `DrugFactory` with states `->hidden()` and `->category(...)`.

## Done when
- [ ] Migration runs up and down cleanly on MySQL.
- [ ] A factory drug saves and reads back its ingredients as an array of `{ name, note }`.
- [ ] All existing tests still pass.
