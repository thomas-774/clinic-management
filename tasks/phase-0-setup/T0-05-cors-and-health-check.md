# T0-05 — Connect frontend to backend (CORS + health check)

**Phase:** 0 · **Area:** Full stack · **Size:** S · **Depends on:** T0-03, T0-04
**Plan refs:** §8 Phase 0

## Steps
- [ ] Backend: `GET /api/v1/health` → `{ "status": "ok", "time": <ISO 8601> }`.
- [ ] Configure `config/cors.php` to allow the Vite dev origin.
- [ ] Frontend: `api/client.js` — Axios instance using `VITE_API_URL`.
- [ ] Frontend: wrap app in `QueryClientProvider`; home page calls `/health` and shows the result.

## Done when
- [ ] The React home page shows "ok" and the server time from Laravel.
