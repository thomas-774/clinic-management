# ADR 0001 — Multi-clinic tenancy

- **Status:** Accepted as the target design; **not built**. v1 stays one clinic (plan §11.1).
- **Date:** 2026-10-07
- **Requirement:** NFR-T.2 (plan §11.2) · task T11-15
- **Related:** NFR-T.1 (`App\Support\ClinicContext`), plan §10 assumptions

## Context

v1 serves one doctor in one clinic (§10). The client wants a SaaS later: many clinics on one deployment, each seeing only its own patients, money and medical records. Decision §11.1: no `clinic_id` column yet, but nothing built now may block adding one.

Today "the clinic" is found in one place: `ClinicContext::doctor()` returns `User::clinicDoctor()`, the first user with the doctor role. Tables that hang off the doctor (`appointments`, `working_hours`, `blocked_times`, `doctor_settings`, `prescriptions`) already carry `doctor_id`. All others assume one clinic.

This ADR fixes the target design now, so new code can follow it (see the checklist at the end), and so the later migration is mechanical.

## Decision

**One database, shared tables, a `clinic_id` column on every clinic-owned table, and an Eloquent global scope that filters on it.**

### Options considered

| Option | Isolation | Cost for a small-clinic SaaS | Verdict |
| --- | --- | --- | --- |
| Database per clinic | Strongest | One migration run, backup and connection per clinic; reports across clinics impossible; ops cost grows with every clinic | Rejected: too heavy for clinics of ~10,000 patients (§11.1 load) |
| Schema per clinic | Strong | Same ops cost as above on MySQL (a schema *is* a database) | Rejected |
| **Shared tables + `clinic_id` + global scope** | Enforced in the app: the scope, plus the guardrail tests | One schema, one migration run, one backup; indexes lead with `clinic_id` | **Chosen** |

The risk of the chosen option is a query that skips the scope and reads another clinic's rows. The guardrails below make that hard. The NFR-T.1 architecture test (`tests/Unit/Architecture/TenancyGuardrailsTest.php`) already enforces part of them today.

## Design

### 1. `clinics` table and the context

```
clinics: id, name, slug (unique, used in the subdomain), is_active, timestamps
```

The clinic's print header (`clinic_name`, `clinic_address`, `clinic_phone` in `doctor_settings`) stays where it is: it belongs to the doctor's prescription pad (FR-J.5, FR-J.6). `clinics.name` is the account name.

`ClinicContext` (already the only resolver, NFR-T.1) gains `clinic()` / `clinicId()`:

- **Logged-in requests:** the clinic of the authenticated user (`users.clinic_id`).
- **Requests before login** (`/auth/login`, `/auth/register`): the clinic from the subdomain (`{slug}.example.com`). An unknown or inactive slug returns 404.
- **A user whose `clinic_id` differs from the subdomain's clinic** gets a 401, and the token is not accepted.
- **Queued jobs** carry the `clinic_id` they were dispatched with and set the context in a job middleware.
- **Console commands** (`audit:prune`, the benchmark, seeders) loop over the clinics and set the context for each one, or opt out explicitly with `withoutGlobalScope(ClinicScope::class)` and a comment saying why.

`doctor()` then returns the doctor *of that clinic*. With more than one doctor per clinic, it becomes the doctor chosen for the booking (§10), and `doctor_id` stays on the tables that have it.

### 2. Tables

A `BelongsToClinic` trait on each model adds `ClinicScope` (`where {table}.clinic_id = ClinicContext::clinicId()`) and fills `clinic_id` on `creating`. Route-model binding goes through Eloquent, so an id from another clinic is a 404, the same as today's IDOR rule (T11-07).

Every clinic-owned table gets `clinic_id`, including the ones already scoped by `doctor_id`, so one scope works the same way everywhere and every hot index can lead with `clinic_id`.

| Table | Scoped today by | Change |
| --- | --- | --- |
| `users` | — (one clinic) | `clinic_id` (doctor, assistants and patients all belong to one clinic) |
| `patients` | — | `clinic_id` |
| `medical_history_entries` | `patient_id` | `clinic_id` |
| `visits` | `patient_id` | `clinic_id`; reports filter on `(clinic_id, visit_date)` |
| `payments` | `visit_id` | `clinic_id`; revenue filters on it |
| `prescription_items` | `prescription_id` | `clinic_id` |
| `drugs` | — (one catalog) | `clinic_id`: each clinic hides, edits and adds its own drugs (FR-J.6). The seeded catalog is copied into each new clinic |
| `audit_logs` | — | `clinic_id`; the Activity page (NFR-S.5) shows only its own clinic |
| `appointments` | `doctor_id` | `clinic_id` too |
| `working_hours` | `doctor_id` | `clinic_id` too |
| `blocked_times` | `doctor_id` | `clinic_id` too |
| `doctor_settings` | `doctor_id` | `clinic_id` too |
| `prescriptions` | `doctor_id` | `clinic_id` too |

**Not clinic tables:** `personal_access_tokens` (scoped through its user), `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`. `password_reset_tokens` is keyed by email, so it becomes `(clinic_id, email)` if password reset is ever added (it is not in v1).

### 3. Unique indexes that become per clinic

| Today | After |
| --- | --- |
| `users.phone` unique | `(clinic_id, phone)` unique: one person may be a patient of two clinics, with two separate accounts |
| `users.email` unique | `(clinic_id, email)` unique |
| `drugs.seed_key` unique | `(clinic_id, seed_key)` unique |
| `appointments (doctor_id, active_slot)` | unchanged: a doctor belongs to one clinic |
| `patients.user_id`, `visits.appointment_id`, `doctor_settings.doctor_id` | unchanged: 1-to-1 links inside one clinic |
| `personal_access_tokens.token`, `failed_jobs.uuid` | unchanged: global by nature |

Validation rules talk to the database directly and **skip the global scope**. Each one needs a `->where('clinic_id', …)`:

- `unique:users,email` in `Auth/RegisterRequest`, `Doctor/StorePatientRequest`, `Doctor/StoreStaffRequest`; `Rule::unique('users', 'email')` in `Doctor/UpdateStaffRequest`; `Rule::unique('users', 'phone')` in `Concerns/ValidatesPhone`.
- `exists:patients,id` in `Doctor/BookForPatientRequest` and `Doctor/StoreVisitRequest`; `exists:visits,id` and `exists:drugs,id` in `Doctor/StorePrescriptionRequest`; `exists:appointments,id` in `Doctor/StoreVisitRequest`. Without the filter, another clinic's id passes validation; the scoped `findOrFail` that follows still stops it, but the error would be a 404 instead of a 422.

Login (`login` = phone or email) looks the user up inside the subdomain's clinic.

### 4. Query-builder code that skips the scope

The scope only covers Eloquent. Today these places use the query builder or raw SQL on clinic tables, and each one must add the clinic filter by hand:

- `ReportService`: `DB::table('patients')` (owed balances), plus the `selectRaw` / `whereRaw` revenue queries joined to `payments`.
- `Visit::scopeUnpaid()`: the `whereRaw` subquery on `payments`.
- `PruneAuditLogs`: `DB::table('audit_logs')`. Pruning by date across all clinics is fine; keep it global on purpose and say so.
- `MedicalEncryption` (the T11-06 migration helper): rewrites every row of every clinic, on purpose.
- `RunBenchmark` / `PerformanceSeeder`: dev tools that run against one clinic.

Controllers must not use `DB::table()` at all (architecture test).

### 5. Moving today's data to clinic 1

One migration, run in maintenance mode (MySQL commits each `ALTER` on its own, so it lives in `tests/Migrations` with `DatabaseMigrations`, like T11-06/T11-08):

1. Create `clinics`; insert clinic **1** with `name` = `doctor_settings.clinic_name` of the current doctor (or `config('app.name')`) and a slug chosen at deploy time.
2. Add `clinic_id` **nullable** to every table in §2, with an index.
3. Backfill: `UPDATE {table} SET clinic_id = 1` (one statement per table; ~10,000 patients and 5 years of visits fit easily).
4. Make `clinic_id` `NOT NULL` with a foreign key to `clinics` (`cascadeOnDelete` is **not** used: deleting a clinic must be a deliberate export-and-purge, not a cascade).
5. Swap the unique indexes in §3 (add the composite one first, then drop the old one).
6. Re-create the hot indexes from T11-08 with `clinic_id` in front (e.g. `visits (clinic_id, visit_date)`), dropping the old ones only after the new ones exist. MySQL silently drops an FK's own index when a wider one can serve it, so `down()` re-adds it first (T11-08 lesson).
7. `down()` reverses each step; the migration test checks up → down → up on seeded data.

Encrypted columns (NFR-S.6) are not touched: all clinics share `APP_KEY`. Per-clinic keys are out of scope.

### 6. Cache keys, rate limits and file names

| What | Today | With clinics |
| --- | --- | --- |
| Rate limiter `login` | `lower(login)\|ip` | `clinic_id\|lower(login)\|ip`: the same phone exists once per clinic |
| Rate limiter `register` | `ip` | unchanged (the limit protects the server, not the clinic) |
| Rate limiters `writes`, `search` | user id | unchanged: user ids are global |
| Application cache (`Cache::`) | not used | every key starts with `clinic:{id}:` |
| Visit file name | `visit-{date}-{visit id}.{pdf\|docx}` | unchanged: visit ids are global and the file is not stored on the server (VR-1). If copies are ever stored, the path starts with `clinics/{id}/` |
| Uploaded / stored files | none | `clinics/{id}/…` on the disk |
| Browser storage (`clinic.token`, `clinic.lang`, `clinic.visitFile.*`) | per origin | unchanged with one subdomain per clinic (each clinic is its own origin); if clinics ever share a host, prefix the keys with the slug |
| `config('clinic.doctor')` / `DoctorSeeder` | the one doctor from `.env` | becomes "create clinic + first doctor" onboarding; the seeder makes clinic 1 |

## Consequences

- Code written from now on follows the checklist below, so the migration in §5 is mechanical and does not need a hunt through the app.
- `ClinicContext` is the seam. When clinics arrive, only it changes how "the clinic" is found; callers stay as they are.
- Until then the global scope does not exist. The tests that guard isolation today are the role, policy and IDOR tests (T2-06, T8, T11-07). When clinics are built, an isolation test (two clinics, every route, no cross-clinic row) becomes part of `composer check`.

## Checklist for new code (from T11-15 on)

1. Find the clinic's doctor or settings through `ClinicContext` (`doctor()`, `doctorId()`, `settings()`), never with `User::clinicDoctor()` or a "first doctor" query. *Enforced by the architecture test.*
2. Read and write clinic data through Eloquent models, never `DB::table()` in a controller. *Enforced.* A service that needs the query builder or raw SQL names itself in §4 of this ADR.
3. No `static` property or variable caches the doctor, the clinic or its settings. *Enforced.* Cache per request through `ClinicContext` (a scoped singleton), and put the clinic id in any `Cache::` key.
4. A new table that holds clinic data has a path to the clinic (`doctor_id`, or a foreign key to a clinic-owned parent) and is added to the table list in §2.
5. A new `unique` index or `unique:` / `exists:` validation rule on clinic data is listed in §3. It becomes per clinic.
6. A new stored file, cache key or rate-limiter key says how it will include the clinic (§6).
