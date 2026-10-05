# T9-06 — Prescription print header in doctor settings

**Phase:** 9 · **Area:** Backend · **Size:** S · **Depends on:** T3-01
**Plan refs:** §5.1 doctor_settings · FR-J.5, FR-J.6

## Steps
- [x] Migration adding to `doctor_settings`: clinic_name, doctor_title, clinic_address, clinic_phone, prescription_footer (all nullable), prescription_paper ENUM('A5','A4') default 'A5'.
  Note: the four header strings are VARCHAR(150), matching the request's max.
- [x] Extend `GET / PUT /doctor/settings` and its Form Request (strings max 150, footer max 255, paper in A5/A4). Existing settings keep working without the new fields.
  Note: every field of `PUT /doctor/settings` is now `sometimes`, so Settings → Prescription (T9-13) and the booking settings card can each save only their own fields; a field that is not sent keeps its value, and a header field sent empty is cleared. Field names are localized (ar / en).
- [x] `DoctorSeeder` fills demo values (clinic name, title, address, phone).
  Note: also a demo footer (working hours). Only blank fields are filled, so re-seeding never overwrites what the doctor typed.

## Done when
- [x] Feature test: the fields save and read back; an invalid paper size → 422; the old settings tests still pass.
  Note: the old "returns the defaults" test uses `assertExactJson`, so it now also lists the new fields (null, paper A5); everything else in it is unchanged. Seeder behaviour is covered in `SeederTest`.
