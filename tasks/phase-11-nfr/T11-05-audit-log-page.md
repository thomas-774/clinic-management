# T11-05 — Activity log: API, Settings page and pruning

**Phase:** 11 · **Area:** Backend + Frontend · **Size:** M · **Depends on:** T11-04
**Plan refs:** §11.2 NFR-S.5

## Steps
- [x] `GET /doctor/audit-logs?patient_id=&user_id=&action=&from=&to=&page=` (doctor only, paginated 50, newest first), API Resource with user name, role, action, record type in the request language, patient name, field names translated, time, IP.
  Note: `AuditLogController` + `AuditLogFilterRequest` + `AuditLogResource`. Record types and field names are translated in `lang/{ar,en}/audit.php` (the generic validation names did not fit, e.g. Arabic `amount` = quantity); the action and role come as codes the frontend translates. `from` / `to` are inclusive clinic-time days. When filtered by patient the response also has `filters.patient` (id, name), so the page can name the patient without opening their record — which would itself write a `viewed` row.
- [x] Scheduled command `audit:prune` (daily) deletes rows older than `config('clinic.audit_retention_years')` (default 5).
  Note: deletes with a query in batches of 1,000 (never through the model); `CLINIC_AUDIT_RETENTION_YEARS` overrides the 5. Scheduled in `routes/console.php` — the server needs the usual `schedule:run` cron (T7-05).
- [x] Frontend: Settings → **Activity** tab, a table with filters (patient search, user, action, date range), ar + en strings, empty / loading / error states; link from a patient's page ("Activity on this patient").
  Note: `pages/doctor/settings/ActivityTab.jsx`. The users list is the doctor plus the assistants (Settings → Staff). The chosen patient is kept in `?patient=`, which the patient page's link sets (`/doctor/settings?tab=activity&patient={id}`).

## Done when
- [x] Feature tests: patient and assistant get 403; filters work; pagination; prune removes only old rows. (`tests/Feature/Doctor/AuditLogsTest.php`)
- [x] Vitest: filters build the right query; empty state shows. (`ActivityTab.test.jsx`, plus the link in `PatientDetails.test.jsx`)
- [ ] Walkthrough: open a patient, edit history, open Activity → both rows are there in Arabic and English.
  Note (Oct 7, 2026): covered at API level by the test "walkthrough: open a patient, edit history, then both rows show under Activity in Arabic and English", and the screen by Vitest in both languages. The in-browser walkthrough was not run: creating login tokens for a headless browser was blocked by this session's permission rules (same as T11-01's CSP check).
- [x] Both test suites still pass.
