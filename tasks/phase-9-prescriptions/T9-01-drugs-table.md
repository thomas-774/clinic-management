# T9-01 — Drugs table and model

**Phase:** 9 · **Area:** Backend · **Size:** S · **Depends on:** T1-06
**Plan refs:** §5.1 drugs · §5.3 · FR-J.1 · RX-3

## Steps
- [x] Migration `drugs`: trade_name, form, pack (nullable), category, active_ingredients (JSON), uses, warnings (nullable), suggested_dose (nullable), seed_key (nullable, UNIQUE), source_page (nullable), is_active (default true). **No price column.**
- [x] INDEX `(is_active, trade_name)`.
- [x] `DrugCategory` enum with the 11 PDF section keys (§5.1).
- [x] `Drug` model: `active_ingredients` cast to array, `is_active` to bool, `category` to the enum; scope `active()`.
- [x] `DrugFactory` with states `->hidden()` and `->category(...)`.

## Done when
- [x] Migration runs up and down cleanly on MySQL.
  Note: checked with `migrate` → `migrate:rollback --step=1` → `migrate` on the dev DB; `DrugModelTest` covers the schema, casts, scope and factory states.
- [x] A factory drug saves and reads back its ingredients as an array of `{ name, note }`.
- [x] All existing tests still pass.
