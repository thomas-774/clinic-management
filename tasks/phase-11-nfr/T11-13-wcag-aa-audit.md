# T11-13 — WCAG 2.1 AA audit of every page

**Phase:** 11 · **Area:** Frontend · **Size:** L · **Depends on:** T11-12
**Plan refs:** §11.2 NFR-U.1

## Steps
- [ ] For every page of every role, in Arabic (RTL) and English: axe in the browser (headless Edge over CDP, as in earlier walkthroughs), keyboard pass, 200 % zoom and 320 px reflow, contrast check of the Tailwind palette (text ≥ 4.5:1, large text and UI parts ≥ 3:1).
- [ ] NVDA pass on the main flows: patient books; doctor records a visit with payment and prescription; assistant collects a payment.
- [ ] Charts (Dashboard / Reports): a text or table alternative for each chart; colour is not the only way to tell series apart.
- [ ] Print page and the slot grid: states (free / booked / blocked) not by colour only.
- [ ] Record each issue with page, WCAG criterion and fix in `docs/a11y/wcag-aa-audit.md`; fix them.

## Done when
- [ ] The audit file has no open AA issue.
- [ ] Browser axe run on every page reports no serious or critical issue in either language.
- [ ] Both test suites still pass.
