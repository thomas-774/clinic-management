# Clinic Management System

Single-clinic web app for one doctor and their patients: profiles and medical history, visits and payments, and online booking with automatic slot generation. Interface in Arabic (default) and English.

- Full plan: [Clinic_Management_System_Development_Plan.md](Clinic_Management_System_Development_Plan.md)
- Task board: [tasks/README.md](tasks/README.md)

## Repository layout

| Folder | What it is |
| --- | --- |
| `backend/` | Laravel 13 REST API (PHP 8.3) (Sanctum, MySQL 8) |
| `frontend/` | React (Vite) app (React Router, TanStack Query, Tailwind, react-i18next) |
| `tasks/` | One file per task, grouped by phase |

## Run locally

Needs PHP 8.3+, Composer, MySQL 8 and Node 20+. Details: [backend/README.md](backend/README.md).

```bash
# terminal 1 — API on http://localhost:8000
cd backend && composer install && cp .env.example .env && php artisan key:generate
php artisan migrate --seed && php artisan serve   # set DOCTOR_* in .env first

# terminal 2 — React app on http://localhost:5173
cd frontend && npm install && cp .env.example .env && npm run dev
```

The home page calls `GET /api/v1/health` and shows the API status and server time.

## Printing prescriptions

A prescription prints on one A5 sheet (or A4, chosen in **Settings → Prescription**, where the clinic name, title, address, phone and footer are also set). **Save & Print** on the prescription form and **Reprint** on the patient page open the print page, which starts printing on its own; **Print again** prints another copy.

### 1. Set up the printer (once)

1. Connect the printer to the clinic PC and install its driver.
2. In Windows **Settings → Bluetooth & devices → Printers & scanners**, turn off "Let Windows manage my default printer", open the clinic printer and choose **Set as default**.
3. Load A5 paper (or A4 if the setting says A4). In the printer's **Printing preferences**, set the same paper size.

### 2. Normal mode: the print dialog

The browser's print dialog opens. The first time only, choose:

- **Printer:** the clinic printer.
- **Paper size:** A5 (or A4), **Margins:** Default, **Scale:** 100 (or Default).
- Under **More settings**, turn off **Headers and footers** (otherwise the page address and date print on the edges).

Chrome and Edge remember these choices, so after that it is just **Print**.

### 3. One-click mode: no dialog

Chrome or Edge started with `--kiosk-printing` prints straight to the Windows default printer, without the dialog.

1. Do one print in normal mode first (step 2), so the browser has saved the paper size and "Headers and footers" off.
2. Right-click the desktop → **New → Shortcut**, and enter (change the address to the clinic's):

   ```text
   "C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk-printing http://localhost:5173/doctor
   ```

   For Edge: `"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe" --kiosk-printing http://localhost:5173/doctor`.
   Name it e.g. "Clinic (one-click print)".
3. **Close every window of that browser before using the shortcut**, or the flag is ignored and the dialog still appears. Also turn off the browser's background running (Chrome: Settings → System → "Continue running background apps"; Edge: Settings → System → "Startup boost" and "Continue running background extensions and apps").

If the dialog still appears, a window of the same browser was still open. If a URL or date prints on the edges, "Headers and footers" is still on. If the page comes out on two sheets, the paper size in the printer and in Settings → Prescription do not match.

## Visit files (PDF / Word)

The doctor can save a visit as a file to print or send: an A4 **PDF**, or an editable **Word** (`.docx`) file. It holds the clinic header, the patient, the work done, the visit's total / paid / remaining with its payments, the patient's overall outstanding balance, the visit's prescriptions and a signature line, never the medical history. It is written in the language the app is shown in (Arabic or English). Only the doctor can make it.

- **When saving a visit:** next to **Save visit**, pick **Also save as: None · PDF · Word**. The choice is remembered on that computer; the button then reads "Save visit + PDF" or "Save visit + Word". If the file cannot be downloaded, the visit is still saved.
- **Any time later:** every visit on the patient page has **PDF** and **Word** buttons. The file is made fresh each time, so after an installment it shows the new paid and remaining amounts.

The file is named `visit-<date>-<visit number>.pdf` (or `.docx`) and goes to the browser's **Downloads** folder. To choose the folder each time, turn on Chrome: Settings → Downloads → "Ask where to save each file before downloading", or Edge: Settings → Downloads → "Ask me what to do with each download". Nothing is stored on the server.

**Server:** the files are made by mPDF and PHPWord, which need the PHP extensions `mbstring`, `gd`, `zip`, `xml` and `dom`. The Arabic font (Cairo) ships with the app in `backend/resources/fonts`. If the browser cannot read the file name in development, check that `Content-Disposition` is in `exposed_headers` in `backend/config/cors.php`.

## Tests

| App | Command | Tooling |
| --- | --- | --- |
| Backend | `cd backend && php artisan test` | Pest on the MySQL database `clinic_testing` (create it and grant your DB user access) |
| Frontend | `cd frontend && npm run test` | Vitest + React Testing Library (jsdom) |

## Branching

- `main` is protected: no direct pushes; every change goes through a pull request.
- Work on one branch per task, named after the task ID:
  - `feature/T1-04-appointments-migration` — new work
  - `fix/T4-01-double-booking-race` — bug fix for a task
  - `chore/T0-06-test-tooling` — tooling, config, docs
- Keep branches short-lived; rebase on `main` before opening the PR.
- Merge with **squash merge** so each task lands on `main` as one commit.
- A PR is ready when the task's "Done when" checks pass and the tests are green.

## Commit style

[Conventional Commits](https://www.conventionalcommits.org/) with the task ID as the scope:

```text
feat(T1-04): add appointments migration with active_slot index
fix(T4-01): return 409 when unique index rejects a booking
test(T3-05): cover slot generation with a mid-shift break
docs(T0-01): record client decisions in the plan
chore(T0-06): configure Pest and Vitest
```

Types: `feat`, `fix`, `test`, `docs`, `refactor`, `chore`. Use the imperative mood ("add", not "added").
