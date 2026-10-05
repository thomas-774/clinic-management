# T9-07 — Tests: prescription permissions and privacy

**Phase:** 9 · **Area:** Backend · **Size:** M · **Depends on:** T9-03, T9-05, T9-06
**Plan refs:** §2 · §9.1 · §9.2 · FR-J.7 · RX-3

## Steps
- [x] A data-driven test over every `/doctor/drugs*` and prescriptions route: patient → 403, assistant → 403, guest → 401.
  Note: `tests/Feature/PrescriptionPrivacyTest.php`. The routes are a Pest dataset run against real records (so a 404 can't hide a missing 403); a coverage test fails if a drug or prescription route is registered without being in the dataset. It also checks the doctor gets through on every route, and that a refused patient / assistant request changes nothing.
- [x] Patient and assistant responses (patient profile, assistant patient page) never contain prescriptions or drug data.
  Note: also the patient's appointments, the assistant's patient list, unpaid visits and schedule — none contains the drug's name, uses, warnings, ingredient, the prescription's notes or instructions, or the words "prescription" / "drug".
- [x] Hidden drugs: not in search, still shown on old prescriptions.
  Note: not found by trade name or ingredient; still on the prescription, in the patient's prescriptions list and readable by id.
- [x] No drug API response includes a price field.
  Note: checked recursively over every key of the search, catalogue, show, create and update responses (create / update are sent a `price`), and of the prescription responses.

## Done when
- [x] All of the above pass; backend suite green.
  Note: 584 backend tests pass.
