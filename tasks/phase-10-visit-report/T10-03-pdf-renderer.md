# T10-03 — PDF renderer (mPDF)

**Phase:** 10 · **Area:** Backend · **Size:** M · **Depends on:** T10-02
**Plan refs:** FR-K.2, FR-K.5 · VR-3, VR-4

## Steps
- [ ] `App\Services\VisitReport\PdfVisitReport::render(VisitReportData $data): string` (PDF bytes) using a Blade view `resources/views/reports/visit.blade.php` and mPDF.
- [ ] A4 portrait, 15 mm margins, Cairo font, black on white; `dir="rtl"` and `lang="ar"` for Arabic, LTR for English. Phones, amounts and drug names in `dir="ltr"` spans (VR-4).
- [ ] Layout, top to bottom: clinic header and thin rule · title "Visit report" · patient and visit block · "Work done today" · money box (total / paid / remaining, remaining in bold, red when > 0) · payments table · overall outstanding · prescriptions (only when there are any) · generated-on line and signature line.
- [ ] Tables repeat their header row on a new page; a payment row is never split; page numbers "1 / 2" in the footer.
- [ ] PDF metadata title "Visit report — <date>".

## Done when
- [ ] Test: the output starts with `%PDF`; its text (extracted with `smalot/pdfparser` as a dev dependency, or mPDF's own text check) contains the work done, the three amounts and the patient's name, in ar and en.
- [ ] Rendered to PNG with PyMuPDF and checked by eye: Arabic letters joined, right-to-left, numbers not reversed, nothing cut off — for a short visit (1 page) and one with 20 payments (2 pages, header row repeated).
