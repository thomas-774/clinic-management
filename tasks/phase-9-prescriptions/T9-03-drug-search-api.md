# T9-03 — Drug search and catalogue API

**Phase:** 9 · **Area:** Backend · **Size:** M · **Depends on:** T9-01, T9-02
**Plan refs:** §6.3 · FR-J.2, FR-J.3, FR-J.6 · RX-3

## Steps
- [x] `GET /doctor/drugs/search?q=` (role:doctor): `q` from 2 characters (shorter returns an empty list); active drugs only; ranking: trade_name starts with `q`, then trade_name contains `q`, then an active ingredient name contains `q`; case-insensitive; max 15; returns id, trade_name, form, pack, category and the first line of `uses`.
  Note: ingredient matching can use `JSON_SEARCH` or a generated `ingredients_text` column; pick whichever stays simple on MySQL 8.
  Note: neither — `Drug::scopeMatching()` lower-cases `JSON_EXTRACT(active_ingredients, '$[*].name')` and matches it with `LIKE`, so no extra column (JSON_SEARCH is case-sensitive on JSON strings). `%` and `_` in `q` are escaped. The first line of `uses` is returned as `short_use` (DrugSuggestionResource).
- [x] `GET /doctor/drugs/{id}`: the full `DrugResource` for the side note (hidden drugs still readable).
- [x] `GET /doctor/drugs?search=&category=&include_hidden=`: paginated catalogue for Settings.
  Note: 20 per page, ordered by trade name; `search` uses the same trade-name / ingredient match; hidden drugs only with `include_hidden=1`; an unknown `category` → 422.
- [x] `POST /doctor/drugs` and `PUT /doctor/drugs/{id}` with `StoreDrugRequest` / `UpdateDrugRequest` (ingredients: 1–10 items, `name` required, `note` optional); hide or show with `is_active`. No delete endpoint (old prescriptions keep the link).
  Note: on `PUT` every field is optional (same rules when sent), so Settings can hide or show a drug with `{ "is_active": false }` alone. `seed_key`, `source_page` and any `price` in the request are ignored; an empty ingredient note is stored as `null`.
- [x] Localized validation messages (ar / en).

## Done when
- [x] Feature tests: "aug" returns Augmentin entries before others; an ingredient search ("amoxicillin") finds Augmentin, Flumox and Hibiotic; hidden drugs never appear in search but open by id; a 1-character query returns `[]`; at most 15 results.
  Note: `tests/Feature/Doctor/DrugsTest.php`; the "aug" / "amoxicillin" / 15-results tests run against the seeded PDF catalogue.
- [x] Create, edit and hide work; validation errors return 422.
