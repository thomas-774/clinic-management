# T9-10 — Frontend: PrescriptionForm page

**Phase:** 9 · **Area:** Frontend · **Size:** L · **Depends on:** T9-05, T9-08, T9-09
**Plan refs:** §7.2 · FR-J.3, FR-J.4 · RX-1

## Steps
- [ ] Routes `/doctor/patients/:id/prescriptions/new[?visit=:id]` and `/doctor/prescriptions/:id/edit`.
- [ ] Header: patient name, age, date (editable, default today); a yellow reminder box with the patient's **allergy** and **condition** history entries (hidden when none).
- [ ] Lines: each row has a number, DrugSearch (or the chosen drug as a chip with "change"), instructions input, move up / down and remove. "Add medication" adds a row and focuses its search. Max 15 rows.
- [ ] Side note: DrugInfoPanel follows the row being edited (its highlighted or chosen drug); "Use suggested dose" fills that row's instructions.
- [ ] Notes textarea; buttons **Save**, **Save & Print** (goes to the print page) and Cancel; client-side checks mirror RX-1; server 422 errors shown per line.
- [ ] Keyboard flow: Enter on a search picks the drug and jumps to its instructions; Enter in instructions adds the next row.

## Done when
- [ ] Vitest: add two catalogue drugs and one free-text line, fill instructions, save → correct POST body; Save & Print navigates to the print page; the side note follows the focused row; the reminder box shows the patient's allergies.
