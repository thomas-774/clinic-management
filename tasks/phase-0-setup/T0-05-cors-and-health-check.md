# T0-05 — Connect frontend to backend (CORS + health check)

**Phase:** 0 · **Area:** Full stack · **Size:** S · **Depends on:** T0-03, T0-04
**Plan refs:** §8 Phase 0

## Steps
- [x] Backend: `GET /api/v1/health` → `{ "status": "ok", "time": <ISO 8601> }`.
- [x] Configure `config/cors.php` to allow the Vite dev origin.
- [x] Frontend: `api/client.js` — Axios instance using `VITE_API_URL`.
- [x] Frontend: wrap app in `QueryClientProvider`; home page calls `/health` and shows the result.

## Done when
- [x] The React home page shows "ok" and the server time from Laravel.
