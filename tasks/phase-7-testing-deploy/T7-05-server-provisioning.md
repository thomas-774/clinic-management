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
  add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self'; font-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'" always;
  add_header X-Content-Type-Options "nosniff" always;
  add_header Referrer-Policy "no-referrer" always;
  add_header X-Frame-Options "DENY" always;
  add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
  add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
  ```
  `connect-src 'self'` assumes the API is served on the same domain (`/api/v1`); if it gets its own subdomain, add that origin. `frontend/vite.config.js` holds the same policy for `npm run preview`.
  Note (T11-16): `npm run a11y:audit` runs every page of the build under that policy and fails on any CSP violation (0 on Oct 7, 2026). After deploying, check that `curl -sI https://<domain>/` sends the same policy as `vite.config.js`.
- [ ] Generate `APP_KEY` once per environment (`php artisan key:generate --show`, set it in `.env`) and store production's in the password manager before any patient data is entered: the medical text is encrypted with it (T11-06, NFR-S.6) and is lost without it. Staging gets its own key. Never regenerate it on a server that has data — rotate instead (T7-07).
- [ ] ASVS L1 server items (T11-07, `docs/security/asvs-l1.md`, the rows marked "Deploy"):
  - TLS: `ssl_protocols TLSv1.2 TLSv1.3;` with Mozilla's "intermediate" cipher list; port 80 only redirects to HTTPS (9.1.1 – 9.1.3).
  - Nginx roots are `backend/public` and `frontend/dist` only; `autoindex off;` and `location ~ /\.(?!well-known) { deny all; }` so `.env` / `.git` are never served (4.3.2, 12.5.1).
  - `client_max_body_size 1m;` (12.1.1).
  - `.env`: `APP_ENV=production`, `APP_DEBUG=false` (14.3.2).
  - Keep Nginx access logs no longer than needed (e.g. 14 days with logrotate): the patient search term is in the URL (8.3.1).
  - No DNS record points at a server or service the clinic no longer uses (10.3.3).
- [ ] Serve the SPA compressed and cached (T11-14, NFR-P.5; the Lighthouse numbers there assume it): `gzip on; gzip_types text/css application/javascript application/json image/svg+xml;`, `location /assets/ { add_header Cache-Control "public, max-age=31536000, immutable"; }` (file names carry a content hash; repeat the security headers in that block, as `add_header` there replaces the server's), and `Cache-Control: no-cache` on `index.html`. The Cairo font files are in `/assets/` too (self-hosted, `font-src 'self'`).
- [ ] Hide the PHP version: `expose_php = Off` in the FPM `php.ini` (no `X-Powered-By` header) and `server_tokens off;` in Nginx.
- [ ] Laravel scheduler (T11-05, NFR-S.5): a cron line for the app's user, `* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1`. It runs `audit:prune` daily, which deletes audit rows older than 5 years (`CLINIC_AUDIT_RETENTION_YEARS`). Check with `php artisan schedule:list`.
- [ ] No coverage driver on staging or production: PCOV (T11-11) is only for `composer check` on dev machines and in CI (T7-08, `coverage: pcov`). Leave it out of the FPM `php.ini`.

## Done when
- [ ] An HTTPS page loads on the staging domain.
