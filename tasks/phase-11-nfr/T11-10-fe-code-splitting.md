# T11-10 — Code splitting and bundle budget

**Phase:** 11 · **Area:** Frontend · **Size:** M · **Depends on:** —
**Plan refs:** §11.2 NFR-P.4 · §7.2

## Steps
- [ ] In `AppRoutes.jsx`, load each role's pages with `React.lazy` + `Suspense` (fallback `FullPageLoader`): doctor, assistant and patient pages become separate chunks; Login / Register stay in the main chunk.
- [ ] Recharts is imported only by Dashboard / Reports, so it lands in their chunk.
- [ ] Prefetch the role's chunk right after login so the first navigation doesn't wait.
- [ ] `scripts/check-bundle-size.js`: after `vite build`, fails if the patient route's initial JS (entry + its chunks) is over 200 kB gzip or any chunk is over 500 kB minified. Wire it into `npm run check` (T11-11).
- [ ] Record before / after sizes here (baseline Oct 7, 2026: one 958.8 kB chunk, 283 kB gzip).

## Done when
- [ ] Build shows no >500 kB warning; the size script passes.
- [ ] Every route still renders (existing route tests pass; add one lazy-route test).
- [ ] Both test suites still pass.
