# T9-02 — Transcribe the PDF into the drug seed file

**Phase:** 9 · **Area:** Data · **Size:** L · **Depends on:** T9-01
**Plan refs:** FR-J.1 · §5.1 drugs · §10 Prescriptions defaults

The PDF's Arabic text layer is broken (its fonts don't map to Unicode, so `pdftotext` gives garbage), so the rows must be read from page images, not copied as text.

## Steps
- [ ] Render every page of `Drugs-for-Dentistry.pdf` to PNG (PyMuPDF, at least 130 dpi) in a scratch folder (not committed).
- [ ] Use the index (page 2) to map PDF pages to the 11 categories.
- [ ] For each product row, write one entry in `backend/database/data/drugs.json`:
  `seed_key`, `trade_name`, `form`, `pack`, `category`, `active_ingredients: [{ name, note }]`, `uses`, `warnings`, `suggested_dose`, `source_page`.
  - A row with several strengths (e.g. Flagyl 125 / 250 / 500 mg, Augmentin 375 / 625 / 1 g) becomes one entry per strength.
  - Lines marked `*` in the "use" column (e.g. "لا ينصح به للأطفال أو كبار السن", not in pregnancy, not with another drug) go into `warnings`, not `uses`.
  - **Skip the price column (السعر) entirely.**
  - Keep the PDF's wording (Arabic, English drug names); fix only obvious typos.
- [ ] `DrugSeeder`: reads the JSON and upserts by `seed_key` (re-running updates, never duplicates, and never touches drugs the doctor added); call it from `DatabaseSeeder`.
- [ ] A test validates the JSON: every entry has trade_name, form, a valid category, at least 1 ingredient and non-empty uses; seed_keys are unique; no key contains "price".
- [ ] Produce a checking list (trade name · page) for the doctor and spot-check 10 random entries against the page images.
  Note: record the final entry count and any rows that were unreadable here.

## Done when
- [ ] Every product in the PDF's 11 sections is in `drugs.json` (about 100 entries), with no prices.
- [ ] `php artisan db:seed --class=DrugSeeder` run twice gives the same row count.
- [ ] The JSON validation test passes.
