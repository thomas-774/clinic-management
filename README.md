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
