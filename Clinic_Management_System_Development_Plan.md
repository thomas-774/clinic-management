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
- Prescriptions: the doctor writes a prescription with live search over a drug catalogue taken from the *Drugs for Dentistry* PDF, sees a side note for each drug (uses, who must not take it, ingredients, suggested dose) and prints it on the clinic printer (Module J, added in Phase 9).
- Visit report file: when saving a visit the doctor can also save its details (work done, total, paid, remaining, payments, overall balance) as a PDF or Word file, and download it again later (Module K, added in Phase 10).

**Out of scope for v1** (can be added later): multiple doctors or clinics, SMS/WhatsApp reminders, online payment, file uploads (X-rays, lab results), drug prices, automatic drug–drug interaction checking.

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
| Write, print and reprint prescriptions; manage the drug catalogue | No | Yes | No |
| Download a visit as PDF / Word | No | Yes | No |

**Simple vs detailed history.** Each history entry has a `visibility` flag: `patient_visible` entries appear on the patient page as the simple history; all entries (including private clinical notes) appear for the doctor.

## 3. Functional Requirements by Module

The system is split into eleven modules (A–K); each requirement has an ID (FR-x.y) so it can be traced to tasks and tests.

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

### Module J — Prescriptions (doctor view)

Added in Phase 9. The source is `Drugs-for-Dentistry.pdf` (repo root): 11 sections (mouth cleaning, sensitive-teeth toothpaste, vitamin C, anti-inflammatory, antibiotics, sedatives/analgesics, antifungal, calcium, cod liver oil, local anaesthesia, other), about 100 products. Each row has trade name, form, price, use, suggested dose and composition.

- **FR-J.1** A drug catalogue is seeded from the PDF with, per product: trade name, form (tablets, syrup, gel, spray…), strength/pack, section, active ingredients (each with its short note), uses ("what it is for"), warnings ("who or what it must not be taken with", e.g. not for children, the elderly, pregnancy) and suggested dose. **The price is not imported.** Texts are kept as written in the PDF (Arabic with English drug names) and are not translated.
- **FR-J.2** While the doctor types a medication name, a search box suggests matching drugs from the 2nd character: trade-name prefix first, then trade name or active ingredient containing the text, case-insensitive, up to 15 results, fully usable with the keyboard (↑ ↓ Enter Esc). A name that is not in the catalogue can still be written as free text.
- **FR-J.3** A side note next to the prescription shows the highlighted or selected drug's uses, warnings (in red), active ingredients with notes, form and suggested dose; one click copies the suggested dose into the line's instructions.
- **FR-J.4** The doctor writes a prescription for a patient (optionally linked to a visit): 1–15 lines, each a drug plus instructions (dose, how often, how long), and optional general notes. The drug's name and form are copied onto the line, so later catalogue edits never change an issued prescription. The patient's allergies and conditions from the medical history are shown on the form as a reminder.
- **FR-J.5** **Print** sends the prescription to the clinic printer as one page: clinic header (clinic name, doctor name and title, address, phone), patient name and age, date, the numbered lines (Rx), notes and a signature line — never the side notes, the price or the app's menus. Any past prescription can be reprinted.
- **FR-J.6** The doctor manages the catalogue in Settings → Drugs: search, add a drug, edit any field, and hide a drug (hidden drugs are not suggested but stay on old prescriptions). The doctor also edits the print header and paper size (A5 default, or A4) in Settings → Prescription.
- **FR-J.7** The patient's prescriptions are listed on the doctor's patient page (date, drugs, open, reprint). Patients and the assistant never see the drug catalogue or prescriptions in v1.

### Module K — Visit report file (PDF / Word, doctor view)

Added in Phase 10. When the doctor saves a visit, the system can save the visit's details as a file on the doctor's computer, in the format the doctor chooses.

- **FR-K.1** Next to **Save** on the visit form the doctor picks **Also save as: None · PDF · Word**. The choice is remembered on that computer. After the visit is saved, the file is generated and downloaded straight away; if the download fails, the visit stays saved and the file can be downloaded again later (FR-K.4).
- **FR-K.2** The file holds **all the visit's information**: clinic header (clinic name, doctor name and title, address, phone — the same fields as the prescription header), patient name, phone and age, visit date (with the appointment time, or "walk-in"), **work done today**, the visit's **total cost**, **amount paid** and **remaining**, its payment status, every payment of the visit (date, amount, method, who recorded it), the patient's **overall outstanding balance** across all visits, the prescriptions written for this visit (drug name, form, instructions), the date and time the file was generated and a signature line.
- **FR-K.3** The file never contains the medical history, private clinical notes, drug side notes or warnings, or the app's menus.
- **FR-K.4** Every visit on the doctor's patient page has **PDF** and **Word** buttons, so the doctor can download the file again at any time — for example after an installment, when the paid and remaining amounts have changed.
- **FR-K.5** The file is written in the interface language: Arabic (right-to-left, default) or English. PDF is A4 portrait; Word is an editable `.docx` that opens in Microsoft Word and LibreOffice.
- **FR-K.6** Only the doctor can generate visit files (they contain the work done). Patients and the assistant get 403.

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

### 4.5 Prescription rules (Phase 9)

- **RX-1** A prescription has 1–15 lines; each line has either a catalogue `drug_id` or a free-text `drug_name`, plus non-empty `instructions`.
- **RX-2** A line stores a snapshot of the drug's name and form when it is saved; editing or hiding the drug later never changes issued prescriptions.
- **RX-3** Hidden drugs (`is_active = false`) are never suggested by the search, but stay readable on old prescriptions.
- **RX-4** The printed page contains only what the patient needs (header, patient, date, lines, notes, signature); side notes, warnings, ingredients and prices are never printed.
- **RX-5** If a prescription is linked to a visit, the visit must belong to the same patient.

### 4.6 Visit report rules (Phase 10)

- **VR-1** The file is generated on request from the database at the moment it is downloaded; it is not stored on the server. Amounts are therefore always the current ones (PR-1), and a file downloaded later shows later installments.
- **VR-2** All amounts come from the server (`PaymentService`), formatted as EGP with two decimals, never recalculated in the file or the browser.
- **VR-3** The PDF and the Word file hold exactly the same sections and values (FR-K.2); only the format differs.
- **VR-4** Arabic text is shaped and joined correctly and laid out right-to-left; phone numbers, amounts and drug names stay left-to-right inside it.
- **VR-5** The file name is `visit-<YYYY-MM-DD>-<visit id>.pdf|docx`, plain ASCII so it works on every system (the patient's name is inside the file, not in its name).

## 5. Database Design (MySQL)

Twelve tables cover the whole v1 (nine core tables, plus `drugs`, `prescriptions` and `prescription_items` from Phase 9); every table also has `id` (BIGINT PK) and `created_at` / `updated_at`.

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
| clinic_name / doctor_title / clinic_address / clinic_phone | VARCHAR | nullable; prescription print header (Phase 9, FR-J.6) |
| prescription_footer | VARCHAR(255) | nullable; e.g. working hours, printed at the bottom |
| prescription_paper | ENUM('A5','A4') | default A5 |

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

**drugs** — the prescription drug catalogue, seeded from `Drugs-for-Dentistry.pdf` (Phase 9, FR-J.1); no price column

| Column | Type | Notes |
| --- | --- | --- |
| trade_name | VARCHAR(150) | e.g. "Augmentin 625 mg" |
| form | VARCHAR(60) | tablets, capsules, syrup, gel, spray, mouthwash, ampoule … |
| pack | VARCHAR(60) | nullable; e.g. "14 tabs", "100 ml" |
| category | VARCHAR(40) | PDF section key: `mouth_cleaning`, `sensitive_toothpaste`, `vitamin_c`, `anti_inflammatory`, `antibiotic`, `analgesic_sedative`, `antifungal`, `calcium`, `cod_liver_oil`, `local_anesthetic`, `other` |
| active_ingredients | JSON | list of `{ name, note }`, e.g. `{ "name": "benzocaine", "note": "مخدر موضعي" }` |
| uses | TEXT | what it is used for |
| warnings | TEXT | nullable; who must not take it / what not to take it with |
| suggested_dose | TEXT | nullable |
| seed_key | VARCHAR(80) | nullable, UNIQUE; stable id from the seed file (e.g. `flumox-1g-vial`) so re-seeding updates instead of duplicating; NULL for drugs the doctor adds |
| source_page | SMALLINT | nullable; PDF page, for checking; NULL for drugs the doctor adds |
| is_active | BOOLEAN | default true; false = hidden from search (RX-3) |

**prescriptions**

| Column | Type | Notes |
| --- | --- | --- |
| patient_id | FK → patients.id | |
| doctor_id | FK → users.id | |
| visit_id | FK → visits.id | nullable (RX-5) |
| issued_on | DATE | default today |
| notes | TEXT | nullable; general advice printed under the lines |

**prescription_items**

| Column | Type | Notes |
| --- | --- | --- |
| prescription_id | FK → prescriptions.id | cascade on delete |
| drug_id | FK → drugs.id | nullable (free-text line); null on delete |
| drug_name | VARCHAR(150) | snapshot (RX-2) |
| drug_form | VARCHAR(60) | nullable; snapshot |
| instructions | VARCHAR(255) | e.g. "1 tablet every 12 hours after meals for 5 days" |
| position | TINYINT | print order, 1-based |

### 5.2 Relationships

- users 1—1 patients · patients 1—N medical_history_entries · patients 1—N appointments
- appointments 1—0..1 visits · visits 1—N payments
- doctor (users) 1—1 doctor_settings · 1—N working_hours · 1—N blocked_times · 1—N appointments
- patients 1—N prescriptions · visits 1—N prescriptions · prescriptions 1—N prescription_items · drugs 1—N prescription_items

### 5.3 Indexes and constraints

- UNIQUE `(doctor_id, start_at)` on appointments, only for active statuses — in MySQL, add a generated column `active_slot` = `start_at` when status ∈ (booked, checked_in, completed) else NULL, and put the unique index on `(doctor_id, active_slot)`; cancelled slots can then be rebooked.
- INDEX `(doctor_id, day_of_week)` on working_hours (no unique constraint, because a day can have several ranges).
- INDEX `(doctor_id, start_at)` for the schedule; INDEX `(paid_at)` on payments for reports; INDEX `(patient_id)` on every child table.
- Remaining balance is **not stored**; it is computed (`total_amount − SUM(payments)`) in a query or Eloquent accessor to avoid stale data.
- INDEX `(is_active, trade_name)` on drugs for the prefix search; UNIQUE `seed_key` so the seeder can upsert (the PDF repeats some trade names with different forms); INDEX `(patient_id, issued_on)` on prescriptions.

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
│   │   ├── Doctor/ReportController.php
│   │   ├── Doctor/DrugController.php           (Phase 9)
│   │   ├── Doctor/PrescriptionController.php   (Phase 9)
│   │   └── Doctor/VisitExportController.php    (Phase 10)
│   ├── Middleware/EnsureRole.php
│   ├── Requests/        (one Form Request per write endpoint)
│   └── Resources/       (PatientResource, AppointmentResource, VisitResource …)
├── Models/              (User, Patient, MedicalHistoryEntry, Appointment, Visit, Payment, WorkingHour, BlockedTime, DoctorSetting, Drug, Prescription, PrescriptionItem)
├── Services/
│   ├── SlotService.php        (generate + validate slots)
│   ├── BookingService.php     (transactional booking)
│   ├── PaymentService.php     (balances, payment rules)
│   ├── ReportService.php      (daily/weekly/monthly aggregates)
│   └── VisitReport/           (Phase 10: VisitReportService builds the data; PdfVisitReport (mPDF) and WordVisitReport (PHPWord) render it)
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
| GET | /doctor/drugs/search?q= | doctor | Typeahead: up to 15 active drugs (id, trade_name, form, pack, short use) | J.2 |
| GET | /doctor/drugs[?search=&category=&include_hidden=] | doctor | Catalogue list for Settings → Drugs (paginated) | J.6 |
| GET | /doctor/drugs/{id} | doctor | Full drug for the side note | J.3 |
| POST / PUT | /doctor/drugs[/{id}] | doctor | Add / edit a drug, hide with `is_active: false` | J.6 |
| GET / POST | /doctor/patients/{id}/prescriptions | doctor | Patient's prescriptions / write one `{ visit_id?, issued_on?, notes?, items: [{ drug_id?, drug_name?, instructions }] }` | J.4, J.7 |
| GET / PUT / DELETE | /doctor/prescriptions/{id} | doctor | Open (with patient name, age and print header) / edit / delete | J.4, J.5 |
| GET | /doctor/visits/{id}/export?format=pdf\|docx | doctor | The visit report as a file download (language from `Accept-Language`) | K.1–K.6 |

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
| /doctor/visits/new?appointment=:id | VisitForm | Work done, total, paid now, live remaining; "Also save as: None · PDF · Word" next to Save (Phase 10) | POST /doctor/visits, GET /doctor/visits/{id}/export |
| /doctor/settings | Settings | Weekly hours grid (several ranges per day), slot duration, cancellation cut-off, blocked dates, live slot preview | /doctor/settings, /working-hours, /blocked-times |
| /doctor/reports | Reports | Period filter, payments table, daily revenue chart | /doctor/reports/* |
| /assistant | Today | Today's queue (Arrived / No-show / Cancel) and "Waiting to pay" with Record payment | /assistant/appointments, /assistant/visits/* |
| /assistant/patients | PatientsList | Search, "New patient" form without illness | GET / POST /assistant/patients |
| /assistant/patients/:id | PatientPage | Contact info (edit), balance, visits money table, record payment, book appointment | /assistant/patients/{id}, /slots |
| /assistant/schedule | Schedule | Day/week list with Arrived / No-show / Cancel, no Start visit | /assistant/appointments |
| /doctor/patients/:id/prescriptions/new[?visit=:id] | PrescriptionForm | Lines with DrugSearch, instructions, notes, DrugInfoPanel side note, allergy reminder, Save & Print | /doctor/drugs/search, /doctor/drugs/{id}, POST prescriptions |
| /doctor/prescriptions/:id/edit | PrescriptionForm | Same form, editing a saved prescription | GET / PUT /doctor/prescriptions/{id} |
| /doctor/prescriptions/:id/print | PrescriptionPrint | Print-only page; opens the print dialog on load | GET /doctor/prescriptions/{id} |
| /doctor/settings (Drugs, Prescription tabs) | Settings | Catalogue search / add / edit / hide; print header and paper size with preview | /doctor/drugs, /doctor/settings |

### 7.3 Key UI behaviour

- **VisitForm:** `remaining` is displayed read-only and recalculated as the doctor types (`total − paid`); shown in red when > 0. The server's value is final.
- **SlotGrid:** buttons for each free slot; on 409/422 "slot taken" the grid refetches and shows a toast.
- **Settings:** a preview shows the slots that the chosen hours + duration will produce, before saving.
- **Schedule:** status badges by colour (Booked grey, Checked In blue, Completed green, No-show red); auto-refresh every 60 s.
- **Language:** Arabic (RTL) is the default and English is the second language, switchable from both layouts and remembered in localStorage. All text goes through react-i18next; `<html dir>` and `lang` follow the active language; layouts use Tailwind logical classes (`ms-`, `me-`, `ps-`, `pe-`) so they flip correctly.
- **DrugSearch (Phase 9):** a combobox that queries `/doctor/drugs/search` 250 ms after the last keystroke (from 2 characters), highlights the matching part, moves with ↑ ↓, picks with Enter, closes with Esc, and offers "use '…' as written" when nothing matches. The highlighted result already fills the side note, so the doctor can read it before choosing.
- **Printing (Phase 9):** the print page uses a `@media print` stylesheet with `@page { size: A5 }` (or A4 from settings), hides all app chrome and prints in the page's language direction (Arabic RTL; drug names and instructions stay LTR inside). Print calls `window.print()`, which sends the page to the printer chosen in the browser. For true one-click printing on the clinic PC, the browser is started with `--kiosk-printing`, which prints straight to the Windows default printer with no dialog (documented in T9-14).
- **Visit files (Phase 10):** the file is fetched with the Axios client (so the token is sent) as a `blob`, then saved through a temporary object URL and `<a download>`; the file name comes from the response's `Content-Disposition`. The browser saves it to the doctor's Downloads folder (or asks where, if the browser is set to ask). The format choice on the visit form is kept in localStorage (`clinic.visitFile`).

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
| 9 | Prescriptions | 2, 5, 8 | Doctor writes a prescription with drug search and side notes and prints it — build before Phase 7 |
| 10 | Visit report file (PDF / Word) | 5, 9 | Saving a visit can also save its details as a PDF or Word file; any visit can be downloaded again — build before Phase 7 |
| 11 | Non-functional hardening | 8, 9, 10 | Security, privacy, performance, quality and accessibility targets of §11 met and measured — build before Phase 7 |

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

- [x] `assistant` role, `users.is_active`, `payments.recorded_by`; doctor manages staff in Settings.
- [x] `/assistant/*` API with privacy-safe resources (no work done, illness or history), reusing BookingService and PaymentService.
- [x] Assistant area in the frontend: Today (queue + waiting to pay), Patients, Schedule.
- [x] **Test:** assistant gets 403 on every doctor route; assistant responses never contain medical fields; payment rules hold; walkthrough register → book → arrive → doctor visit → assistant collects payment.

### Phase 9 — Prescriptions (Module J)

- [x] `drugs` table and the catalogue transcribed from `Drugs-for-Dentistry.pdf` into a seed file (no prices), checked against the PDF.
- [x] Drug search / catalogue API and the prescriptions API (snapshotted lines, RX-1 – RX-5); print header fields in doctor settings.
- [x] Frontend: DrugSearch, DrugInfoPanel side note, PrescriptionForm, print page (A5/A4), prescriptions on PatientDetails, Settings → Drugs and Prescription.
- [ ] **Test:** search ranking and hidden drugs; patient and assistant get 403 on every drug and prescription route; editing a drug never changes an issued prescription; the printed page holds only header, patient, lines, notes and signature; walkthrough visit → prescription → print on the clinic printer.

### Phase 10 — Visit report file (Module K)

- [x] Install mPDF (PDF with proper Arabic shaping and RTL) and PHPWord (`.docx`); bundle the app's Cairo font for the PDF.
- [x] `VisitReportService` builds one data object per visit (header, patient, visit, work done, amounts, payments, overall balance, linked prescriptions); `PdfVisitReport` and `WordVisitReport` render it; `GET /doctor/visits/{id}/export?format=pdf|docx`.
- [x] Frontend: "Also save as: None · PDF · Word" next to Save on the visit form; PDF / Word buttons on every visit in the patient's timeline.
- [x] **Test:** both formats hold the same values as the API (after an installment too); patient and assistant get 403; unknown format → 422; the file never holds medical history; Arabic is joined and RTL; walkthrough save visit → file opens in Word and a PDF reader, in ar and en.

### Phase 11 — Non-functional hardening (§11)

- [ ] Security and privacy: security headers and `no-store` on API responses, rate limits on every write and search route, composer/npm audit clean, audit log of who read or changed medical and money records (doctor sees it in Settings), medical text fields encrypted at rest, OWASP ASVS Level 1 review.
- [ ] Performance: missing indexes, no N+1 queries (lazy loading blocked outside production), a 5-year demo dataset with measured p95 API times, and the frontend split into lazy-loaded chunks per role.
- [ ] Quality: coverage thresholds and zero-warning lint gates, run by one `check` command per app and a pre-commit hook.
- [ ] Usability: WCAG 2.1 AA in Arabic and English; patient pages built mobile-first for 360 px with Lighthouse ≥ 90.
- [ ] SaaS readiness: one `ClinicContext` resolves the clinic's doctor; an architecture test keeps it that way; an ADR describes the future multi-clinic model.
- [ ] **Test:** every NFR in §11.2 has a recorded measurement that meets its target (T11-16).

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

**Prescriptions (Phase 9) — defaults used until the client says otherwise (raised Oct 5, 2026)**

| Question | Default in the plan | Affects |
| --- | --- | --- |
| Can patients see their prescriptions on their page? Can the assistant reprint them? | **No to both** in v1; doctor only. Easy to add later (read-only list). | FR-J.7 · T9-05, T9-07 |
| Paper size of the clinic's prescription pad? | **A5**, switchable to A4 in Settings → Prescription. | FR-J.6 · T9-06, T9-11 |
| Print with the dialog or straight to the printer? | Print button opens the browser print dialog; the clinic PC can be set up for **one-click silent printing** with `--kiosk-printing`. | §7.3 · T9-11, T9-14 |
| Translate the PDF's Arabic drug notes into English? | **No**; shown as written in both interface languages. | FR-J.1 · T9-02 |
| The PDF is from 2014 — are any products discontinued? | Everything is imported; the doctor hides what is no longer sold (FR-J.6). | T9-02, T9-13 |

**Visit report file (Phase 10) — defaults used until the client says otherwise (raised Oct 7, 2026)**

| Question | Default in the plan | Affects |
| --- | --- | --- |
| Should the server also keep a copy of every file? | **No.** The file is generated from the database when downloaded (VR-1) and saved on the doctor's computer; the database stays the record. Storing copies can be added later. | VR-1 · T10-05 |
| Should the assistant or the patient get the file (e.g. a receipt)? | **No**, doctor only — the file contains the work done (FR-K.6). A money-only receipt for the assistant can be added later. | FR-K.6 · T10-06 |
| Default format on a new computer? | **None** (just save), until the doctor picks PDF or Word once. | FR-K.1 · T10-08 |
| Paper size? | **A4 portrait** for the PDF (the prescription keeps its own A5/A4 setting). | FR-K.5 · T10-03 |
| Show the prescriptions of the visit in the file? | **Yes**, drug name, form and instructions only (no side notes, RX-4). | FR-K.2 · T10-02 |

## 11. Non-functional requirements (Phase 11)

Added Oct 7, 2026. Phases 0–10 cover what the system does; this section sets how well it must do it: how safe the medical data is, how fast it responds, and how the code and the screens are held to a bar. Each requirement has an ID (NFR-x.y), a number that can be measured, and the task that delivers it. Phase 11 is built **before Phase 7**, so v1 is deployed already hardened and Phase 7 tests what ships.

### 11.1 Decisions (Oct 7, 2026)

| Question | Decision | Affects |
| --- | --- | --- |
| One clinic or SaaS? | **One clinic now, SaaS later.** No `clinic_id` column yet, but nothing built now may block adding one (guardrails only). | NFR-T · T11-15 |
| What load do the targets assume? | **Small clinic:** up to 50 visits a day, about 10,000 patients and 5 years of visits, at most 5 people using it at once. | NFR-P · T11-09 |
| Which areas? | Security and privacy, performance, quality, usability. Reliability and operations (backups, monitoring, deploys, CI) stay in Phase 7 (T7-05 … T7-08). | Phases 7, 11 |
| Security scope? | Audit log, encryption of medical text at rest, security headers + rate limits + dependency audit + ASVS L1 review. | NFR-S |
| Usability and quality scope? | WCAG 2.1 AA, mobile-first patient pages, coverage and lint gates. | NFR-U, NFR-Q |

### 11.2 Requirements and targets

The baseline measured on Oct 7, 2026 is shown where one exists, so the gain can be checked.

**Security and privacy (NFR-S)**

| ID | Requirement | Target / check | Baseline | Task |
| --- | --- | --- | --- | --- |
| NFR-S.1 | Security headers on every response | API: `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer`, `X-Frame-Options: DENY`, `Cache-Control: no-store` on authenticated responses; HSTS outside local. SPA (Nginx): a CSP without `unsafe-eval`. A feature test checks the API headers. | none | T11-01 |
| NFR-S.2 | Rate limits beyond login | Every write route ≤ 60/min per user, search routes ≤ 120/min, register ≤ 5/min per IP; a 429 comes back as `{ message }` in the request language. | login only | T11-02 |
| NFR-S.3 | No known vulnerable dependencies | `composer audit` and `npm audit --omit=dev --audit-level=high` report nothing; both are part of `check` (T11-11) and of CI (T7-08). | not run | T11-03 |
| NFR-S.4 | Audit log of medical and money records | Every read of a patient's doctor page, history, visit, prescription or visit file, and every create / update / delete of those and of payments, writes one row: who, role, action, record, patient, time, IP, and the **names** of changed fields (never their values). Rows cannot be edited or deleted through the app. | none | T11-04 |
| NFR-S.5 | Doctor can review the audit log | Settings → Activity: filter by patient, user, action and date; paginated; rows kept 5 years, then pruned by a scheduled command. | none | T11-05 |
| NFR-S.6 | Medical text encrypted at rest | `patients.current_illness`, `medical_history_entries.title` and `details`, `visits.work_done`, `prescriptions.notes`, `prescription_items.instructions` use Laravel's encrypted cast (AES-256 with `APP_KEY`). A raw `SELECT` shows only ciphertext. Existing rows are encrypted by a reversible migration. `APP_KEY` is kept in the password manager and the backup runbook (T7-07); key rotation via `APP_PREVIOUS_KEYS`. | plain text | T11-06 |
| NFR-S.7 | OWASP ASVS Level 1 | Every applicable L1 item checked and the result recorded in `docs/security/asvs-l1.md`; every fail fixed or accepted in writing. Includes: no stack traces with `APP_DEBUG=false`, no mass assignment, an IDOR sweep over every `{id}` route. | not done | T11-07 |

**Performance (NFR-P)**: measured on the 5-year demo dataset (T11-09), server-side time, warm cache.

| ID | Requirement | Target / check | Baseline | Task |
| --- | --- | --- | --- | --- |
| NFR-P.1 | API response time | p95 < 300 ms and p99 < 800 ms for every list, detail, slot and report endpoint; visit file export p95 < 1.5 s. | not measured | T11-09 |
| NFR-P.2 | No N+1 queries | `Model::preventLazyLoading()` outside production; each list endpoint runs a fixed number of queries whatever the page size (query-count test). | not enforced | T11-08 |
| NFR-P.3 | Indexes for every hot filter | `visits.visit_date` (reports filter on it since the revenue-by-visit-date change), plus any index `EXPLAIN` shows missing on the measured endpoints; no full table scan on them. | `visit_date` unindexed | T11-08 |
| NFR-P.4 | Frontend first load | Patient pages: initial JS ≤ 200 kB gzip; no chunk > 500 kB minified; Recharts loaded only on Dashboard / Reports. A build-size check fails `npm run check` when the budget is broken. | one 959 kB chunk (283 kB gzip) | T11-10 |
| NFR-P.5 | Perceived speed | Lighthouse mobile (Slow 4G, mid-range phone) on the patient pages: LCP < 2.5 s, CLS < 0.1, Performance ≥ 90. | not measured | T11-14 |

**Quality (NFR-Q)**

| ID | Requirement | Target / check | Baseline | Task |
| --- | --- | --- | --- | --- |
| NFR-Q.1 | Backend coverage | Line coverage ≥ 80 % overall and ≥ 95 % on `app/Services` (`pest --coverage --min=80`, PCOV driver). | no coverage driver | T11-11 |
| NFR-Q.2 | Frontend coverage | Vitest (V8) thresholds: lines ≥ 70 %, branches ≥ 60 %; `src/utils` and `src/hooks` ≥ 85 %. | not measured | T11-11 |
| NFR-Q.3 | Lint and style | `pint --test` clean on the whole backend; oxlint with **zero** warnings, jsx-a11y plugin on. | Pint fails on `HealthTest.php` | T11-11, T11-12 |
| NFR-Q.4 | One gate | `composer check` and `npm run check` run lint, tests, coverage, audit and the size budget; a versioned pre-commit hook runs the fast part (lint + related tests). | none | T11-11 |

**Usability and accessibility (NFR-U)**

| ID | Requirement | Target / check | Baseline | Task |
| --- | --- | --- | --- | --- |
| NFR-U.1 | WCAG 2.1 AA | Every page passes axe-core with no serious or critical issues in Arabic (RTL) and English; full keyboard use (visible focus, no traps, skip link); text contrast ≥ 4.5:1; form errors tied to their fields; toasts announced (`aria-live`). Manual pass with NVDA on the main flows. | not checked | T11-12, T11-13 |
| NFR-U.2 | Mobile-first patient pages | Login, Register, Home, Book and My appointments designed at 360 px first: no horizontal scroll, tap targets ≥ 44 × 44 px, inputs ≥ 16 px (no zoom on iOS), the right mobile keyboards (`tel`, `numeric`). Lighthouse mobile Accessibility and Best Practices ≥ 90. | partly (T7-03 check) | T11-14 |

**SaaS readiness (NFR-T)**

| ID | Requirement | Target / check | Baseline | Task |
| --- | --- | --- | --- | --- |
| NFR-T.1 | One place resolves "the clinic" | A `ClinicContext` service is the only code that finds the clinic's doctor; `User::clinicDoctor()` (5 callers today) is called only there. A Pest architecture test fails on any new caller. | 5 callers | T11-15 |
| NFR-T.2 | Future tenancy model written down | ADR `docs/adr/0001-multi-clinic-tenancy.md`: single database, `clinic_id` on the tables it lists, a global scope, and how today's data migrates. | none | T11-15 |

### 11.3 Not in Phase 11 (deferred on purpose)

- **Session and token hardening** (token expiry, idle logout on the shared clinic PC, revoking tokens on password change). Not chosen for now. Known risk: Sanctum tokens never expire today (`sanctum.expiration = null`), so a token left in a shared browser stays valid until logout.
- **Slow or flaky internet handling** (offline banner, retries, drafts on every form) beyond what T7-03 covers.
- **Reliability and operations**: monitoring, uptime, backups, zero-downtime deploys and CI stay in Phase 7.
- **Search inside encrypted fields**: after NFR-S.6, `current_illness`, history and `work_done` cannot be searched or sorted in SQL. Nothing does that today; a future search over them needs its own design.
- **ASVS Level 1 items accepted in the T11-07 review** (`docs/security/asvs-l1.md`, Oct 7, 2026); each one fails today on purpose:
  - **A-1 Password rules** (ASVS 2.1.1, 2.1.7, 2.1.8): minimum 8 characters, not 12; no breached-password check (it would call the Have I Been Pwned API on every sign-up) and no strength meter. 8 is the NIST SP 800-63B floor and login is rate-limited (5/min).
  - **A-2 bcrypt's 72-byte limit** (2.1.3): §9.2 chose bcrypt, which ignores everything after 72 bytes (about 36 Arabic letters). Switching to Argon2id would remove it.
  - **A-3 Account self-service** (2.1.5, 2.2.3, 2.3.1, 2.5.5, 3.7.1): users cannot change their own password, an initial password is not forced to change at first login, nobody is notified when a password or phone changes (v1 sends no SMS or email), and a patient changes their phone (their login name) without typing the password. Goes with "Session and token hardening" above.
  - **A-4 Token in `localStorage`** (3.2.3): an XSS bug could read the token. The CSP (`script-src 'self'`, `connect-src 'self'`, T11-01) is the mitigation for now; the fix is Sanctum's `HttpOnly` cookie authentication, with "Session and token hardening".
  - **A-5 Not in v1** (4.3.1, 8.3.2, 8.3.3): no second factor for the doctor; patients cannot export or delete their own data (medical records must be kept; the retention rule is the client's call); no privacy notice on Register yet (to be written with the client before launch).
