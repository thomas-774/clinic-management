# T1-06 — Enums and Eloquent models

**Phase:** 1 · **Area:** Backend · **Size:** M · **Depends on:** T1-01 … T1-05
**Plan refs:** §5.2 Relationships · §6.1 Models / Enums

## Steps
- [x] Enums: `UserRole` (patient, doctor), `AppointmentStatus` (booked, checked_in, completed, cancelled, no_show), `HistoryType`, `PaymentMethod`.
- [x] Models: User, Patient, MedicalHistoryEntry, DoctorSetting, WorkingHour, BlockedTime, Appointment, Visit, Payment.
- [x] Relationships per §5.2 (User hasOne Patient; Patient hasMany history / appointments / visits; Appointment hasOne Visit; Visit hasMany Payments; doctor relations).
- [x] Casts: enums, dates, `decimal:2` for money, `boolean` for flags.
- [x] `$fillable` on every model.

## Done when
- [x] In `php artisan tinker`, `$patient->user`, `$visit->payments` and `$appointment->visit` all resolve.
