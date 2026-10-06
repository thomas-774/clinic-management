# T10-07 — Frontend: download helper and useVisitExport

**Phase:** 10 · **Area:** Frontend · **Size:** S · **Depends on:** T10-05
**Plan refs:** §7.3 Visit files · FR-K.1, FR-K.4, FR-K.5

## Steps
- [ ] `api/visits.js`: `exportVisit(id, format)` → `client.get('/doctor/visits/{id}/export', { params: { format }, responseType: 'blob' })` (the client already sends the token and `Accept-Language`).
- [ ] `utils/download.js`: `saveBlob(blob, fileName)` — object URL, temporary `<a download>`, click, revoke; `fileNameFrom(headers, fallback)` reads `Content-Disposition`.
- [ ] `hooks/useVisits.js`: `useVisitExport()` mutation that downloads the file and shows a toast "PDF saved." / "Word file saved."; on error, reads the JSON message out of the error blob and shows it.
- [ ] i18n keys in both `ar.json` and `en.json` (identical key sets).

## Done when
- [ ] Vitest: `fileNameFrom` handles quoted, unquoted and missing headers; `useVisitExport` calls the API with `responseType: 'blob'`, creates and revokes the object URL, and shows the error message from a 403/422 blob.
