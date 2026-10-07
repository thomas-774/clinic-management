# T11-05 — Activity log: API, Settings page and pruning

**Phase:** 11 · **Area:** Backend + Frontend · **Size:** M · **Depends on:** T11-04
**Plan refs:** §11.2 NFR-S.5

## Steps
- [ ] `GET /doctor/audit-logs?patient_id=&user_id=&action=&from=&to=&page=` (doctor only, paginated 50, newest first), API Resource with user name, role, action, record type in the request language, patient name, field names translated, time, IP.
- [ ] Scheduled command `audit:prune` (daily) deletes rows older than `config('clinic.audit_retention_years')` (default 5).
- [ ] Frontend: Settings → **Activity** tab, a table with filters (patient search, user, action, date range), ar + en strings, empty / loading / error states; link from a patient's page ("Activity on this patient").

## Done when
- [ ] Feature tests: patient and assistant get 403; filters work; pagination; prune removes only old rows.
- [ ] Vitest: filters build the right query; empty state shows.
- [ ] Walkthrough: open a patient, edit history, open Activity → both rows are there in Arabic and English.
- [ ] Both test suites still pass.
