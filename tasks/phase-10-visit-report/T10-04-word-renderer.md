# T10-04 — Word renderer (PHPWord)

**Phase:** 10 · **Area:** Backend · **Size:** M · **Depends on:** T10-02
**Plan refs:** FR-K.2, FR-K.5 · VR-3, VR-4

## Steps
- [ ] `App\Services\VisitReport\WordVisitReport::render(VisitReportData $data): string` (`.docx` bytes) with PHPWord, written to a temp file and read back.
- [ ] Same sections, order and values as the PDF (VR-3), built with real Word headings, paragraphs and tables so the doctor can edit the file.
- [ ] Arabic: section `rtl`, paragraph `bidi`, table `bidiVisual`, and the complex-script font set (`rtl: true`, Arial — always present on Windows; Cairo is not usually installed on the doctor's PC). English: LTR, Arial.
- [ ] A4 portrait, the same margins; document properties title and creator (clinic name).

## Done when
- [ ] Test: the output is a valid zip with `word/document.xml`; the XML contains the work done, the three amounts and the patient's name, and `w:bidi` for ar but not for en.
- [ ] The file opens without a repair prompt in Microsoft Word and in LibreOffice (converted to PDF with `soffice --headless --convert-to pdf` if Word is not available here), Arabic right-to-left with tables mirrored.
