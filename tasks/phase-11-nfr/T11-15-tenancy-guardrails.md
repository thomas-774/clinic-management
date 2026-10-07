# T11-15 — SaaS guardrails: ClinicContext and tenancy ADR

**Phase:** 11 · **Area:** Backend · **Size:** M · **Depends on:** —
**Plan refs:** §11.2 NFR-T.1, NFR-T.2 · §10 assumptions

## Steps
- [x] `App\Support\ClinicContext` (bound as a scoped singleton): `doctor()`, `doctorId()`, `settings()`. Today it returns `User::clinicDoctor()`; later it will come from the logged-in user's clinic.
  - Note: the doctor is looked up once per scope (request / queued job) and memoized in an instance property, not a static one. Laravel only flushes scoped instances in queue workers and Octane, so inside one feature test several requests share it. The full suite passes with that.
- [x] Replace the 5 direct `User::clinicDoctor()` callers (assistant controllers, services) with `ClinicContext`.
  - Note: the callers were `Assistant\ScheduleController` (2 calls, now method-injected: controllers are cached on the route, so constructor injection would outlive the request), `SlotService::forClinic()`, `VisitReportService` (header), `RunBenchmark` and `PerformanceSeeder`. The 11 test files that called it now use a `clinicDoctor()` helper in `tests/Pest.php`. The helper builds a fresh `ClinicContext` each call, so tests never share the instance the requests memoize.
- [x] Pest architecture test: `User::clinicDoctor` is called only from `ClinicContext`; controllers don't use `DB::table()` on clinic tables; no `static` caches of the doctor.
  - Note: `tests/Unit/Architecture/TenancyGuardrailsTest.php` reads PHP tokens. Pest's `arch()` can only forbid a whole class, not one static method, and the token scan skips comments. Scope: `app`, `database`, `routes`, `tests`. Every application table is a clinic table, so controllers may not use `DB::table()` at all. A "static cache" is a static property or static variable whose type or name mentions user/doctor/clinic/setting. Three self-tests prove each rule catches what it should.
- [x] ADR `docs/adr/0001-multi-clinic-tenancy.md`: single database with a `clinics` table and `clinic_id` + global scope; the tables that need it (`patients`, `visits`, `payments`, `medical_history_entries`, `drugs`, `audit_logs`, … — `appointments`, `working_hours`, `blocked_times`, `doctor_settings`, `prescriptions` already have `doctor_id`); unique indexes that become per clinic (`users.phone`, `users.email`); how today's rows migrate to clinic 1; cache keys and the file names that need the clinic.
  - Note: the ADR also gives `clinic_id` to the five `doctor_id` tables, so that one global scope and `clinic_id`-first indexes work the same everywhere. It also lists `users`, `prescription_items` and `drugs.seed_key`, plus the `unique:` / `exists:` validation rules and the query-builder code that skip a global scope.
- [x] New code from here on follows the ADR's checklist (added to the task README "How to use").

## Done when
- [x] The architecture test passes and fails when a direct caller is added back.
  - Note: checked by putting `User::clinicDoctor()` back into `SlotService::forClinic()`. The test failed, naming `app/Services/SlotService.php:29`, and passed again after the revert.
- [x] The ADR is written and linked from §11.
- [x] Both test suites still pass.
  - Note: `composer check`: 792 tests, coverage 98.9 % (app/Services 99.2 %), no advisories. `npm run check`: 313 tests, oxlint 0 warnings, bundle budget OK.
