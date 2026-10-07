# T11-01 — Security headers on every response

**Phase:** 11 · **Area:** Backend · **Size:** S · **Depends on:** —
**Plan refs:** §11.2 NFR-S.1 · §9.2

## Steps
- [ ] `SecurityHeaders` middleware appended globally in `bootstrap/app.php` (next to `SetLocale`): `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer`, `X-Frame-Options: DENY`, `Permissions-Policy: camera=(), microphone=(), geolocation=()`.
- [ ] `Cache-Control: no-store` (and `Pragma: no-cache`) on every authenticated API response, so medical data never stays in the shared clinic PC's browser cache. Visit file downloads (T10-05) keep their `Content-Disposition` and get `no-store` too.
- [ ] `Strict-Transport-Security: max-age=31536000; includeSubDomains` only when `app.env` is not `local` / `testing`.
- [ ] Write the Nginx headers for the SPA into T7-05: the same four headers plus a CSP — `default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'`. Check the print page (T9-11) and the download helper (T10-07) still work under it.
- [ ] Remove `X-Powered-By` (`expose_php = Off`) in the server checklist (T7-05).

## Done when
- [ ] Feature test: a guest call, a logged-in call and a 404 each carry the headers; only authenticated responses carry `no-store`.
- [ ] Locally built SPA served with the CSP header (Vite preview or a local Nginx) has no CSP errors in the console on every page, incl. print and download.
- [ ] Both test suites still pass.
