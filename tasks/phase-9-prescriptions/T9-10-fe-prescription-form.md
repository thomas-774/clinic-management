# T9-10 — Frontend: PrescriptionForm page

**Phase:** 9 · **Area:** Frontend · **Size:** L · **Depends on:** T9-05, T9-08, T9-09
**Plan refs:** §7.2 · FR-J.3, FR-J.4 · RX-1

## Steps
- [x] Routes `/doctor/patients/:id/prescriptions/new[?visit=:id]` and `/doctor/prescriptions/:id/edit`.
  Note: both render `pages/doctor/PrescriptionForm.jsx` (the edit route with `editing`). `api/prescriptions.js` and `hooks/usePrescriptions.js` (list, get, save, delete) are added here for T9-11 / T9-12. Until T9-11, `/doctor/prescriptions/:id/print` is a placeholder route so Save & Print has somewhere to go.
- [x] Header: patient name, age, date (editable, default today); a yellow reminder box with the patient's **allergy** and **condition** history entries (hidden when none).
  Note: the age is counted on the prescription date (`ageOn` in `utils/dates.js`) and hidden without a date of birth. With `?visit=` the header also shows "For the visit of …".
- [x] Lines: each row has a number, DrugSearch (or the chosen drug as a chip with "change"), instructions input, move up / down and remove. "Add medication" adds a row and focuses its search. Max 15 rows.
  Note: free-text lines show as a chip too, marked "Not in the catalogue". "Change" goes back to the search with the old name typed in. The last remaining row cannot be removed. DrugSearch got an `onInputChange` prop so the form knows when a row has typed text that was not picked.
- [x] Side note: DrugInfoPanel follows the row being edited (its highlighted or chosen drug); "Use suggested dose" fills that row's instructions.
  Note: beside the lines from 1024 px (`lg`), under the row being edited on narrower screens (`hooks/useMediaQuery.js`).
- [x] Notes textarea; buttons **Save**, **Save & Print** (goes to the print page) and Cancel; client-side checks mirror RX-1; server 422 errors shown per line.
  Note: empty rows are left out of the body. A row with a drug but no instructions, with typed text that was not picked, or with instructions over 255 characters stops the save with a message on that row. Save goes back to the patient page.
- [x] Keyboard flow: Enter on a search picks the drug and jumps to its instructions; Enter in instructions adds the next row.
  Note: if the row below is empty, Enter moves to it instead of adding another. Enter never submits the form from a search or instructions box.

## Done when
- [x] Vitest: add two catalogue drugs and one free-text line, fill instructions, save → correct POST body; Save & Print navigates to the print page; the side note follows the focused row; the reminder box shows the patient's allergies.
  Note: `pages/doctor/PrescriptionForm.test.jsx` (11 tests, also RX-1 checks, 422 per line, move / remove, the 15-row limit, `?visit=` and editing with PUT).
