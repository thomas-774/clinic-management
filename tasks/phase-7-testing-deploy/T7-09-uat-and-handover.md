# T7-09 — UAT with the doctor and handover

**Phase:** 7 · **Area:** QA / Handover · **Size:** M · **Depends on:** T7-06
**Plan refs:** §9.1 Manual / UAT · §8 Phase 7

## Steps
- [ ] UAT checklist: a full working day on real hours and prices (bookings, check-ins, visits, installments, reports).
- [ ] UAT of the Phase 11 safeguards (T11-04 … T11-06):
  - Audit log: the doctor opens a patient and edits a history entry; the assistant records a payment. Settings → Activity shows each one: who, when, the record, and the names of the changed fields (never their values), in Arabic and English. The doctor agrees to the 5-year retention.
  - Encryption: on staging, `SELECT details FROM medical_history_entries LIMIT 1` shows only ciphertext, while the same entry reads normally in the app. The doctor knows where production's `APP_KEY` is kept and that backups are unreadable without it (T7-07).
- [ ] Checks Phase 11 left for a person: an NVDA pass on the main flows (T11-13, plan §11.3: patient books; doctor records a visit with payment and prescription; assistant collects a payment); no CSP error in the browser console while downloading a visit file (T11-01); the Activity walkthrough in both languages (T11-05).
- [ ] Fix or log the issues found.
- [ ] Short user guide (1–2 pages, with screenshots): settings, schedule, visit form, reports.
- [ ] Hand over credentials securely; the doctor changes the password on first login.

## Done when
- [ ] The doctor signs off on v1.
