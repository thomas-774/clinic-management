# T7-03 — Empty, loading and error states; mobile check

**Phase:** 7 · **Area:** Frontend · **Size:** M · **Depends on:** all frontend tasks
**Plan refs:** §8 Phase 7

## Steps
- [ ] Every page: loading skeleton, empty state, and error state with a retry button.
- [ ] Global error boundary.
- [ ] Patient pages tested at 360 px width (Home, Book, My appointments).
  Note (T11-14): the pages were reworked mobile-first in T11-14. Use this check as its regression check: `npm run mobile:audit` (in `frontend/`) renders Login, Register, Home, Book and My appointments at 360 px in Arabic and English, fails on sideways scroll, clipped text, tap targets under 44 × 44 px or inputs under 16 px, and saves a screenshot of each in `frontend/mobile-audit/`. Look through the screenshots too.
- [ ] Check every page in Arabic (RTL, the default) and in English (LTR).

## Done when
- [ ] Walking through every page with the network throttled or offline shows a sensible state.
