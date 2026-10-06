# T10-03 — PDF renderer (mPDF)

**Phase:** 10 · **Area:** Backend · **Size:** M · **Depends on:** T10-02
**Plan refs:** FR-K.2, FR-K.5 · VR-3, VR-4

## Steps
- [x] `App\Services\VisitReport\PdfVisitReport::render(VisitReportData $data): string` (PDF bytes) using a Blade view `resources/views/reports/visit.blade.php` and mPDF.
- [x] A4 portrait, 15 mm margins, Cairo font, black on white; `dir="rtl"` and `lang="ar"` for Arabic, LTR for English. Phones, amounts and drug names in `dir="ltr"` spans (VR-4).
  Note: the bottom margin is 20 mm to leave room for the page-number footer. mPDF's per-script font switching is off, so Cairo is used for every script. Names, work done, address, forms and instructions are free text that may be in either language, so their block gets its direction from the first letter (`VisitReportData::textDir()`, like HTML's `dir="auto"`, which mPDF lacks) — without it "1 tablet every…" in the Arabic file showed its "1" at the end, and an Arabic address in the English file moved its "12". Work done is split into lines, each with its own direction.
  Note: Cairo's `mkmk` (mark-on-mark) GPOS lookups use mark filtering sets, which mPDF 8.3 refuses with "MarkGlyphSets - Not tested yet" as soon as a text has harakat (e.g. "مدفوع جزئيًا"). The view turns that one feature off with `font-feature-settings: "mkmk" off`; single marks are still placed by `mark`.
- [x] Layout, top to bottom: clinic header and thin rule · title "Visit report" · patient and visit block · "Work done today" · money box (total / paid / remaining, remaining in bold, red when > 0) · payments table · overall outstanding · prescriptions (only when there are any) · generated-on line and signature line.
  Note: with no payments the table is replaced by "No payments yet."; the overall outstanding is also red when > 0.
- [x] Tables repeat their header row on a new page; a payment row is never split; page numbers "1 / 2" in the footer.
- [x] PDF metadata title "Visit report — <date>".
  Note: author and creator are the clinic name (the doctor's name when no clinic name is set).

## Done when
- [x] Test: the output starts with `%PDF`; its text (extracted with `smalot/pdfparser` as a dev dependency, or mPDF's own text check) contains the work done, the three amounts and the patient's name, in ar and en.
  Note: `tests/Feature/Services/PdfVisitReportTest.php`, with `smalot/pdfparser` ^2.12 (dev). mPDF writes Arabic in visual order as presentation forms, so the helpers `pdfText()` (NFKC-normalized) and `pdfArabic()` (phrase in visual order) in `tests/Pest.php` are used to find Arabic labels. Also tested: A4 media box, title/creator metadata, 2 pages with the header row on both, no prescriptions section when there are none, no "SECRET-" text.
- [x] Rendered to PNG with PyMuPDF and checked by eye: Arabic letters joined, right-to-left, numbers not reversed, nothing cut off — for a short visit (1 page) and one with 20 payments (2 pages, header row repeated).
  Note: checked in ar and en with Arabic clinic, doctor, patient and work done. Backend 605 passed, frontend 227 passed, oxlint and Pint clean.
