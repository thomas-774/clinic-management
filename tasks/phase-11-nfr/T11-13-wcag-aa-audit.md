# T11-13 — WCAG 2.1 AA audit of every page

**Phase:** 11 · **Area:** Frontend · **Size:** L · **Depends on:** T11-12
**Plan refs:** §11.2 NFR-U.1

## Steps
- [x] For every page of every role, in Arabic (RTL) and English: axe in the browser (headless Edge over CDP, as in earlier walkthroughs), keyboard pass, 200 % zoom and 320 px reflow, contrast check of the Tailwind palette (text ≥ 4.5:1, large text and UI parts ≥ 3:1).
  Note: `npm run a11y:audit` (`frontend/scripts/a11y-audit/run.mjs`): 23 pages × 2 languages and two dialogs, on the production build served by `vite preview`. Instead of logging in to a running backend, the browser answers every API call from `fixtures.json` (captured through the API from the T11-09 fake dataset by `dump-fixtures.php`, as `clinic:bench` calls it), with a dummy token string in localStorage: no real account, token or backend is involved. Per page: axe (WCAG 2.0/2.1 A + AA, contrast on), sideways scroll at 320 px and at 640 px (= 200 % zoom of 1280 px), and a Tab pass that checks each stop is visible and shows a ring. Contrast of the palette: `npm run check:contrast` (`palette.mjs`) checks every text colour in `src/` on its background from Tailwind's OKLCH values and is now part of `npm run check`; it agrees with axe's measured ratio (3.22 vs 3.21 for green-600). Input borders were checked against 3:1 separately (#11 in the audit).
- [ ] NVDA pass on the main flows: patient books; doctor records a visit with payment and prescription; assistant collects a payment.
  Note: not done — no screen reader is available to the automated setup. What to listen for is in `docs/a11y/wcag-aa-audit.md` ("Not covered"); left for a person.
- [x] Charts (Dashboard / Reports): a text or table alternative for each chart; colour is not the only way to tell series apart.
  Note: already in place: the only chart (Reports, daily revenue) is one series with `role="img"`, a name with the month's total and a "Show as table" button. The Dashboard has no chart. Fixed: the chart could keep a wider size than its box and widen the page (#7).
- [x] Print page and the slot grid: states (free / booked / blocked) not by colour only.
  Note: the patient's slot grid shows only free slots; the chosen one now has a ✓ besides its colour (#12). Appointment and payment states are text badges. The print page shows no state. Also fixed: today's column in the week view was marked only by its ring (#14).
- [x] Record each issue with page, WCAG criterion and fix in `docs/a11y/wcag-aa-audit.md`; fix them.
  Note: 14 issues, all fixed: contrast (Status text, the working-hours dash, the Cancelled badge, the chosen slot, "Start visit"), Label in Name (three prescription links), reflow at 320 px (visit buttons, prescription page grid, an `sr-only` header escaping the table's scroll box, the chart), focus visible (Chromium time fields only match `:focus-within`), non-text contrast of input borders (1.5:1 → 4.8:1), and colour-only states (chosen slot, today in the week view).

## Done when
- [x] The audit file has no open AA issue.
  Note: the only open item is the NVDA pass above, which is a check not yet done, not a known issue.
- [x] Browser axe run on every page reports no serious or critical issue in either language.
  Note: final run: 0 axe violations of any impact on 46 page runs and 4 dialog runs; no sideways scroll at 320 / 640 px; every Tab stop visible with a ring.
- [x] Both test suites still pass.
  Note: Vitest 307 (new: palette checks, the ✓ on the chosen slot, today in the week view); Pest unchanged.
