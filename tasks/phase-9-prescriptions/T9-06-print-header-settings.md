# T9-06 — Prescription print header in doctor settings

**Phase:** 9 · **Area:** Backend · **Size:** S · **Depends on:** T3-01
**Plan refs:** §5.1 doctor_settings · FR-J.5, FR-J.6

## Steps
- [ ] Migration adding to `doctor_settings`: clinic_name, doctor_title, clinic_address, clinic_phone, prescription_footer (all nullable), prescription_paper ENUM('A5','A4') default 'A5'.
- [ ] Extend `GET / PUT /doctor/settings` and its Form Request (strings max 150, footer max 255, paper in A5/A4). Existing settings keep working without the new fields.
- [ ] `DoctorSeeder` fills demo values (clinic name, title, address, phone).

## Done when
- [ ] Feature test: the fields save and read back; an invalid paper size → 422; the old settings tests still pass.
