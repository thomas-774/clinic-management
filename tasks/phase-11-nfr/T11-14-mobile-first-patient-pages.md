# T11-14 — Mobile-first patient pages

**Phase:** 11 · **Area:** Frontend · **Size:** M · **Depends on:** T11-10, T11-12
**Plan refs:** §11.2 NFR-U.2, NFR-P.5 · T7-03

## Steps
- [x] Rework Login, Register, PatientHome, BookAppointment and MyAppointments from 360 px up (base styles for phones, `sm:` / `md:` for bigger): no horizontal scroll, one column, primary action reachable by the thumb.
  Note: Login / Register: the card starts at the top on a phone (the keyboard covers the bottom half), centred from `sm:` up, with less padding. Home and My appointments: with no appointment, "Book now" is a full-width button; Cancel is full width on a phone. Dialogs (book confirm, cancel confirm, every `ConfirmDialog`) open as a bottom sheet with the buttons stacked full width and the action last, nearest the thumb; side by side from `sm:`. Long names / addresses wrap (`break-words`).
- [x] Tap targets ≥ 44 × 44 px (slot buttons, cancel, language switch); inputs ≥ 16 px font; `type="tel"` / `inputMode="numeric"` where they fit; `autocomplete` on login and register fields.
  Note: done in the shared parts, so the staff pages get it too: `Field` inputs are `min-h-11 text-base`, and the language switch, log out, the dialog × and buttons, the retry button, the password Show button and the patient nav links are ≥ 44 px; links inside cards became 44 px tall. Phone fields are `type="tel"` with `autocomplete="tel"` (Register and the Home contact form; the browser shows the phone keypad, so no `inputMode` is needed). No field on these pages is purely numeric. Login is `username` / `current-password` with auto-capitalise and auto-correct off; Register has `name`, `tel`, `email`, `street-address` and `new-password`.
- [x] Slot grid on a phone: day picker scrolls horizontally inside its own box, slots wrap; works in RTL.
  Note: the date input became `DayPicker`: one chip per bookable day (today … today + booking window) in a row that scrolls sideways inside its card, a radio group (one Tab stop; arrows move between days, mirrored in RTL; Home / End). Slots wrap in 3 / 4 / 6 columns. Found by the browser audit: an earlier version scrolled the chosen chip into view on mount, which moved Chrome's Tab starting point past the menus; removed (`focus()` already scrolls). The a11y audit's Tab pass also stopped at the first slot, because its loop check compared only the first 120 characters of `outerHTML` (the same for every slot); it now includes the text.
- [x] Lighthouse mobile (Slow 4G, Moto G profile) on each of the five pages, both languages; record the scores here.
  Note: `npm run mobile:audit` (`frontend/scripts/mobile-audit/run.mjs`) serves the production build over HTTPS + HTTP/2 with gzip and immutable `/assets` caching, as T7-05 will (now written there), with the API answered from the a11y-audit fixtures and a dummy token. It runs Lighthouse 13.5 (`npx`, not a dependency) with its default mobile settings (Moto G Power, Slow 4G, simulated throttling) and a cold cache. Scores below. Over plain HTTP/1.1 the logged-in pages measured LCP 2.6–2.7 s even after the fixes below, because Lighthouse then models six connections with a handshake each; production serves HTTP/2.
  Note: what changed for speed: (1) Cairo is self-hosted (`@fontsource-variable/cairo`, OFL) instead of Google Fonts: no render-blocking third-party CSS and no extra connections, and the CSP is now `style-src 'self'; font-src 'self'` (vite.config.js and T7-05). (2) `warmStart()`: opening a patient page with a stored token starts its chunk and data together with `/me` instead of after it. (3) A page whose chunk is already loaded renders directly instead of through `React.lazy`, which suspended once and then held the page back ~300 ms (React's Suspense throttling): observed LCP 518 → 158 ms. (4) The rest of the role's pages are prefetched 1.5 s after login instead of at once, so they don't compete with the page. (5) The three patient queries keep fresh data for 5 s, so the page doesn't fetch again right after warmStart.

  | Page | Lang | Performance | Accessibility | Best Practices | LCP | CLS |
  |---|---|---|---|---|---|---|
  | Login | ar | 98 | 100 | 100 | 1.97 s | 0.002 |
  | Login | en | 98 | 100 | 100 | 1.80 s | 0.002 |
  | Register | ar | 98 | 100 | 100 | 1.97 s | 0.003 |
  | Register | en | 98 | 100 | 100 | 1.81 s | 0.003 |
  | Home | ar | 99 | 100 | 100 | 2.02 s | 0.004 |
  | Home | en | 99 | 100 | 100 | 1.87 s | 0.004 |
  | Book | ar | 99 | 100 | 100 | 1.95 s | 0.003 |
  | Book | en | 99 | 100 | 100 | 1.95 s | 0.004 |
  | My appointments | ar | 99 | 100 | 100 | 1.95 s | 0.003 |
  | My appointments | en | 99 | 100 | 100 | 1.84 s | 0.003 |
- [x] T7-03 keeps its 360 px check as a regression check of this work.
  Note: a Note under T7-03's 360 px step points to `npm run mobile:audit` and its screenshots.

## Done when
- [x] Each page: Lighthouse mobile Performance ≥ 90, Accessibility ≥ 90, Best Practices ≥ 90; LCP < 2.5 s; CLS < 0.1.
  Note: see the table (final run, 2026-10-07). Lighthouse in headless Edge now and then fails a run with NO_FCP or a CDP timeout; the script retries up to three times.
- [x] Screenshots at 360 px in ar and en show no clipped or overflowing content (render wide enough for headless Edge's width limit, then check with device emulation).
  Note: CDP device emulation at 360 × 780 (DPR 2, touch) in a 1280 px window; full-page screenshots in `frontend/mobile-audit/` (git-ignored), checked by eye, plus the booking sheet. In the same pass the script checks every page for sideways scroll, clipped boxes, boxes past the screen edge outside a scrolling box, tap targets under 44 × 44 px and inputs under 16 px: none on all 10 runs. The fixtures' patient got an upcoming, cancellable appointment so the Cancel button is on the page. The T11-13 WCAG audit (`npm run a11y:audit`) still reports 0 axe issues and no reflow on every page.
- [x] Both test suites still pass.
  Note: Vitest 313 (new: day picker keys incl. RTL, autocomplete / keyboard types, warmStart, deferred prefetch and remembered chunks); `npm run check` green (bundle budget: patient initial JS 162.6 kB of 200 kB). Pest 782, `composer check` green.
