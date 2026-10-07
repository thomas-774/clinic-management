# T11-12 — Accessibility foundation

**Phase:** 11 · **Area:** Frontend · **Size:** M · **Depends on:** —
**Plan refs:** §11.2 NFR-U.1, NFR-Q.3

## Steps
- [x] Turn on oxlint's `jsx-a11y` plugin (recommended rules as errors); fix what it finds.
  Note: `.oxlintrc.json` lists the 31 rules of eslint-plugin-jsx-a11y's recommended config as errors, with the same options (lists may be `listbox`, items `option`, dialogs take key handlers, `onFocus` is not counted). `aria-role` has `ignoreNonDOM` (`<RoleRoute role="patient">` is a prop, not ARIA). Off: `prefer-tag-over-role` (not in the recommended set; it asks for `<output>` instead of `role="status"`) and `control-has-associated-label` (off in the recommended set too). Fixed: the Modal backdrop is `role="presentation"` and closes only when the press lands on it; the Settings tab keys moved from the `tablist` to each tab; DrugSearch lost its unused `autoFocus`, picks an option on mouse press (keyboard picks from the input with ↑ ↓ Enter, as before), and never lets Enter submit the form, so PrescriptionForm's wrapper `div` with a key handler is gone.
- [x] Add `axe-core` (via `vitest-axe` or a small helper in `src/test/`) and a `expectNoA11yViolations(container)` check; call it in the main page tests, in `ar` and in `en`.
  Note: `src/test/a11y.js`: `expectNoA11yViolations()` fails on serious or critical issues and lists them; `expectNoA11yViolationsInBothLanguages(render)` renders in Arabic, then English, waiting until the page has its `<h1>` and no "Loading…". Colour contrast is off there (jsdom has no layout); T11-13 checks it in the browser. 17 pages have the check in an `accessibility` block of their test file: login, register, patient home / book / my appointments, dashboard, schedule, patients list, patient details, visit form, prescription form, prescription print, reports, settings, assistant today, assistant patients list and patient page. Found: the doctor's and the assistant's patient pages had no `<h1>` (the name was a card's `<h2>`); `Card` now takes `headingLevel`. A self-test proves the helper fails on a nameless button and an unlabelled input.
- [x] Layouts: a "Skip to content" link, `<main>` landmark, `lang` and `dir` on `<html>` follow the language switcher, the page `<title>` changes per route.
  Note: `lang` / `dir` already followed the switcher (`src/i18n/index.js`). New: `SkipLink` (first Tab stop in all three layouts) to `<main id="main" tabIndex={-1}>`; the auth layout's card is now the `<main>` and its top row a `<header>`. `RouteChange` sets `<title>` to "page · Clinic" for every route of §7.2, in the current language.
- [x] Focus: on route change move focus to the page heading; dialogs trap focus and return it on close; visible focus ring everywhere (Tailwind `focus-visible:` on the shared buttons and inputs).
  Note: `RouteChange` focuses the new page's `<h1>` after a navigation (not on the first load), waiting up to 5 s for a lazy page, and leaves the focus alone when the page already put it in a field. `Modal` now keeps Tab / Shift+Tab inside (it already returned focus). The ring: one base rule in `index.css` (`:focus-visible` → 2 px `sky-700` outline) covers every button, link and control instead of classes on each; the inputs' own focus rings went from `-200` shades (~1.5:1 on white) to `sky-600` / `red-600`.
- [x] Forms: every input has a label; validation errors use `aria-invalid` + `aria-describedby`; the first invalid field gets focus on submit.
  Note: labels and `aria-invalid` / `aria-describedby` were already in `Field`, DrugSearch and the prescription lines; axe's label rules pass on every page above. New `components/form/Form.jsx` (`<form noValidate>`) moves the focus to the first `aria-invalid` field after a submit, whether the error is found at once or comes back from the server (up to 15 s later); all 16 forms use it.
- [x] Toasts in an `aria-live="polite"` region (errors `assertive`).
  Note: two regions, always in the page (a region added together with its text is often not read): successes `aria-live="polite"`, errors `aria-live="assertive"`. The toasts themselves no longer carry `role="status"` / `role="alert"`, so nothing is announced twice; tests read them through `successToasts()` / `errorToasts()`.

## Done when
- [x] oxlint passes with jsx-a11y on.
- [x] axe checks pass with no serious or critical issues in the page tests, both languages.
- [x] Keyboard only: log in, book an appointment, record a visit — no mouse needed.
  Note: as Vitest + user-event tests that only press Tab, type and Enter: log in (the first Tab lands on "Skip to content"), book a slot through the confirm dialog, record a visit with payment. Also: after a 422 the focus is on the rejected field (login, visit). Not repeated in a real browser here (logged-in browser walkthroughs are not run in this setup); T11-13's browser audit covers it.
- [x] Both test suites still pass.
  Note: Vitest 304 tests (was 271); Pest unchanged.
