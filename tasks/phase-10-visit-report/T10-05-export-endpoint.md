# T10-05 — Export endpoint

**Phase:** 10 · **Area:** Backend · **Size:** S · **Depends on:** T10-03, T10-04
**Plan refs:** §6.3 · FR-K.1, FR-K.4, FR-K.5, FR-K.6 · VR-1, VR-5

## Steps
- [x] `GET /api/v1/doctor/visits/{visit}/export?format=pdf|docx` in the doctor route group → `Doctor\VisitExportController`.
  Note: a single-action controller, route name `doctor.visits.export`; the group's `role:doctor` middleware gives the 403s (FR-K.6).
- [x] Form Request: `format` required, `in:pdf,docx` (localized field name).
  Note: `ExportVisitRequest`; field name "file format" / "صيغة الملف" added to the lang files.
- [x] Locale from `Accept-Language` (the existing `SetLocale` middleware) passed to `VisitReportService::build`.
- [x] Response: the bytes with `Content-Type` `application/pdf` or `application/vnd.openxmlformats-officedocument.wordprocessingml.document`, `Content-Disposition: attachment; filename="visit-2026-10-07-88.pdf"` (VR-5), `Cache-Control: no-store`. Nothing is written to storage (VR-1).
  Note: the date in the name is the visit's date. Also sends `Content-Length`; Laravel adds `private` to `Cache-Control`, so the header is `no-store, private`.
- [x] CORS: add `Content-Disposition` to `exposed_headers` in `config/cors.php`, so the frontend can read the file name in dev.
- [x] Add the route to the plan's §6.3 table if anything changed while building it.
  Note: nothing changed; the §6.3 row already matches.

## Done when
- [x] Feature test: doctor gets 200 with the right content type and file name for both formats; `format=xls` or missing → 422; an unknown visit → 404.
  Note: `tests/Feature/Doctor/VisitExportTest.php`; also checks the file's magic bytes, `Content-Length`, `no-store` and the Arabic field name in the 422.
- [x] Feature test: with `Accept-Language: en` the PDF text holds "Work done"; with `ar` it holds the Arabic label.
  Note: backend 617 passed, frontend 227 passed, oxlint and Pint clean.
