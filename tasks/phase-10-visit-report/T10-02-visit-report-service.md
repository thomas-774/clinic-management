# T10-02 — VisitReportService (the file's data)

**Phase:** 10 · **Area:** Backend · **Size:** M · **Depends on:** T10-01
**Plan refs:** FR-K.2, FR-K.3 · VR-1, VR-2, VR-3 · PR-1 – PR-3

## Steps
- [x] `App\Services\VisitReport\VisitReportService::build(Visit $visit, string $locale): VisitReportData` — one plain, read-only object that both renderers use, so the PDF and the Word file can never disagree (VR-3).
  Note: `VisitReportData` is a `final readonly` class whose values are already localized and formatted (labels, dates, amounts), so a renderer only lays them out. An unknown locale falls back to `app.fallback_locale`.
- [x] Sections and fields:
  - **Header:** clinic name, doctor name and title, address, phone (from `doctor_settings`, as the prescription header; empty parts left out).
  - **Patient:** name, phone, age (from date of birth; left out when unknown).
  - **Visit:** date, appointment time range or "walk-in", payment status (localized).
  - **Work done today:** the visit's `work_done`, line breaks kept.
  - **Money:** total cost, paid, remaining — all from `PaymentService` (VR-2), formatted `1,500.00 EGP` / `1,500.00 ج.م` by one formatter.
  - **Payments:** date, amount, method (localized), recorded by (name), oldest first.
  - **Overall outstanding:** the patient's remaining across all visits (FR-D.7).
  - **Prescriptions of this visit:** issued date, each line's drug name, form and instructions (snapshot fields only, RX-2/RX-4).
  - **Footer:** "Generated on <date time>" in Africa/Cairo, signature line label.
  Note: visits have no doctor column, so the header uses `User::clinicDoctor()` (v1 has one doctor). Age is taken on the visit day, as the prescription takes it on the issue day. Dates read "7 Oct 2026" / "7 أكتوبر 2026" and times "10:00", like the frontend's `formatDate` / `formatTime`. The formatter is `VisitReportService::money()`; it groups the digits on the string, so no float touches an amount (PR-6). The prescription's general notes are not included (the task lists drug name, form and instructions only).
- [x] Labels come from the lang files (`lang/ar.json`, `lang/en.json`, sorted as the other keys) using the given locale, not the request's global locale, so a test can build both languages.
  Note: 35 keys added (section and column labels, payment statuses and methods, "EGP"); Arabic wording follows the frontend's (e.g. "بدون موعد", "استلم المبلغ").
- [x] Nothing from medical history, current illness or drug notes is loaded (FR-K.3).

## Done when
- [x] Unit/feature test: a visit with two payments and a linked prescription gives the expected values; paid/remaining match `VisitResource` for the same visit, also after an installment is added.
  Note: `tests/Feature/Services/VisitReportServiceTest.php`. The shared fixture `visitReportFixture()` in `tests/Pest.php` (full header, appointment, two payments by doctor and assistant, linked prescription, plus "SECRET-" history, illness, drug notes and another visit's prescription) is reused by T10-03 … T10-06.
- [x] Test: a walk-in visit, a patient with no date of birth and empty header parts leave those fields out without errors.
- [x] Test: the data object has no history / illness / drug-note fields.
  Note: checks the property list by reflection, and that no "SECRET-" text is in the built data in ar or en. Backend 599 passed, frontend 227 passed (run with `--maxWorkers=4`: under full parallel load a different `findBy…` test timed out on each run; they all pass alone), oxlint and Pint clean.
