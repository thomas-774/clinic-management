# T10-01 — Install the PDF and Word libraries

**Phase:** 10 · **Area:** Backend · **Size:** S · **Depends on:** —
**Plan refs:** §6.1 · FR-K.5 · VR-4

## Steps
- [ ] `composer require mpdf/mpdf phpoffice/phpword` in `backend/`.
  Why mPDF and not dompdf: dompdf does not join Arabic letters; mPDF shapes Arabic and lays out RTL on its own, in pure PHP (no Chrome or Node on the server).
- [ ] Confirm the PHP extensions they need are on (mbstring, gd, zip, xml, dom) locally and add them to the server checklist in T7-05.
- [ ] Bundle the app's font (Cairo, OFL licence — the frontend loads it from Google Fonts) as TTF in `backend/resources/fonts/` with its licence file, and register it in a small `config/visit_report.php` (font dir, font name, temp dir under `storage/app/mpdf`).
- [ ] mPDF's temp dir is created on first use and is git-ignored.

## Done when
- [ ] A throwaway tinker/Pest check renders "مرحبا Hello 1,500.00" with mPDF in Cairo and the Arabic word is joined (checked by rendering the page to PNG with PyMuPDF and looking at it).
- [ ] A throwaway PHPWord document with one RTL paragraph opens in Word.
- [ ] Both test suites still pass.
