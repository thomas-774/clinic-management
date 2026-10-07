# T11-10 — Code splitting and bundle budget

**Phase:** 11 · **Area:** Frontend · **Size:** M · **Depends on:** —
**Plan refs:** §11.2 NFR-P.4 · §7.2

## Steps
- [x] In `AppRoutes.jsx`, load each role's pages with `React.lazy` + `Suspense` (fallback `FullPageLoader`): doctor, assistant and patient pages become separate chunks; Login / Register stay in the main chunk.
  Note: the loaders and lazy components live in `src/pages/lazyPages.js`, grouped by role; `AppRoutes` imports them and wraps `<Routes>` in one `Suspense`. Each page is its own chunk (the bundler puts code shared by several pages in small shared chunks), so a role's "chunk" is the set of its pages' chunks. Layouts, Login, Register and Status stay in the main chunk. `PatientsList` and `Schedule` are shared by doctor and assistant.
- [x] Recharts is imported only by Dashboard / Reports, so it lands in their chunk.
  Note: only `Reports` (through `RevenueChart`) imports it; the Dashboard has no chart. It is in `Reports-*.js` alone; the size script checks it never reaches the entry or a patient page's chunks.
- [x] Prefetch the role's chunk right after login so the first navigation doesn't wait.
  Note: `prefetchRolePages(role)` runs from an effect in `AppRoutes` whenever a role appears — after login or register, and after a reload with a stored token. Failures are ignored; opening the page asks again.
- [x] `scripts/check-bundle-size.js`: after `vite build`, fails if the patient route's initial JS (entry + its chunks) is over 200 kB gzip or any chunk is over 500 kB minified. Wire it into `npm run check` (T11-11).
  Note: reads `dist/.vite/manifest.json` (`build.manifest: true`) and follows static imports from the entry and all three patient pages; gzip is summed per file. Runs as `npm run check:size`; T11-11 adds it to `npm run check`. Unit tests in `scripts/check-bundle-size.test.js` (over 200 kB, over 500 kB, Recharts in a patient chunk).
- [x] Record before / after sizes here (baseline Oct 7, 2026: one 958.8 kB chunk, 283 kB gzip).

## Sizes (Oct 7, 2026, Vite 8.3)

| | Before | After |
| --- | --- | --- |
| JS chunks | 1 | 45 |
| Entry chunk | 964.5 kB · 284.0 kB gzip | 287.9 kB · 88.2 kB gzip (+ `client` 171.2 kB · 58.4 kB gzip, React DOM) |
| Patient pages, initial JS (entry + everything the three patient pages import) | 284.0 kB gzip | 160.4 kB gzip in 20 files (budget 200) |
| Largest chunk | 964.5 kB | `Reports` 363.2 kB · 105.3 kB gzip, with Recharts (budget 500) |
| Build warning "larger than 500 kB" | yes | no |

The baseline in this file (958.8 kB) was measured before Phase 11's last pages; today's single chunk was 964.5 kB.

## Done when
- [x] Build shows no >500 kB warning; the size script passes.
- [x] Every route still renders (existing route tests pass; add one lazy-route test).
  Note: `src/AppRoutes.test.jsx`: the role pages are `React.lazy`; `/patient` renders through `Suspense` and prefetches the patient pages only; no prefetch when logged out; prefetch loads every page of a role. Not checked in a browser (logged-in walkthroughs are not run in this setup); the route tests render every page through the lazy routes.
- [x] Both test suites still pass.
