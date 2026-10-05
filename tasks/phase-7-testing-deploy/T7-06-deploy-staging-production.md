# T7-06 — Deploy backend and frontend (staging → production)

**Phase:** 7 · **Area:** DevOps · **Size:** M · **Depends on:** T7-05, T7-04
**Plan refs:** §9.3

## Steps
- [ ] Backend: `.env` (APP_ENV=production, APP_DEBUG=false, Africa/Cairo), `composer install --no-dev`, `php artisan migrate --force`, `config:cache`, `route:cache`.
- [ ] Frontend: `npm run build`; Nginx serves `dist/` with an SPA fallback to `index.html`.
- [ ] Proxy `/api` to Laravel on the same domain (no CORS needed).
- [ ] Deploy to staging → doctor tests → deploy to production with the real doctor account (not demo data).
- [ ] Write a short `DEPLOY.md` listing the steps.

## Done when
- [ ] Production is live and the doctor can log in.
