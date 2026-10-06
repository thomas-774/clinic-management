# T10-04 — Word renderer (PHPWord)

**Phase:** 10 · **Area:** Backend · **Size:** M · **Depends on:** T10-02
**Plan refs:** FR-K.2, FR-K.5 · VR-3, VR-4

## Steps
- [x] `App\Services\VisitReport\WordVisitReport::render(VisitReportData $data): string` (`.docx` bytes) with PHPWord, written to a temp file and read back.
- [x] Same sections, order and values as the PDF (VR-3), built with real Word headings, paragraphs and tables so the doctor can edit the file.
  Note: Heading 1 for the title and Heading 2 for the sections; the clinic header's rule is a paragraph bottom border; payments and prescription tables repeat their header row and their rows cannot split; the footer has "PAGE / NUMPAGES", as the PDF's "1 / 2". Free text takes its direction from its first letter (`VisitReportData::textDir()`, as in the PDF); a paragraph in the other direction is aligned to "end" so it stays on the file's side.
- [x] Arabic: section `rtl`, paragraph `bidi`, table `bidiVisual`, and the complex-script font set (`rtl: true`, Arial — always present on Windows; Cairo is not usually installed on the doctor's PC). English: LTR, Arial.
  Note: PHPWord has no section direction, so `<w:bidi/>` is added to `w:sectPr` when the temp file is read back. Every run gets an explicit size, which PHPWord also writes as `w:szCs` (the T10-01 finding), and bold writes `w:bCs`. Only Arabic runs are `w:rtl`; phones and amounts stay LTR runs. The theme language is en-US / ar-EG. PHPWord marks cells `noWrap` by default, so every cell is created with `noWrap: false`, or long instructions would widen the table.
- [x] A4 portrait, the same margins; document properties title and creator (clinic name).
  Note: page size and margins are written as whole twips (11906 × 16838, 850): PHPWord's own A4 size has decimals, which OOXML does not allow — a first version with them hung Word on a hidden dialog when opened over COM.

## Done when
- [x] Test: the output is a valid zip with `word/document.xml`; the XML contains the work done, the three amounts and the patient's name, and `w:bidi` for ar but not for en.
  Note: `tests/Feature/Services/WordVisitReportTest.php`; also checks A4 size and margins, Arial for complex script, headings, repeated header row, no `noWrap`, section `bidi`, `bidiVisual`, title and creator, no "SECRET-" text.
- [x] The file opens without a repair prompt in Microsoft Word and in LibreOffice (converted to PDF with `soffice --headless --convert-to pdf` if Word is not available here), Arabic right-to-left with tables mirrored.
  Note: opened in Word 16 over COM (read-only, alerts off, 60 s watchdog) and exported to PDF, then rendered with PyMuPDF: short (1 page) and 20-payment (2 pages, header row repeated) visits in ar and en, with Arabic clinic, doctor, patient and work done. LibreOffice is not installed on this machine; Word was available, as the step allows. Backend 609 passed, frontend 227 passed, oxlint and Pint clean.
