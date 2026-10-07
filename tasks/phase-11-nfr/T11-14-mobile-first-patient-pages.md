# T11-14 — Mobile-first patient pages

**Phase:** 11 · **Area:** Frontend · **Size:** M · **Depends on:** T11-10, T11-12
**Plan refs:** §11.2 NFR-U.2, NFR-P.5 · T7-03

## Steps
- [ ] Rework Login, Register, PatientHome, BookAppointment and MyAppointments from 360 px up (base styles for phones, `sm:` / `md:` for bigger): no horizontal scroll, one column, primary action reachable by the thumb.
- [ ] Tap targets ≥ 44 × 44 px (slot buttons, cancel, language switch); inputs ≥ 16 px font; `type="tel"` / `inputMode="numeric"` where they fit; `autocomplete` on login and register fields.
- [ ] Slot grid on a phone: day picker scrolls horizontally inside its own box, slots wrap; works in RTL.
- [ ] Lighthouse mobile (Slow 4G, Moto G profile) on each of the five pages, both languages; record the scores here.
- [ ] T7-03 keeps its 360 px check as a regression check of this work.

## Done when
- [ ] Each page: Lighthouse mobile Performance ≥ 90, Accessibility ≥ 90, Best Practices ≥ 90; LCP < 2.5 s; CLS < 0.1.
- [ ] Screenshots at 360 px in ar and en show no clipped or overflowing content (render wide enough for headless Edge's width limit, then check with device emulation).
- [ ] Both test suites still pass.
