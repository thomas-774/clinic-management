# T10-01 — Install the PDF and Word libraries

**Phase:** 10 · **Area:** Backend · **Size:** S · **Depends on:** —
**Plan refs:** §6.1 · FR-K.5 · VR-4

## Steps
- [x] `composer require mpdf/mpdf phpoffice/phpword` in `backend/`.
  Why mPDF and not dompdf: dompdf does not join Arabic letters; mPDF shapes Arabic and lays out RTL on its own, in pure PHP (no Chrome or Node on the server).
  Note: mpdf/mpdf 8.3.1 and phpoffice/phpword 1.4.0, constrained `^8.3` / `^1.4`.
- [x] Confirm the PHP extensions they need are on (mbstring, gd, zip, xml, dom) locally and add them to the server checklist in T7-05.
  Note: all five are on in Laragon's PHP 8.3.33; T7-05 also says `storage/app/mpdf` must be writable.
- [x] Bundle the app's font (Cairo, OFL licence — the frontend loads it from Google Fonts) as TTF in `backend/resources/fonts/` with its licence file, and register it in a small `config/visit_report.php` (font dir, font name, temp dir under `storage/app/mpdf`).
  Note: upstream (Gue3bara/Cairo) only ships a variable font, which mPDF cannot use for bold, so the static Regular (400) and Bold (700) TTFs come from Google Fonts' own instances, in `resources/fonts/cairo/` with `OFL.txt`. The config also holds the Word font (Arial, see T10-04) and the paper size (A4).
- [x] mPDF's temp dir is created on first use and is git-ignored.
  Note: `storage/app/.gitignore` already ignores everything but `private/` and `public/`, so no new rule was needed.

## Done when
- [x] A throwaway tinker/Pest check renders "مرحبا Hello 1,500.00" with mPDF in Cairo and the Arabic word is joined (checked by rendering the page to PNG with PyMuPDF and looking at it).
  Note: checked by eye: Arabic joined and RTL, "1,500.00" not reversed inside it, bold works, and the PDF embeds `Cairo-Regular` and `Cairo-Bold` subsets. Kept as a permanent smoke test, `tests/Feature/Services/VisitReportLibrariesSmokeTest.php` (fonts present, mPDF embeds Cairo, PHPWord writes `w:bidi`), so a missing font or extension on the server fails the suite.
- [x] A throwaway PHPWord document with one RTL paragraph opens in Word.
  Note: opened in Word 16 over COM (no repair prompt) and exported to PDF: Arabic RTL, the `bidiVisual` table mirrored. For T10-04: Arabic text came out smaller than English at the same `size`, so the complex-script size must be set too.
- [x] Both test suites still pass.
  Note: backend 588 passed, frontend 227 passed, oxlint and Pint clean.
