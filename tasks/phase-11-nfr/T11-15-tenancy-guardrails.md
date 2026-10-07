# T11-15 — SaaS guardrails: ClinicContext and tenancy ADR

**Phase:** 11 · **Area:** Backend · **Size:** M · **Depends on:** —
**Plan refs:** §11.2 NFR-T.1, NFR-T.2 · §10 assumptions

## Steps
- [ ] `App\Support\ClinicContext` (bound as a scoped singleton): `doctor()`, `doctorId()`, `settings()`. Today it returns `User::clinicDoctor()`; later it will come from the logged-in user's clinic.
- [ ] Replace the 5 direct `User::clinicDoctor()` callers (assistant controllers, services) with `ClinicContext`.
- [ ] Pest architecture test: `User::clinicDoctor` is called only from `ClinicContext`; controllers don't use `DB::table()` on clinic tables; no `static` caches of the doctor.
- [ ] ADR `docs/adr/0001-multi-clinic-tenancy.md`: single database with a `clinics` table and `clinic_id` + global scope; the tables that need it (`patients`, `visits`, `payments`, `medical_history_entries`, `drugs`, `audit_logs`, … — `appointments`, `working_hours`, `blocked_times`, `doctor_settings`, `prescriptions` already have `doctor_id`); unique indexes that become per clinic (`users.phone`, `users.email`); how today's rows migrate to clinic 1; cache keys and the file names that need the clinic.
- [ ] New code from here on follows the ADR's checklist (added to the task README "How to use").

## Done when
- [ ] The architecture test passes and fails when a direct caller is added back.
- [ ] The ADR is written and linked from §11.
- [ ] Both test suites still pass.
