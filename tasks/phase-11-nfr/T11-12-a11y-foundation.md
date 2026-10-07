# T11-12 — Accessibility foundation

**Phase:** 11 · **Area:** Frontend · **Size:** M · **Depends on:** —
**Plan refs:** §11.2 NFR-U.1, NFR-Q.3

## Steps
- [ ] Turn on oxlint's `jsx-a11y` plugin (recommended rules as errors); fix what it finds.
- [ ] Add `axe-core` (via `vitest-axe` or a small helper in `src/test/`) and a `expectNoA11yViolations(container)` check; call it in the main page tests, in `ar` and in `en`.
- [ ] Layouts: a "Skip to content" link, `<main>` landmark, `lang` and `dir` on `<html>` follow the language switcher, the page `<title>` changes per route.
- [ ] Focus: on route change move focus to the page heading; dialogs trap focus and return it on close; visible focus ring everywhere (Tailwind `focus-visible:` on the shared buttons and inputs).
- [ ] Forms: every input has a label; validation errors use `aria-invalid` + `aria-describedby`; the first invalid field gets focus on submit.
- [ ] Toasts in an `aria-live="polite"` region (errors `assertive`).

## Done when
- [ ] oxlint passes with jsx-a11y on.
- [ ] axe checks pass with no serious or critical issues in the page tests, both languages.
- [ ] Keyboard only: log in, book an appointment, record a visit — no mouse needed.
- [ ] Both test suites still pass.
