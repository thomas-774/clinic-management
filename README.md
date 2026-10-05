# Clinic Management System

Single-clinic web app for one doctor and their patients: profiles and medical history, visits and payments, and online booking with automatic slot generation. Interface in Arabic (default) and English.

- Full plan: [Clinic_Management_System_Development_Plan.md](Clinic_Management_System_Development_Plan.md)
- Task board: [tasks/README.md](tasks/README.md)

## Repository layout

| Folder | What it is |
| --- | --- |
| `backend/` | Laravel 11 REST API (Sanctum, MySQL 8) |
| `frontend/` | React (Vite) app (React Router, TanStack Query, Tailwind, react-i18next) |
| `tasks/` | One file per task, grouped by phase |

Setup instructions for each app are added in their own folders (T0-03, T0-04).

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
