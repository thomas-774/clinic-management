# WCAG 2.1 AA audit (NFR-U.1)

**Date:** Oct 7, 2026 · **Task:** T11-13 · **Builds on:** T11-12 (jsx-a11y lint, axe in the page tests, skip link, focus handling, live regions)

**Result:** every page of every role, in Arabic (RTL) and English, passes the browser run with no axe violation at any level, no sideways scrolling at 320 px or at 200 % zoom, and a visible focus indicator on every Tab stop. All 14 issues found are fixed (list below). One check is still open for a person to do: the screen reader pass with NVDA (see "Not covered").

## How it was checked

| Check | How | Where |
| --- | --- | --- |
| Automated rules, incl. colour contrast | axe-core 4.14 in headless Edge, WCAG 2.0 / 2.1 A + AA tags, on the rendered page after its data loaded | `npm run a11y:audit` (`frontend/scripts/a11y-audit/run.mjs`) |
| Reflow (1.4.10) | Window set to 320 px, then 640 px (= 200 % zoom of a 1280 px window); the page must not scroll sideways. The run names the boxes that stick out. | same run |
| Keyboard (2.1.1, 2.4.3, 2.4.7) | Tab pressed through the page (up to 80 stops); every stop must sit on a visible element and show an outline or ring | same run; the log-in, booking and visit flows by keyboard only are Vitest tests (T11-12) |
| Contrast of the palette (1.4.3, 1.4.11) | Every text colour in `src/` against the background it is written on (its `bg-*`, else white and slate-50), from Tailwind's OKLCH values; fails under 4.5:1 | `npm run check:contrast` (`palette.mjs`), part of `npm run check` |
| Every page, both languages, in CI | axe in jsdom (no contrast) in 17 page tests, Arabic then English | `src/test/a11y.js`, T11-12 |

The browser run uses the production build served by `vite preview`. Every API call is answered inside the browser from `frontend/scripts/a11y-audit/fixtures.json`, which `dump-fixtures.php` captures through the API from the PerformanceSeeder data (fake people, 5 years of visits, Arabic and English text). No backend or real account is needed. Dialogs opened and checked: the booking confirmation and "New patient".

Pages (23 × Arabic and English = 46 runs): Login, Register, Status · patient: Home, Book an appointment (+ confirm dialog), My appointments · doctor: Dashboard, Schedule, Patients (+ New patient dialog), Patient details, New visit, New prescription, Edit prescription, Print prescription, Settings (General, Drugs, Prescription, Activity), Reports · assistant: Today, Patients, Patient page, Schedule.

## Final run

| | Arabic | English |
| --- | --- | --- |
| axe violations (any impact) | 0 on 23 pages + 2 dialogs | 0 on 23 pages + 2 dialogs |
| Sideways scroll at 320 px / 640 px | none | none |
| Tab stops without a visible indicator, or on hidden elements | none | none |
| Text / background pairs under 4.5:1 in `src/` | 0 of 61 | — |

## Issues found and fixed

| # | Page | WCAG | Issue | Fix |
| --- | --- | --- | --- | --- |
| 1 | Status | 1.4.3 Contrast | "ok" in green-600 on white, 3.2:1 | green-700 (4.9:1) |
| 2 | Patient details (English) | 2.5.3 Label in Name | "Write prescription" link was named "Write **a** prescription for the visit of …" | label now starts with the visible text |
| 3 | Patient details: prescriptions | 2.5.3 Label in Name | "Open / Edit" was named "Open the prescription of …" (both languages); Arabic "إعادة الطباعة" was named "إعادة طباعة روشتة …" | both labels contain the visible words |
| 4 | Patient details at 320 px | 1.4.10 Reflow | a visit's action buttons (Write prescription, PDF, Word, Edit) stayed on one line, 39 px too wide | they wrap |
| 5 | New / Edit prescription at 320 px | 1.4.10 Reflow | the page grid had no mobile column size, so its items kept their content's width (59 px too wide); card header rows did not wrap | `grid-cols-[minmax(0,1fr)]`; `Card` header wraps |
| 6 | Settings: Drugs at 320 px | 1.4.10 Reflow | an `sr-only` table header is absolutely placed, so it escaped the table's scroll box and widened the page by 115 px | the `DataTable` scroll box (and the outstanding dialog's) is `relative` |
| 7 | Reports at 320 / 640 px | 1.4.10 Reflow | the revenue chart could keep a wider size than its box (seen keeping its 991 px desktop width; it also starts at 800 px before measuring) | the chart box clips (`overflow-hidden`) |
| 8 | Settings: General | 2.4.7 Focus Visible | Chromium puts the focus inside time fields, so the field matches only `:focus-within`: no ring on the 12 working-hours fields | base style: date, time and month fields show the outline on `:focus-within` |
| 9 | Settings: General | 1.4.3 Contrast | the "–" between two times in slate-400, 2.6:1 | slate-500 |
| 10 | Schedule, Patient pages | 1.4.3 Contrast | "Cancelled" badge slate-500 on slate-100, 4.35:1 | slate-600 (7.6:1) |
| 11 | Every form | 1.4.11 Non-text Contrast | text fields are white on white, told apart only by a slate-300 border (1.5:1); error borders red-400 (2.9:1) | slate-500 (4.8:1) and red-600 (4.8:1) |
| 12 | Book an appointment | 1.4.3 Contrast · 1.4.1 Use of Color | chosen slot white on sky-600 (4.0:1), told only by its fill | sky-700 (5.9:1) and a ✓ before the time (hidden from screen readers, which hear "pressed") |
| 13 | Schedule (day) | 1.4.3 Contrast | "Start visit" button white on green-600, 3.2:1 (found by the palette check; no fixture had a checked-in patient) | green-700 (4.9:1) |
| 14 | Schedule (week) | 1.4.1 Use of Color | today's column was marked only by a thicker blue ring | a "Today" label and `aria-current="date"` |

Found by T11-12 and fixed there: the doctor's and assistant's patient pages had no `<h1>`; the jsx-a11y findings (Modal backdrop, Settings tabs, DrugSearch, prescription lines).

## Checked, no issue

- **Charts** (1.1.1, 1.4.1): the only chart is the daily revenue bar chart on Reports. It is one series (no colours to tell apart), has `role="img"` with the month's total as its name, and a "Show as table" button with every day's amount. The Dashboard shows numbers in text cards, not charts.
- **Slot grid and states** (1.4.1): the patient's grid lists only free slots (booked and blocked times are not shown); the chosen one has a ✓ (#12). Appointment states are text badges ("Booked", "Checked in", …; "Cancelled" also struck through); the settings preview has one kind of chip. Payment states are text ("Paid", "Partially paid", "Unpaid").
- **Print page**: black text on white, no state shown by colour; its two Tab stops are reachable and ringed.
- **Language and direction** (3.1.1, 3.1.2): `<html lang dir>` follow the language switcher; numbers and times keep `dir="ltr"`/`bdi` inside Arabic text.
- **Page titles** (2.4.2): every route sets "page · Clinic" in the current language (T11-12).

## Not covered

- **NVDA pass** on the three main flows (patient books; doctor records a visit with payment and prescription; assistant collects a payment). No screen reader is available to the automated setup; this needs a person with NVDA (or Narrator / TalkBack). What to listen for: the page name is read after each navigation (the focus moves to the `<h1>`); form errors are read when the focus jumps to the first wrong field; toasts are read (errors at once); the booking dialog is announced with its title; the slot buttons say "pressed" for the chosen one.
- Hover colours (`hover:`) are not in the palette check.
- Dialogs other than the two above are covered by the jsdom axe checks only where a page test opens them.

## Re-running

```bash
cd frontend
npm run check:contrast        # palette, seconds; also part of npm run check
npm run a11y:audit            # builds dist-a11y, serves it on :4174, drives headless Edge (EDGE_PATH to override); a few minutes
npm run a11y:audit -- --only=reports --build=no
```

Results land in `frontend/a11y-audit-results.json` (git-ignored); the run exits 1 on a serious or critical axe issue. To refresh the fixtures after API changes, see the header of `frontend/scripts/a11y-audit/dump-fixtures.php` (needs the T11-09 `clinic_bench` database).
