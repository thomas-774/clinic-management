# T10-05 — Export endpoint

**Phase:** 10 · **Area:** Backend · **Size:** S · **Depends on:** T10-03, T10-04
**Plan refs:** §6.3 · FR-K.1, FR-K.4, FR-K.5, FR-K.6 · VR-1, VR-5

## Steps
- [ ] `GET /api/v1/doctor/visits/{visit}/export?format=pdf|docx` in the doctor route group → `Doctor\VisitExportController`.
- [ ] Form Request: `format` required, `in:pdf,docx` (localized field name).
- [ ] Locale from `Accept-Language` (the existing `SetLocale` middleware) passed to `VisitReportService::build`.
- [ ] Response: the bytes with `Content-Type` `application/pdf` or `application/vnd.openxmlformats-officedocument.wordprocessingml.document`, `Content-Disposition: attachment; filename="visit-2026-10-07-88.pdf"` (VR-5), `Cache-Control: no-store`. Nothing is written to storage (VR-1).
- [ ] CORS: add `Content-Disposition` to `exposed_headers` in `config/cors.php`, so the frontend can read the file name in dev.
- [ ] Add the route to the plan's §6.3 table if anything changed while building it.

## Done when
- [ ] Feature test: doctor gets 200 with the right content type and file name for both formats; `format=xls` or missing → 422; an unknown visit → 404.
- [ ] Feature test: with `Accept-Language: en` the PDF text holds "Work done"; with `ar` it holds the Arabic label.
