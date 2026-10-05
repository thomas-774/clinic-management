# T9-02 — Transcribe the PDF into the drug seed file

**Phase:** 9 · **Area:** Data · **Size:** L · **Depends on:** T9-01
**Plan refs:** FR-J.1 · §5.1 drugs · §10 Prescriptions defaults

The PDF's Arabic text layer is broken (its fonts don't map to Unicode, so `pdftotext` gives garbage), so the rows must be read from page images, not copied as text.

## Steps
- [x] Render every page of `Drugs-for-Dentistry.pdf` to PNG (PyMuPDF, at least 130 dpi) in a scratch folder (not committed).
  Note: the English words of the text layer (drug and ingredient names) are readable, so they were used to cross-check spelling.
- [x] Use the index (page 2) to map PDF pages to the 11 categories.
  Note: the index numbers are the printed footer numbers (= PDF page − 1). Each section starts with a cover page; the products are on PDF pages 4–14 (mouth cleaning), 16 (toothpaste), 18 (vitamin C), 20–21 (anti-inflammatory), 23–28 (antibiotics), 30–35 (analgesics / sedatives), 37–38 (antifungal), 40–41 (calcium), 43–44 (cod liver oil), 46–48 (local anaesthesia), 50–51 (other). `source_page` is the PDF file page.
- [x] For each product row, write one entry in `backend/database/data/drugs.json`:
  `seed_key`, `trade_name`, `form`, `pack`, `category`, `active_ingredients: [{ name, note }]`, `uses`, `warnings`, `suggested_dose`, `source_page`.
  - A row with several strengths (e.g. Flagyl 125 / 250 / 500 mg, Augmentin 375 / 625 / 1 g) becomes one entry per strength.
  - Lines marked `*` in the "use" column (e.g. "لا ينصح به للأطفال أو كبار السن", not in pregnancy, not with another drug) go into `warnings`, not `uses`.
  - **Skip the price column (السعر) entirely.**
  - Keep the PDF's wording (Arabic, English drug names); fix only obvious typos.
  Note: typos fixed — "للخروج" → "للجروح" (clove oil), Egyfluor → Elgyfluor, Seven seas / Fort sea / High sea / Halorang → Seven Seas / Forte Seas / High Seas / Halorange, ingredient "Lornicam" → lornoxicam, "fluride" → fluoride, "kids edge" → "kids age" (all as printed on the product photo in the same row). Warnings that were not starred but say who must not take it (Alpthtabn under 12, Oflam under 9, Lornicam max 16 mg/day, Cataflam after meals) also went into `warnings`. Where the pack size is not in the table it was taken from the product photo in the same row, or left empty. "Hibiotic 460 mg" syrup is listed twice with only a different price, so it is one entry.
- [x] `DrugSeeder`: reads the JSON and upserts by `seed_key` (re-running updates, never duplicates, and never touches drugs the doctor added); call it from `DatabaseSeeder`.
  Note: the seeder never sets `is_active`, so a seeded drug the doctor hid stays hidden after re-seeding.
- [x] A test validates the JSON: every entry has trade_name, form, a valid category, at least 1 ingredient and non-empty uses; seed_keys are unique; no key contains "price".
  Note: `tests/Feature/Database/DrugCatalogueTest.php` (also checks the file contains no "جنيه").
- [x] Produce a checking list (trade name · page) for the doctor and spot-check 10 random entries against the page images.
  Note: the list is `backend/database/data/drugs-checklist.md`. Final count: **128 entries** (more than the plan's "about 100" because every strength and form is its own entry): mouth cleaning 30, toothpaste 3, vitamin C 3, anti-inflammatory 5, antibiotics 24, analgesics 32, antifungal 6, calcium 6, cod liver oil 6, local anaesthesia 7, other 6. No row was unreadable. Spot-checked (all matched): Cataflam 75 mg, Mycostatin, Dentinox, Jobadel, Ambezim-G, Flumox 1 g vial, Thiotex 300 mg, Ketolgin 25 mg, Brufen 400 mg, Lignocaine spray.

## Done when
- [x] Every product in the PDF's 11 sections is in `drugs.json` (about 100 entries), with no prices.
- [x] `php artisan db:seed --class=DrugSeeder` run twice gives the same row count.
  Note: 128 → 128 on the dev DB.
- [x] The JSON validation test passes.
