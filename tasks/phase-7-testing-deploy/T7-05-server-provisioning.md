# T7-05 — Server provisioning

**Phase:** 7 · **Area:** DevOps · **Size:** M · **Depends on:** —
**Plan refs:** §9.3

## Steps
- [ ] VPS (DigitalOcean / Hostinger) or Laravel Forge.
- [ ] Nginx, PHP-FPM 8.3, MySQL 8, Composer, Node (for builds).
- [ ] PHP extensions for the visit files (Phase 10, mPDF and PHPWord): mbstring, gd, zip, xml, dom; `storage/app/mpdf` writable by PHP-FPM.
- [ ] Domain + HTTPS (Let's Encrypt); force HTTPS redirects.
- [ ] Firewall: only 22 / 80 / 443 open; MySQL not public.
- [ ] Separate staging and production databases.
- [ ] Security headers for the SPA (T11-01, NFR-S.1) in the Nginx `server` block that serves `frontend/dist` (the API adds its own in `SecurityHeaders` middleware; it also sends HSTS outside local / testing):
  ```nginx
  add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'" always;
  add_header X-Content-Type-Options "nosniff" always;
  add_header Referrer-Policy "no-referrer" always;
  add_header X-Frame-Options "DENY" always;
  add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
  add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
  ```
  `connect-src 'self'` assumes the API is served on the same domain (`/api/v1`); if it gets its own subdomain, add that origin. `frontend/vite.config.js` holds the same policy for `npm run preview`.
- [ ] Hide the PHP version: `expose_php = Off` in the FPM `php.ini` (no `X-Powered-By` header) and `server_tokens off;` in Nginx.

## Done when
- [ ] An HTTPS page loads on the staging domain.
