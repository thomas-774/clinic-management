# T10-02 — VisitReportService (the file's data)

**Phase:** 10 · **Area:** Backend · **Size:** M · **Depends on:** T10-01
**Plan refs:** FR-K.2, FR-K.3 · VR-1, VR-2, VR-3 · PR-1 – PR-3

## Steps
- [ ] `App\Services\VisitReport\VisitReportService::build(Visit $visit, string $locale): VisitReportData` — one plain, read-only object that both renderers use, so the PDF and the Word file can never disagree (VR-3).
- [ ] Sections and fields:
  - **Header:** clinic name, doctor name and title, address, phone (from `doctor_settings`, as the prescription header; empty parts left out).
  - **Patient:** name, phone, age (from date of birth; left out when unknown).
  - **Visit:** date, appointment time range or "walk-in", payment status (localized).
  - **Work done today:** the visit's `work_done`, line breaks kept.
  - **Money:** total cost, paid, remaining — all from `PaymentService` (VR-2), formatted `1,500.00 EGP` / `1,500.00 ج.م` by one formatter.
  - **Payments:** date, amount, method (localized), recorded by (name), oldest first.
  - **Overall outstanding:** the patient's remaining across all visits (FR-D.7).
  - **Prescriptions of this visit:** issued date, each line's drug name, form and instructions (snapshot fields only, RX-2/RX-4).
  - **Footer:** "Generated on <date time>" in Africa/Cairo, signature line label.
- [ ] Labels come from the lang files (`lang/ar.json`, `lang/en.json`, sorted as the other keys) using the given locale, not the request's global locale, so a test can build both languages.
- [ ] Nothing from medical history, current illness or drug notes is loaded (FR-K.3).

## Done when
- [ ] Unit/feature test: a visit with two payments and a linked prescription gives the expected values; paid/remaining match `VisitResource` for the same visit, also after an installment is added.
- [ ] Test: a walk-in visit, a patient with no date of birth and empty header parts leave those fields out without errors.
- [ ] Test: the data object has no history / illness / drug-note fields.
