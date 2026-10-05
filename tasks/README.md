# Clinic Management System — Task Board

All tasks come from [Clinic_Management_System_Development_Plan.md](../Clinic_Management_System_Development_Plan.md).
Each task is one file: what to build, which plan section it comes from, the steps, and a "Done when" check.

## How to use

- Work phase by phase; inside a phase, follow the **Depends on** line of each task.
- Task IDs look like `T<phase>-<number>` (e.g. `T3-04`). Use them in branch names: `feature/T3-04-slot-service`.
- Tick the checkboxes in the task file while working, then mark the task `[x]` in the list below when every "Done when" item passes.
- **Size:** S = up to 2 hours · M = half a day · L = about a full day.
- **Plan refs** point back to the plan (FR-x.y requirements, BR/PR rules, § sections).

## Overview

| Phase | Folder | Tasks | Deliverable |
| --- | --- | --- | --- |
| 0 | [phase-0-setup](phase-0-setup/) | 6 | Both apps run locally and talk to each other |
| 1 | [phase-1-database-auth](phase-1-database-auth/) | 14 | Login works for both roles |
| 2 | [phase-2-profiles-history](phase-2-profiles-history/) | 9 | Patient page and doctor patient page |
| 3 | [phase-3-availability-slots](phase-3-availability-slots/) | 9 | Doctor sets hours; slots are generated correctly |
| 4 | [phase-4-booking-schedule](phase-4-booking-schedule/) | 11 | Patient books; doctor sees and checks in |
| 5 | [phase-5-visits-payments](phase-5-visits-payments/) | 11 | Visit form with automatic remaining balance |
| 6 | [phase-6-dashboard-reports](phase-6-dashboard-reports/) | 6 | Daily / weekly / monthly numbers |
| 7 | [phase-7-testing-deploy](phase-7-testing-deploy/) | 9 | Live v1 |
| | **Total** | **75** | |

Phase order: 0 → 1 → (2 and 3 in parallel) → 4 → 5 → 6 → 7. Phase 5 needs both 2 and 4.

## Phase 0 — Setup

- [x] [T0-01](phase-0-setup/T0-01-confirm-open-questions.md) Confirm open questions with the client · S
- [ ] [T0-02](phase-0-setup/T0-02-repo-and-branching.md) Create repository and branching rules · S
- [x] [T0-03](phase-0-setup/T0-03-laravel-backend-setup.md) Create Laravel backend · S
- [x] [T0-04](phase-0-setup/T0-04-react-frontend-setup.md) Create React (Vite) frontend · S
- [x] [T0-05](phase-0-setup/T0-05-cors-and-health-check.md) Connect frontend to backend (CORS + health check) · S
- [x] [T0-06](phase-0-setup/T0-06-test-tooling.md) Set up test tooling · S

## Phase 1 — Database and authentication

Backend
- [x] [T1-01](phase-1-database-auth/T1-01-migration-users-patients.md) Migrations: users and patients · S
- [x] [T1-02](phase-1-database-auth/T1-02-migration-medical-history.md) Migration: medical_history_entries · S
- [x] [T1-03](phase-1-database-auth/T1-03-migration-availability.md) Migrations: doctor_settings, working_hours, blocked_times · S
- [x] [T1-04](phase-1-database-auth/T1-04-migration-appointments.md) Migration: appointments (with active_slot) · M
- [x] [T1-05](phase-1-database-auth/T1-05-migration-visits-payments.md) Migrations: visits and payments · S
- [x] [T1-06](phase-1-database-auth/T1-06-enums-and-models.md) Enums and Eloquent models · M
- [x] [T1-07](phase-1-database-auth/T1-07-factories-and-seeders.md) Factories and seeders · M
- [x] [T1-08](phase-1-database-auth/T1-08-api-response-conventions.md) API response and error conventions · S
- [x] [T1-09](phase-1-database-auth/T1-09-auth-endpoints.md) Auth endpoints (register, login, logout, me) · M
- [x] [T1-10](phase-1-database-auth/T1-10-role-middleware.md) EnsureRole middleware and route groups · S

Frontend
- [x] [T1-11](phase-1-database-auth/T1-11-frontend-api-client.md) API client with token interceptor · S
- [x] [T1-12](phase-1-database-auth/T1-12-auth-context-and-guards.md) AuthContext, ProtectedRoute, RoleRoute · M
- [x] [T1-13](phase-1-database-auth/T1-13-login-register-pages.md) Login and Register pages · M
- [x] [T1-14](phase-1-database-auth/T1-14-layouts-and-i18n.md) Patient/Doctor layouts and i18n setup · M

## Phase 2 — Profiles and medical history (Modules B, C)

Backend
- [x] [T2-01](phase-2-profiles-history/T2-01-resources-and-policy.md) Patient resources and PatientPolicy · M
- [x] [T2-02](phase-2-profiles-history/T2-02-patient-profile-endpoints.md) Patient profile endpoints · M
- [x] [T2-03](phase-2-profiles-history/T2-03-doctor-patient-list.md) Doctor: patient list with search and create patient · M
- [x] [T2-04](phase-2-profiles-history/T2-04-doctor-patient-details.md) Doctor: patient details and update · M
- [x] [T2-05](phase-2-profiles-history/T2-05-history-crud.md) Medical history CRUD · M
- [x] [T2-06](phase-2-profiles-history/T2-06-history-privacy-tests.md) Tests: history privacy and profile access · S

Frontend
- [x] [T2-07](phase-2-profiles-history/T2-07-fe-patient-home.md) PatientHome page · M
- [x] [T2-08](phase-2-profiles-history/T2-08-fe-patients-list.md) PatientsList (doctor) with "New patient" form · M
- [x] [T2-09](phase-2-profiles-history/T2-09-fe-patient-details.md) PatientDetails with history management · L

## Phase 3 — Availability and slot engine (Module G)

Backend
- [x] [T3-01](phase-3-availability-slots/T3-01-settings-endpoints.md) Doctor settings endpoints · S
- [x] [T3-02](phase-3-availability-slots/T3-02-working-hours-endpoints.md) Working hours endpoints · M
- [x] [T3-03](phase-3-availability-slots/T3-03-blocked-times-endpoints.md) Blocked times endpoints · S
- [x] [T3-04](phase-3-availability-slots/T3-04-slot-service.md) SlotService::generate() · M
- [x] [T3-05](phase-3-availability-slots/T3-05-slot-service-tests.md) Unit tests for SlotService · M
- [x] [T3-06](phase-3-availability-slots/T3-06-slots-endpoint.md) GET /slots endpoint · S

Frontend
- [x] [T3-07](phase-3-availability-slots/T3-07-fe-settings-hours-duration.md) Settings: weekly hours and duration · M
- [x] [T3-08](phase-3-availability-slots/T3-08-fe-blocked-dates.md) Blocked dates management · S
- [x] [T3-09](phase-3-availability-slots/T3-09-fe-slot-preview.md) Live slot preview · S

## Phase 4 — Booking, schedule and check-in (Modules E, F)

Backend
- [x] [T4-01](phase-4-booking-schedule/T4-01-booking-service.md) BookingService (transactional booking) · L
- [x] [T4-02](phase-4-booking-schedule/T4-02-patient-appointment-endpoints.md) Patient appointment endpoints · M
- [x] [T4-03](phase-4-booking-schedule/T4-03-doctor-appointment-endpoints.md) Doctor appointment endpoints · S
- [x] [T4-04](phase-4-booking-schedule/T4-04-appointment-status-transitions.md) Appointment status transitions · M
- [x] [T4-05](phase-4-booking-schedule/T4-05-booking-tests.md) Tests: booking rules and double booking · M

Frontend
- [x] [T4-06](phase-4-booking-schedule/T4-06-fe-slot-grid.md) SlotGrid component and useSlots hook · S
- [x] [T4-07](phase-4-booking-schedule/T4-07-fe-book-appointment.md) BookAppointment page · M
- [x] [T4-08](phase-4-booking-schedule/T4-08-fe-my-appointments.md) MyAppointments page · S
- [x] [T4-09](phase-4-booking-schedule/T4-09-fe-schedule-day-view.md) Schedule day view with status actions · M
- [x] [T4-10](phase-4-booking-schedule/T4-10-fe-schedule-week-view.md) Schedule week view · S
- [x] [T4-11](phase-4-booking-schedule/T4-11-next-appointment-on-profile.md) Next appointment on the patient profile · S

## Phase 5 — Visits and payments (Module D)

Backend
- [x] [T5-01](phase-5-visits-payments/T5-01-payment-service.md) PaymentService (balances and rules) · M
- [x] [T5-02](phase-5-visits-payments/T5-02-payment-service-tests.md) Unit tests for PaymentService · S
- [x] [T5-03](phase-5-visits-payments/T5-03-create-visit-endpoint.md) POST /doctor/visits (visit + first payment) · M
- [x] [T5-04](phase-5-visits-payments/T5-04-update-visit-endpoint.md) PUT /doctor/visits/{id} · S
- [x] [T5-05](phase-5-visits-payments/T5-05-add-payment-endpoint.md) Add payment (installments) · S
- [x] [T5-06](phase-5-visits-payments/T5-06-visit-payment-feature-tests.md) Feature tests: visits and payments · M
- [x] [T5-07](phase-5-visits-payments/T5-07-balances-in-profiles.md) Visits and balances in the profile endpoints · S

Frontend
- [x] [T5-08](phase-5-visits-payments/T5-08-fe-visit-form.md) MoneyField and VisitForm · M
- [x] [T5-09](phase-5-visits-payments/T5-09-fe-start-visit-from-schedule.md) "Start visit" from the schedule · S
- [x] [T5-10](phase-5-visits-payments/T5-10-fe-visit-timeline.md) Visit timeline and installments on PatientDetails · M
- [x] [T5-11](phase-5-visits-payments/T5-11-fe-balance-on-patient-home.md) Outstanding balance on PatientHome · S

## Phase 6 — Dashboard and reports (Module H)

Backend
- [x] [T6-01](phase-6-dashboard-reports/T6-01-period-helper.md) Period helper and week-start config · S
- [x] [T6-02](phase-6-dashboard-reports/T6-02-report-service.md) ReportService aggregates and tests · M
- [x] [T6-03](phase-6-dashboard-reports/T6-03-report-endpoints.md) Report endpoints · M

Frontend
- [x] [T6-04](phase-6-dashboard-reports/T6-04-fe-dashboard.md) Dashboard · M
- [x] [T6-05](phase-6-dashboard-reports/T6-05-fe-reports-table.md) Reports page, filter and payments table · M
- [x] [T6-06](phase-6-dashboard-reports/T6-06-fe-revenue-chart.md) Daily revenue chart · S

## Phase 7 — Testing, polish and deployment

- [ ] [T7-01](phase-7-testing-deploy/T7-01-permission-feature-tests.md) Feature tests: permissions on every endpoint · M
- [ ] [T7-02](phase-7-testing-deploy/T7-02-frontend-tests.md) Frontend tests (Vitest + RTL) · M
- [ ] [T7-03](phase-7-testing-deploy/T7-03-ui-states-and-mobile.md) Empty / loading / error states; mobile check · M
- [ ] [T7-04](phase-7-testing-deploy/T7-04-demo-data-and-walkthrough.md) Demo data and full walkthrough · S
- [ ] [T7-05](phase-7-testing-deploy/T7-05-server-provisioning.md) Server provisioning · M
- [ ] [T7-06](phase-7-testing-deploy/T7-06-deploy-staging-production.md) Deploy backend and frontend (staging → production) · M
- [ ] [T7-07](phase-7-testing-deploy/T7-07-backups.md) Daily database backups · S
- [ ] [T7-08](phase-7-testing-deploy/T7-08-ci-pipeline.md) CI with GitHub Actions (optional) · S
- [ ] [T7-09](phase-7-testing-deploy/T7-09-uat-and-handover.md) UAT with the doctor and handover · M

## Requirement → task map

| Module | Requirements | Tasks |
| --- | --- | --- |
| A — Authentication | FR-A.1 – A.3 | T1-09, T1-10, T1-11, T1-12, T1-13 |
| B — Patient home | FR-B.1 – B.5 | T2-02, T2-07, T4-11, T5-07, T5-11 |
| C — Doctor patient page | FR-C.1 – C.6 | T2-03, T2-04, T2-05, T2-08, T2-09, T5-10 |
| D — Visits and payments | FR-D.1 – D.7 | T5-01 … T5-10 |
| E — Booking | FR-E.1 – E.5 | T3-06, T4-01, T4-02, T4-06, T4-07, T4-08 |
| F — Schedule and check-in | FR-F.1 – F.4 | T4-03, T4-04, T4-09, T4-10, T5-09 |
| G — Availability | FR-G.1 – G.4 | T3-01 … T3-09 |
| H — Dashboard and reports | FR-H.1 – H.4 | T6-01 … T6-06 |
