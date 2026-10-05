# T7-05 — Server provisioning

**Phase:** 7 · **Area:** DevOps · **Size:** M · **Depends on:** —
**Plan refs:** §9.3

## Steps
- [ ] VPS (DigitalOcean / Hostinger) or Laravel Forge.
- [ ] Nginx, PHP-FPM 8.3, MySQL 8, Composer, Node (for builds).
- [ ] Domain + HTTPS (Let's Encrypt); force HTTPS redirects.
- [ ] Firewall: only 22 / 80 / 443 open; MySQL not public.
- [ ] Separate staging and production databases.

## Done when
- [ ] An HTTPS page loads on the staging domain.
