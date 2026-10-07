# T11-01 — Security headers on every response

**Phase:** 11 · **Area:** Backend · **Size:** S · **Depends on:** —
**Plan refs:** §11.2 NFR-S.1 · §9.2

## Steps
- [x] `SecurityHeaders` middleware appended globally in `bootstrap/app.php` (next to `SetLocale`): `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer`, `X-Frame-Options: DENY`, `Permissions-Policy: camera=(), microphone=(), geolocation=()`.
- [x] `Cache-Control: no-store` (and `Pragma: no-cache`) on every authenticated API response, so medical data never stays in the shared clinic PC's browser cache. Visit file downloads (T10-05) keep their `Content-Disposition` and get `no-store` too.
  Note: "authenticated" = the `sanctum` guard resolved a user for the request (`Auth::guard('sanctum')->hasUser()`), so a logged-in user's 403 / 422 also gets `no-store`; a guest's 401 / 404 does not.
- [x] `Strict-Transport-Security: max-age=31536000; includeSubDomains` only when `app.env` is not `local` / `testing`.
- [x] Write the Nginx headers for the SPA into T7-05: the same four headers plus a CSP — `default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'`. Check the print page (T9-11) and the download helper (T10-07) still work under it.
  Note: the same policy is in `frontend/vite.config.js` (`preview.headers`), with the API origin from `VITE_API_URL` added to `connect-src` because locally the API runs on another port. Code review: no `eval` / `new Function`, no inline `<script>` or `<style>`; React `style` props are set through the CSSOM, which `style-src` does not block; the download helper uses a `blob:` link with `download`, which CSP does not govern. The in-browser check is still open (see below).
  Note (T11-14): Cairo is self-hosted since T11-14, so the policy is now `style-src 'self'; font-src 'self'` (no Google Fonts); T7-05 and `vite.config.js` are updated.
- [x] Remove `X-Powered-By` (`expose_php = Off`) in the server checklist (T7-05).

## Done when
- [x] Feature test: a guest call, a logged-in call and a 404 each carry the headers; only authenticated responses carry `no-store`. (`tests/Feature/SecurityHeadersTest.php`, also a logged-in 403, the visit file download and HSTS per environment.)
- [ ] Locally built SPA served with the CSP header (Vite preview or a local Nginx) has no CSP errors in the console on every page, incl. print and download.
  Note (Oct 7, 2026): not run. Creating login tokens for a headless-browser walkthrough was blocked by the session's permission rules. To check by hand: `npm run build && npm run preview` in `frontend/` (port 4173; add `http://localhost:4173` to the backend's `FRONTEND_URL` for CORS), log in as each role, open every page, print a prescription and download a visit file, and look for "Content Security Policy" errors in the console.
  Note (T11-16): now measured for every page except the download. `npm run a11y:audit` serves the build with this CSP through `vite preview` and fails on any `securitypolicyviolation`: 0 on 23 pages × ar / en, their dialogs and the print page (Oct 7, 2026). Left open for the visit-file download only; moved to the UAT in T7-09.
- [x] Both test suites still pass.
