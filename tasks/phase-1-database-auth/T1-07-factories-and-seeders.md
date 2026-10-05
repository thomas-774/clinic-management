# T1-07 — Factories and seeders

**Phase:** 1 · **Area:** Backend · **Size:** M · **Depends on:** T1-06
**Plan refs:** §8 Phase 1

## Steps
- [x] Factories for User (patient / doctor states), Patient, MedicalHistoryEntry, Appointment, Visit, Payment.
- [x] `DoctorSeeder`: one doctor account (credentials read from `.env`).
- [x] Default `doctor_settings` (45 min, 30 days, 2 h cancellation cut-off).
- [x] `working_hours`: one 17:00–21:00 range for Sat–Thu; no rows for Friday (day off).
- [x] 10 fake patients with a few history entries each.

## Done when
- [x] After `php artisan migrate:fresh --seed`, the doctor can log in and 10 patients exist.
