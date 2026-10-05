# Clinic Management System — Development Plan

Oct 5, 2026 · Thomas

## 1. Project Overview

Version 1 is a single-clinic web app with three roles (patient, doctor and front-desk assistant) and three core areas: profile and medical history, visits and payments, and appointment booking with automatic slot generation.

**Goal.** Let patients see their own record and book appointments online, and let the doctor manage records, visits, payments, schedule and revenue from one dashboard.

**Tech stack**

| Layer | Technology | Notes |
| --- | --- | --- |
| Frontend | React (Vite) + React Router | Tailwind CSS for styling, Axios for API calls, TanStack Query for server state |
| Backend | Laravel 13 (REST API) | Laravel Sanctum for token auth, Form Requests for validation, API Resources for JSON |
| Database | MySQL 8 (SQL) | Managed through Laravel migrations and seeders |
| Tooling | Git + GitHub, Postman, PHPUnit/Pest, Vitest | One repo with `/backend` and `/frontend`, or two repos |

**In scope for v1**

- Patient home page: name, phone, address, current illness, simple medical history.
- Doctor patient page: same data plus detailed medical history, today's treatment notes, and payment (paid, total, remaining).
- Booking: patient picks a free slot; doctor sees all bookings, marks arrival, sets working hours and slot duration.
- Dashboard: patient count and revenue per day, week and month, with per-patient payments.
- Interface in Arabic (default, right-to-left) and English, with a language switcher.

- Assistant (front desk): registers new patients, records what the patient pays, runs today's queue and books appointments (Module I, added in Phase 8).

**Out of scope for v1** (can be added later): multiple doctors or clinics, SMS/WhatsApp reminders, online payment, prescriptions printing, file uploads (X-rays, lab results).

## 2. User Roles and Permissions

There are three roles; every API route checks the role, and a patient can only ever read their own data. The assistant (added in Phase 8, Module I) works the front desk and never sees medical data.

| Capability | Patient | Doctor | Assistant |
| --- | --- | --- | --- |
| Register / log in | Yes (self-register, or account created by the doctor) | Yes (account seeded) | Yes (account created by the doctor; can be deactivated) |
| Create patient accounts | No | Yes (for patients who call by phone) | Yes (no illness field) |
| View own profile (name, phone, address, illness) | Yes | — | — |
| View any patient's profile | No | Yes | Contact info, visit dates and money only |
| Medical history | Simple summary only | Full detailed history, can add/edit | No |
| Record today's treatment (visit notes, total) | No | Yes | No (never sees work done) |
| Record payments (paid / total / remaining) | No (sees own balance) | Yes | Yes (records amounts paid; cannot change the total) |
| See available slots and book | Yes | Yes (can book on behalf of a patient) | Yes (on behalf of a patient) |
| Cancel own appointment | Yes (up to the cancellation cut-off, default 2 h before start) | Yes (any) | Yes (any) |
| View all appointments / schedule | No (own only) | Yes | Yes |
| Mark patient Arrived / Checked In | No | Yes | Yes (also No-show; not Completed) |
| Set working hours and slot duration | No | Yes | No |
| Revenue and patient-count dashboard | No | Yes | No |
| Manage assistant accounts | No | Yes | No |

**Simple vs detailed history.** Each history entry has a `visibility` flag: `patient_visible` entries appear on the patient page as the simple history; all entries (including private clinical notes) appear for the doctor.

## 3. Functional Requirements by Module

The system is split into nine modules (A–I); each requirement has an ID (FR-x.y) so it can be traced to tasks and tests.

### Module A — Authentication

- **FR-A.1** Patient registers with name, phone, email (optional), password, address.
- **FR-A.2** Patient and doctor log in with phone/email + password; API returns a Sanctum token and the user's role.
- **FR-A.3** Log out revokes the token. Unauthenticated users are redirected to the login page.

### Module B — Patient Home Page (patient view)

- **FR-B.1** Shows personal info: full name, phone number, address.
- **FR-B.2** Shows the current illness / main complaint.
- **FR-B.3** Shows the simple medical history: only entries marked visible to the patient (date, title, short description).
- **FR-B.4** Shows the next upcoming appointment (date, time, status) and current remaining balance.
- **FR-B.5** Patient can edit phone and address only; illness and history are edited by the doctor.

### Module C — Doctor Patient Page (doctor view)

- **FR-C.1** Patient list with search by name or phone.
- **FR-C.2** Opening a patient shows the same info as the patient page (name, phone, address, illness).
- **FR-C.3** Shows the detailed medical history: all entries, including chronic conditions, allergies, surgeries, medications and private notes.
- **FR-C.4** Doctor can add, edit and delete history entries and set each entry's visibility.
- **FR-C.5** Shows the visit timeline: every past visit with its work notes and payment.
- **FR-C.6** Doctor can create a patient account (name, phone, address, optional email) for a patient who calls by phone; the system generates an initial password that is shown once so the doctor can give it to the patient.

### Module D — Visits and Payments (doctor view)

- **FR-D.1** Doctor opens a "Today's visit" form for a patient (usually from a checked-in appointment).
- **FR-D.2** Field: **Work done today** — free text for the treatment/procedure performed.
- **FR-D.3** Fields: **Total cost** and **Amount paid now**.
- **FR-D.4** **Remaining balance** is calculated automatically (total − all payments for that visit) and shown read-only; it is never typed by hand.
- **FR-D.5** If remaining > 0 the visit is flagged "Unpaid balance" and it appears on the patient page and in the doctor patient page.
- **FR-D.6** Doctor can add a later payment against an old visit (installments); the balance updates.
- **FR-D.7** Patient's overall outstanding balance = sum of remaining across all visits.

### Module E — Booking (patient view)

- **FR-E.1** Patient picks a date (today up to 30 days ahead).
- **FR-E.2** System shows generated slots for that date; booked or past slots are hidden or disabled.
- **FR-E.3** Patient selects a slot and confirms; the appointment is saved with the exact start and end time.
- **FR-E.4** Patient sees their upcoming and past appointments and can cancel an upcoming one until the cancellation cut-off (BR-5).
- **FR-E.5** A patient can hold only one future appointment at a time (prevents slot hoarding). The limit is a config value so it can be raised later without code changes.

### Module F — Schedule and Check-in (doctor view)

- **FR-F.1** Day view (default today) listing all appointments ordered by time: time, patient name, phone, status.
- **FR-F.2** Week view to scan upcoming days.
- **FR-F.3** Button **Mark Arrived / Checked In** on each appointment; then **Start visit** opens the visit form (Module D).
- **FR-F.4** Doctor can also mark **Completed**, **No-show** or **Cancelled**.

### Module G — Availability Settings (doctor view)

- **FR-G.1** Doctor sets working hours per weekday as one or more time ranges, so a day can include a break (e.g. Sat–Thu 17:00–21:00, Tuesday 10:00–13:00 and 17:00–21:00, Friday off).
- **FR-G.2** Doctor sets appointment duration in minutes (default 45; e.g. 60) and the patient cancellation cut-off in hours (default 2).
- **FR-G.3** Doctor can block a specific date (holiday) or a time range on a date.
- **FR-G.4** Changes apply to future slot generation only; already-booked appointments keep their times.

### Module H — Dashboard and Reports (doctor view)

- **FR-H.1** Cards for **Today / This week / This month**: number of patients seen and total revenue.
- **FR-H.2** Revenue = sum of payments received in that period (cash actually collected), with outstanding balances shown separately.
- **FR-H.3** Table of payments in the selected period: date, patient, visit total, paid, remaining.
- **FR-H.4** A revenue-per-day chart for the selected month.

### Module I — Assistant (front desk)

Added in Phase 8. The doctor sets the visit's work done and total cost; the assistant collects the money.

- **FR-I.1** The doctor creates assistant accounts (name, phone, initial password shown once) in Settings → Staff, can edit them, reset the password and deactivate them. A deactivated assistant cannot log in and their tokens are revoked.
- **FR-I.2** The assistant searches patients by name or phone and creates a patient account for a patient who does not exist yet (name, phone, address, optional email, date of birth, gender — no current illness). The initial password is shown once.
- **FR-I.3** The assistant opens a patient and sees contact info, outstanding balance and every visit's date, total, paid, remaining and payments — never medical history, current illness or work done. The assistant can edit contact info only.
- **FR-I.4** A "Waiting to pay" list shows the day's visits with a remaining balance; the assistant records the amount the patient pays (full or partial, cash/card/wallet) under the same rules as the doctor (PR-1 – PR-3). Later installments are recorded the same way from the patient page.
- **FR-I.5** Every payment stores who recorded it (`recorded_by`); the doctor's payments report shows it.
- **FR-I.6** The assistant sees the schedule (today's queue, day/week), marks patients Arrived, No-show or Cancelled, and books appointments on a patient's behalf (same booking rules; the patient cancellation cut-off BR-5 does not apply to the assistant). Completed is set only by the doctor's visit.

## 4. Business Rules

Slots are never stored in advance; they are calculated on request from the doctor's working hours and duration, minus anything already booked or blocked.

### 4.1 Slot generation algorithm

Input: a date. Output: list of free start times.

1. Read the doctor's working-hour ranges for that weekday (e.g. 17:00–21:00, or 10:00–13:00 and 17:00–21:00). If there are none (day off) or the whole day is blocked, return an empty list.
2. Read the slot duration D from settings (e.g. 45 min).
3. For each range separately: start at `start_time`; while `slot_start + D <= end_time`, add the slot and move `slot_start += D`. A slot never crosses a break.
4. Remove slots that overlap an existing appointment with status Booked, Checked In or Completed.
5. Remove slots that overlap a blocked time range.
6. If the date is today, remove slots whose start time has already passed.

**Worked examples (hours 17:00–21:00)**

| Duration | Generated slots | Note |
| --- | --- | --- |
| 45 min | 17:00, 17:45, 18:30, 19:15, 20:00 | 20:45 is dropped because it would end at 21:30, after closing |
| 60 min | 17:00, 18:00, 19:00, 20:00 | Last slot ends exactly at 21:00 |
| 30 min | 17:00, 17:30 … 20:30 | 8 slots |

**With a break (10:00–13:00 and 17:00–21:00, 60 min):** 10:00, 11:00, 12:00, 17:00, 18:00, 19:00, 20:00. The second range starts its own cursor at 17:00.

```php
// SlotService::generate(Carbon $date): array
foreach ($ranges as $range) {            // working_hours rows for this weekday
    $cursor = $date->copy()->setTimeFromTimeString($range->start_time);
    $end    = $date->copy()->setTimeFromTimeString($range->end_time);
    while ($cursor->copy()->addMinutes($duration)->lte($end)) {
        $slots[] = [$cursor->copy(), $cursor->copy()->addMinutes($duration)];
        $cursor->addMinutes($duration);
    }
}
return array_filter($slots, fn($s) => !$this->isTaken($s) && !$this->isBlocked($s) && $s[0]->isFuture());
```

### 4.2 Booking rules

- **BR-1** The booked start time must be one of the slots generated for that date at the moment of booking (the server re-generates and checks; the frontend list is not trusted).
- **BR-2** No double booking: a unique index on `(doctor_id, start_at)` plus a database transaction with a row lock makes two simultaneous requests safe — the second one gets "slot no longer available".
- **BR-3** `end_at = start_at + duration` is stored on the appointment, so changing the duration later does not move existing bookings.
- **BR-4** One active future appointment per patient. The limit is read from `config('clinic.max_active_appointments')` (default 1) so it can be changed later.
- **BR-5** Patients can cancel only until `start_at − cancel_cutoff_hours` (doctor setting, default 2 h); after that only the doctor can cancel. A cancelled slot becomes free again.

### 4.3 Appointment status lifecycle

| From | Action (who) | To |
| --- | --- | --- |
| — | Patient books (patient/doctor) | Booked |
| Booked | Patient arrives (doctor) | Checked In |
| Booked | Cancel (patient/doctor) | Cancelled |
| Booked | Patient never came (doctor) | No-show |
| Checked In | Visit saved (doctor) | Completed |
| Checked In | Patient leaves without a visit (doctor) | Cancelled |

### 4.4 Payment rules

- **PR-1** `remaining = visit.total_amount − SUM(payments.amount for that visit)`; always calculated on the server, never entered.
- **PR-2** A payment cannot exceed the current remaining amount; total cannot be lower than what has already been paid.
- **PR-3** Payment status per visit: Paid (remaining = 0), Partially paid (0 < paid < total), Unpaid (paid = 0).
- **PR-4** Revenue for a period = sum of `payments.amount` where `paid_at` is in the period (so an installment paid in November counts in November).
- **PR-5** Patients counted in a period = distinct patients with a Completed visit in that period.
- **PR-6** Amounts are stored as `DECIMAL(10,2)` in EGP; never as float.

## 5. Database Design (MySQL)

Nine tables cover the whole v1; every table also has `id` (BIGINT PK) and `created_at` / `updated_at`.

### 5.1 Tables

**users** — login accounts for both roles

| Column | Type | Notes |
| --- | --- | --- |
| name | VARCHAR(120) | |
| phone | VARCHAR(20) | UNIQUE, used for login |
| email | VARCHAR(150) | UNIQUE, nullable |
| password | VARCHAR(255) | bcrypt hash |
| role | ENUM('patient','doctor','assistant') | `assistant` added in Phase 8 |
| is_active | BOOLEAN | default true; false blocks login (assistants, FR-I.1) |

**patients** — one row per patient user

| Column | Type | Notes |
| --- | --- | --- |
| user_id | FK → users.id | UNIQUE |
| address | VARCHAR(255) | |
| date_of_birth | DATE | nullable |
| gender | ENUM('male','female') | nullable |
| current_illness | TEXT | main complaint, set by doctor |

**medical_history_entries**

| Column | Type | Notes |
| --- | --- | --- |
| patient_id | FK → patients.id | |
| type | ENUM('condition','allergy','surgery','medication','note') | |
| title | VARCHAR(150) | e.g. "Diabetes type 2" |
| details | TEXT | detailed description (doctor only) |
| patient_visible | BOOLEAN | TRUE = shown in simple history |
| recorded_on | DATE | |

**doctor_settings** — single row for the one doctor

| Column | Type | Notes |
| --- | --- | --- |
| doctor_id | FK → users.id | UNIQUE |
| slot_duration_minutes | SMALLINT | default 45 |
| booking_window_days | SMALLINT | default 30 |
| cancel_cutoff_hours | SMALLINT | default 2; patients cannot cancel later than this before start |

**working_hours** — one row per working time range; a weekday can have several rows (a break is the gap between them), and a weekday with no rows is a day off

| Column | Type | Notes |
| --- | --- | --- |
| doctor_id | FK → users.id | |
| day_of_week | TINYINT | 0 = Sunday … 6 = Saturday |
| start_time | TIME | e.g. 17:00 |
| end_time | TIME | e.g. 21:00, must be > start_time; ranges on the same day must not overlap |

**blocked_times** — holidays and exceptions

| Column | Type | Notes |
| --- | --- | --- |
| doctor_id | FK → users.id | |
| date | DATE | |
| start_time / end_time | TIME | both NULL = whole day blocked |
| reason | VARCHAR(150) | nullable |

**appointments**

| Column | Type | Notes |
| --- | --- | --- |
| doctor_id | FK → users.id | |
| patient_id | FK → patients.id | |
| start_at | DATETIME | exact booked time |
| end_at | DATETIME | start_at + duration at booking time |
| status | ENUM('booked','checked_in','completed','cancelled','no_show') | |
| checked_in_at | DATETIME | nullable, set on Arrived |
| cancelled_at | DATETIME | nullable |

**visits** — what was done in one session

| Column | Type | Notes |
| --- | --- | --- |
| patient_id | FK → patients.id | |
| appointment_id | FK → appointments.id | nullable (walk-ins), UNIQUE |
| visit_date | DATE | |
| work_done | TEXT | "what we will work today" |
| total_amount | DECIMAL(10,2) | |

**payments** — supports installments

| Column | Type | Notes |
| --- | --- | --- |
| visit_id | FK → visits.id | |
| amount | DECIMAL(10,2) | > 0 |
| method | ENUM('cash','card','wallet') | default cash |
| paid_at | DATETIME | used for revenue reports |
| recorded_by | FK → users.id | nullable; doctor or assistant who took the money (FR-I.5) |

### 5.2 Relationships

- users 1—1 patients · patients 1—N medical_history_entries · patients 1—N appointments
- appointments 1—0..1 visits · visits 1—N payments
- doctor (users) 1—1 doctor_settings · 1—N working_hours · 1—N blocked_times · 1—N appointments

### 5.3 Indexes and constraints

- UNIQUE `(doctor_id, start_at)` on appointments, only for active statuses — in MySQL, add a generated column `active_slot` = `start_at` when status ∈ (booked, checked_in, completed) else NULL, and put the unique index on `(doctor_id, active_slot)`; cancelled slots can then be rebooked.
- INDEX `(doctor_id, day_of_week)` on working_hours (no unique constraint, because a day can have several ranges).
- INDEX `(doctor_id, start_at)` for the schedule; INDEX `(paid_at)` on payments for reports; INDEX `(patient_id)` on every child table.
- Remaining balance is **not stored**; it is computed (`total_amount − SUM(payments)`) in a query or Eloquent accessor to avoid stale data.

## 6. Backend (Laravel) Architecture

The backend is a stateless REST API under `/api/v1`, with thin controllers and the real logic in service classes.

### 6.1 Folder structure

```text
backend/app/
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── AuthController.php
│   │   ├── Patient/ProfileController.php
│   │   ├── Patient/AppointmentController.php
│   │   ├── Doctor/PatientController.php
│   │   ├── Doctor/MedicalHistoryController.php
│   │   ├── Doctor/VisitController.php
│   │   ├── Doctor/PaymentController.php
│   │   ├── Doctor/ScheduleController.php
│   │   ├── Doctor/SettingsController.php
│   │   └── Doctor/ReportController.php
│   ├── Middleware/EnsureRole.php
│   ├── Requests/        (one Form Request per write endpoint)
│   └── Resources/       (PatientResource, AppointmentResource, VisitResource …)
├── Models/              (User, Patient, MedicalHistoryEntry, Appointment, Visit, Payment, WorkingHour, BlockedTime, DoctorSetting)
├── Services/
│   ├── SlotService.php        (generate + validate slots)
│   ├── BookingService.php     (transactional booking)
│   ├── PaymentService.php     (balances, payment rules)
│   └── ReportService.php      (daily/weekly/monthly aggregates)
├── Policies/            (PatientPolicy, AppointmentPolicy)
└── Enums/               (AppointmentStatus, UserRole)
```

### 6.2 Cross-cutting concerns

- **Auth:** Laravel Sanctum personal access tokens; `auth:sanctum` on all routes except login/register.
- **Roles:** `role:doctor` / `role:patient` middleware; Policies ensure a patient touches only their own records.
- **Validation:** every write uses a Form Request (e.g. `BookAppointmentRequest`, `StoreVisitRequest`).
- **Responses:** API Resources with a consistent shape `{ data, message }`; errors return 422 with field messages.
- **Time zone:** `APP_TIMEZONE=Africa/Cairo`; dates sent as ISO 8601.
- **Language:** the frontend sends `Accept-Language: ar|en`; a `SetLocale` middleware sets the app locale so validation and error messages come back in that language (default `ar`, fallback `en`).
- **Clinic config:** `config/clinic.php` holds business constants such as `max_active_appointments` (default 1).
- **Concurrency:** `BookingService` wraps booking in `DB::transaction` and relies on the unique index (section 5.3).

### 6.3 API endpoints

| Method | Endpoint | Role | Purpose | FR |
| --- | --- | --- | --- | --- |
| POST | /auth/register | public | Patient sign-up | A.1 |
| POST | /auth/login | public | Returns token + role | A.2 |
| POST | /auth/logout | any | Revoke token | A.3 |
| GET | /me | any | Current user | A.2 |
| GET | /patient/profile | patient | Info, illness, simple history, balance, next appointment | B.1–B.4 |
| PATCH | /patient/profile | patient | Update phone/address | B.5 |
| GET | /slots?date=YYYY-MM-DD | patient, doctor, assistant | Free slots for a date | E.2 |
| POST | /patient/appointments | patient | Book `{ start_at }` | E.3 |
| GET | /patient/appointments | patient | Own appointments | E.4 |
| PATCH | /patient/appointments/{id}/cancel | patient | Cancel own | E.4 |
| GET | /doctor/patients?search= | doctor | Patient list | C.1 |
| POST | /doctor/patients | doctor | Create a patient account; returns the initial password once | C.6 |
| GET | /doctor/patients/{id} | doctor | Full profile + detailed history + visits | C.2–C.5 |
| PUT | /doctor/patients/{id} | doctor | Update illness and info | C.2 |
| POST / PUT / DELETE | /doctor/patients/{id}/history[/{entryId}] | doctor | Manage history entries | C.4 |
| GET | /doctor/appointments?from=&to= | doctor | Schedule (day/week) | F.1–F.2 |
| POST | /doctor/appointments | doctor | Book on behalf of a patient | — |
| PATCH | /doctor/appointments/{id}/status | doctor | checked_in / completed / no_show / cancelled | F.3–F.4 |
| POST | /doctor/visits | doctor | Create visit `{ patient_id, appointment_id, work_done, total_amount, paid_now }` | D.1–D.4 |
| PUT | /doctor/visits/{id} | doctor | Edit work/total | D.2–D.3 |
| POST | /doctor/visits/{id}/payments | doctor | Add installment | D.6 |
| GET / PUT | /doctor/settings | doctor | Slot duration, booking window, cancellation cut-off | G.2 |
| GET / PUT | /doctor/working-hours | doctor | Weekly hours (7 days, each with zero or more time ranges) | G.1 |
| GET / POST / DELETE | /doctor/blocked-times | doctor | Holidays/exceptions | G.3 |
| GET | /doctor/reports/summary?period=day\|week\|month | doctor | Patient count, revenue, outstanding | H.1–H.2 |
| GET | /doctor/reports/payments?from=&to= | doctor | Per-patient payment table | H.3 |
| GET | /doctor/reports/daily-revenue?month= | doctor | Chart data | H.4 |
| GET / POST | /doctor/staff | doctor | List / create assistants; create returns the initial password once | I.1 |
| PUT | /doctor/staff/{id} | doctor | Edit, deactivate, reset password | I.1 |
| GET / POST | /assistant/patients[?search=] | assistant | Search / create patient (no illness) | I.2 |
| GET / PUT | /assistant/patients/{id} | assistant | Contact info, balance, visits money only / edit contact info | I.3 |
| GET | /assistant/visits/unpaid?date= | assistant | Visits with a remaining balance (default today) | I.4 |
| POST | /assistant/visits/{id}/payments | assistant | Record a payment `{ amount, method, paid_at? }` | I.4–I.5 |
| GET / POST | /assistant/appointments | assistant | Schedule / book on behalf of a patient | I.6 |
| PATCH | /assistant/appointments/{id}/status | assistant | checked_in / no_show / cancelled | I.6 |

### 6.4 Key request example — create visit with payment

```json
POST /api/v1/doctor/visits
{
  "patient_id": 12,
  "appointment_id": 340,
  "work_done": "Root canal, session 1 of 2",
  "total_amount": 1500.00,
  "paid_now": 1000.00
}
// 201 → { "data": { "id": 88, "total_amount": 1500.00, "paid": 1000.00, "remaining": 500.00, "payment_status": "partially_paid" } }
```

The controller creates the visit and its first payment in one transaction, then sets the appointment to Completed.

## 7. Frontend (React) Architecture

The frontend is a single React app with two route areas, `/patient/*` and `/doctor/*`, guarded by role after login.

### 7.1 Folder structure

```text
frontend/src/
├── api/            (axios client with token interceptor + one file per resource: auth.js, slots.js, appointments.js …)
├── auth/           (AuthContext, ProtectedRoute, RoleRoute)
├── layouts/        (PatientLayout, DoctorLayout with sidebar)
├── pages/
│   ├── auth/       (Login, Register)
│   ├── patient/    (PatientHome, BookAppointment, MyAppointments)
│   └── doctor/     (Dashboard, Schedule, PatientsList, PatientDetails, VisitForm, Settings, Reports)
├── components/     (SlotGrid, AppointmentCard, StatusBadge, MoneyField, HistoryList, StatCard, DataTable, Modal)
├── hooks/          (useSlots, useAppointments, useReports … built on TanStack Query)
└── utils/          (formatMoney, formatTime, date helpers with dayjs)
```

### 7.2 Pages and routes

| Route | Page | What it shows / does | Main API calls |
| --- | --- | --- | --- |
| /login, /register | Login, Register | Auth forms; redirect by role | /auth/* |
| /patient | PatientHome | Name, phone, address, illness, simple history, next appointment, balance | GET /patient/profile |
| /patient/book | BookAppointment | Date picker → SlotGrid → confirm modal | GET /slots, POST /patient/appointments |
| /patient/appointments | MyAppointments | Upcoming + past, cancel button | GET/PATCH /patient/appointments |
| /doctor | Dashboard | Today/week/month cards, today's queue | /doctor/reports/summary, /doctor/appointments |
| /doctor/schedule | Schedule | Day/week list, Arrived, Start visit, No-show | /doctor/appointments, PATCH status |
| /doctor/patients | PatientsList | Search table, "New patient" form | GET / POST /doctor/patients |
| /doctor/patients/:id | PatientDetails | Info, detailed history (add/edit), visit timeline with balances | GET /doctor/patients/{id}, history CRUD |
| /doctor/visits/new?appointment=:id | VisitForm | Work done, total, paid now, live remaining | POST /doctor/visits |
| /doctor/settings | Settings | Weekly hours grid (several ranges per day), slot duration, cancellation cut-off, blocked dates, live slot preview | /doctor/settings, /working-hours, /blocked-times |
| /doctor/reports | Reports | Period filter, payments table, daily revenue chart | /doctor/reports/* |
| /assistant | Today | Today's queue (Arrived / No-show / Cancel) and "Waiting to pay" with Record payment | /assistant/appointments, /assistant/visits/* |
| /assistant/patients | PatientsList | Search, "New patient" form without illness | GET / POST /assistant/patients |
| /assistant/patients/:id | PatientPage | Contact info (edit), balance, visits money table, record payment, book appointment | /assistant/patients/{id}, /slots |
| /assistant/schedule | Schedule | Day/week list with Arrived / No-show / Cancel, no Start visit | /assistant/appointments |

### 7.3 Key UI behaviour

- **VisitForm:** `remaining` is displayed read-only and recalculated as the doctor types (`total − paid`); shown in red when > 0. The server's value is final.
- **SlotGrid:** buttons for each free slot; on 409/422 "slot taken" the grid refetches and shows a toast.
- **Settings:** a preview shows the slots that the chosen hours + duration will produce, before saving.
- **Schedule:** status badges by colour (Booked grey, Checked In blue, Completed green, No-show red); auto-refresh every 60 s.
- **Language:** Arabic (RTL) is the default and English is the second language, switchable from both layouts and remembered in localStorage. All text goes through react-i18next; `<html dir>` and `lang` follow the active language; layouts use Tailwind logical classes (`ms-`, `me-`, `ps-`, `pe-`) so they flip correctly.

## 8. Development Phases (in build order)

Build in eight phases of roughly one week each; every phase ends with something working end-to-end, and each phase depends on the one before it.

| Phase | Focus | Depends on | Deliverable |
| --- | --- | --- | --- |
| 0 | Setup | — | Both apps run locally, connected |
| 1 | Database + auth | 0 | Login works for both roles |
| 2 | Profiles + medical history | 1 | Patient page and doctor patient page |
| 3 | Availability + slot engine | 1 | Doctor sets hours; slots generated correctly |
| 4 | Booking + schedule + check-in | 3 | Patient books; doctor sees and checks in |
| 5 | Visits + payments | 2, 4 | Visit form with automatic remaining balance |
| 6 | Dashboard + reports | 5 | Daily/weekly/monthly numbers |
| 7 | Testing, polish, deploy | all | Live v1 |
| 8 | Assistant (front desk) | 5, 6 | Assistant registers patients, records payments, runs the queue — build before the deploy tasks of Phase 7 (T7-05 onwards) |

### Phase 0 — Project setup

- [ ] Create GitHub repo with `/backend` and `/frontend`; branch rules (main + feature branches).
- [ ] `composer create-project laravel/laravel backend`; install Sanctum; configure `.env` (MySQL, `APP_TIMEZONE=Africa/Cairo`).
- [ ] `npm create vite@latest frontend -- --template react`; add React Router, Axios, TanStack Query, Tailwind, dayjs, react-i18next.
- [ ] Configure CORS and the Axios base URL; one test route `/api/v1/health` shown on the React home page.

### Phase 1 — Database and authentication

- [ ] Write all migrations from section 5 (tables, FKs, indexes, generated `active_slot` column).
- [ ] Models with relationships, enums and casts.
- [ ] Seeders: one doctor, default settings (45 min), working hours (17:00–21:00), 10 fake patients via factories.
- [ ] AuthController (register, login, logout, me) + `EnsureRole` middleware.
- [ ] React: AuthContext, Login/Register pages, ProtectedRoute and RoleRoute, two empty layouts, Arabic (default) + English i18n with RTL.

### Phase 2 — Patient profile and medical history (Modules B, C)

- [ ] API: `/patient/profile`, `/doctor/patients` (list + create), `/doctor/patients/{id}`, history CRUD with `patient_visible` filter.
- [ ] Policies so a patient can only read their own profile.
- [ ] React: PatientHome, PatientsList (search), PatientDetails with HistoryList and add/edit modal.
- [ ] Check: a history entry marked private is visible to the doctor and absent on the patient page.

### Phase 3 — Availability settings and slot engine (Module G)

- [ ] API: settings, working-hours, blocked-times endpoints.
- [ ] `SlotService::generate()` exactly as section 4.1.
- [ ] `GET /slots?date=` endpoint.
- [ ] Unit tests for the worked examples (45, 60, 30 min; day with a break; day off; blocked range; today with past slots).
- [ ] React: Settings page with weekly hours grid, duration selector and live slot preview.

### Phase 4 — Booking, schedule and check-in (Modules E, F)

- [ ] `BookingService` with transaction, slot re-validation (BR-1), one-active-appointment rule (BR-4).
- [ ] Patient endpoints: book, list, cancel. Doctor endpoints: list by range, book for patient, change status.
- [ ] React (patient): BookAppointment with date picker + SlotGrid + confirm; MyAppointments.
- [ ] React (doctor): Schedule page with day/week view, Arrived button, No-show and Cancel.
- [ ] Test: two parallel booking requests for the same slot → exactly one succeeds.

### Phase 5 — Visits and payments (Module D)

- [ ] `PaymentService`: remaining calculation, payment status, validation rules PR-1 to PR-3.
- [ ] Endpoints: create/edit visit, add payment; appointment → Completed on visit save.
- [ ] React: VisitForm (work done, total, paid now, live remaining); "Start visit" from a checked-in appointment; visit timeline with balances on PatientDetails; outstanding balance on PatientHome.
- [ ] Test: total 1500, paid 1000 → remaining 500; second payment 500 → Paid; payment of 600 is rejected.

### Phase 6 — Dashboard and reports (Module H)

- [ ] `ReportService` aggregate queries (SUM payments by `paid_at`, COUNT DISTINCT patients with completed visits).
- [ ] Summary, payments table and daily-revenue endpoints.
- [ ] React: Dashboard StatCards (today / week / month), today's queue; Reports page with period filter, table and chart (Recharts).
- [ ] Define week start (Saturday for Egypt) in one config value.

### Phase 7 — Testing, polish and deployment

- [ ] Feature tests for every endpoint's happy path and permission failures (patient calling doctor routes → 403).
- [ ] Empty, loading and error states on every page; mobile layout check for patient pages.
- [ ] Seed realistic demo data; run a full walkthrough (book → arrive → visit → payment → dashboard).
- [ ] Deploy (section 9) and hand over credentials and a short user guide to the doctor.

### Phase 8 — Assistant (Module I)

- [ ] `assistant` role, `users.is_active`, `payments.recorded_by`; doctor manages staff in Settings.
- [ ] `/assistant/*` API with privacy-safe resources (no work done, illness or history), reusing BookingService and PaymentService.
- [ ] Assistant area in the frontend: Today (queue + waiting to pay), Patients, Schedule.
- [ ] **Test:** assistant gets 403 on every doctor route; assistant responses never contain medical fields; payment rules hold; walkthrough register → book → arrive → doctor visit → assistant collects payment.

## 9. Testing, Security and Deployment

The three areas that must be tested hardest are slot generation, double booking and balance calculation, because errors there directly cost the clinic time or money.

### 9.1 Testing

| Level | Tool | What to cover |
| --- | --- | --- |
| Unit | Pest / PHPUnit | SlotService (all cases in 4.1), PaymentService (PR-1 to PR-3), ReportService period boundaries |
| Feature (API) | Pest + RefreshDatabase | Every endpoint: success, validation errors (422), wrong role (403), other patient's data (403/404) |
| Concurrency | Feature test | Two bookings for one slot → one 201, one 409 |
| Frontend | Vitest + React Testing Library | VisitForm remaining calculation, SlotGrid states, route guards |
| Manual / UAT | Checklist with the doctor | Full day scenario on real hours and prices |

### 9.2 Security

- Passwords hashed with bcrypt; login rate-limited (5 attempts/minute).
- All routes behind Sanctum; role middleware + Policies on every patient-specific query.
- Medical data served only over HTTPS; never expose private history entries in patient endpoints (test it).
- Validate and cast all money fields server-side; never trust values calculated in the browser.
- Daily database backup (mysqldump to off-server storage).

### 9.3 Deployment

- **Backend:** VPS (e.g. DigitalOcean / Hostinger) with Nginx + PHP-FPM 8.3 + MySQL 8, or Laravel Forge. Run `php artisan migrate --force`, `config:cache`, `route:cache`.
- **Frontend:** `npm run build`; serve the `dist` folder from Nginx on the same domain (e.g. `clinic.example.com` for React, `/api` proxied to Laravel) to avoid CORS issues.
- **Environments:** local → staging (for the doctor to test) → production.
- **CI (optional):** GitHub Actions running backend and frontend tests on each pull request.

## 10. Assumptions and Open Questions

The plan assumes one doctor in one clinic; the questions below should be confirmed with the client before Phase 1.

**Assumptions made**

- One doctor, one clinic (the schema keeps `doctor_id` everywhere so more doctors can be added later). Assistant accounts belong to that clinic's doctor (Phase 8).
- Currency is EGP; all payments are recorded manually by the doctor or the assistant (no online payment).
- Revenue counts money actually received on the payment date, not the visit total.
- Patients register themselves; the doctor can also add a patient who calls by phone.

**Open questions — answered Oct 5, 2026 (T0-01)**

| Question | Decision | Affects |
| --- | --- | --- |
| Self-register or doctor-created accounts? | **Both.** Patients self-register; the doctor can also create an account (FR-C.6, `POST /doctor/patients`). | FR-A.1, FR-C.6 · T2-03, T2-08 |
| More than one future appointment per patient? | **One for now**, kept in `config('clinic.max_active_appointments')` so it can be raised later. | FR-E.5, BR-4 · T4-01, T4-05 |
| How late can a patient cancel? | **Up to X hours before start**, where X is a doctor setting `cancel_cutoff_hours` (default 2). | BR-5, FR-G.2 · T1-03, T3-01, T3-07, T4-02, T4-05, T4-08 |
| Different hours per weekday? Break inside the shift? | **Yes to both.** Each weekday has zero or more time ranges; no rows = day off. | FR-G.1, §4.1, `working_hours` · T1-03, T1-07, T3-02, T3-04, T3-05, T3-07, T3-09 |
| Receptionist login in v1? | **No**, doctor only. Can be added later. **Changed Oct 5, 2026:** an assistant role is added in Phase 8 — the doctor sets the total, the assistant records payments, registers patients, runs the queue and books; contact info and money only, no medical data; accounts made by the doctor in Settings. | §2, Module I · T8-01 … T8-13 |
| Interface language? | **Arabic and English, Arabic is the default** (RTL). API messages are localized via `Accept-Language`. | §6.2, §7.3 · T1-08, T1-11, T1-14, T7-03 |
| SMS/WhatsApp reminders? | **Not in v1.** | §1 out of scope (unchanged) |
