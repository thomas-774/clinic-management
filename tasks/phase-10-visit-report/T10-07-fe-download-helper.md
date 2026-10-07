# T10-07 — Frontend: download helper and useVisitExport

**Phase:** 10 · **Area:** Frontend · **Size:** S · **Depends on:** T10-05
**Plan refs:** §7.3 Visit files · FR-K.1, FR-K.4, FR-K.5

## Steps
- [x] `api/visits.js`: `exportVisit(id, format)` → `client.get('/doctor/visits/{id}/export', { params: { format }, responseType: 'blob' })` (the client already sends the token and `Accept-Language`).
  Note: it resolves to the whole axios response (not `.data`), so the hook can read the headers.
- [x] `utils/download.js`: `saveBlob(blob, fileName)` — object URL, temporary `<a download>`, click, revoke; `fileNameFrom(headers, fallback)` reads `Content-Disposition`.
  Note: `fileNameFrom` also reads RFC 5987 `filename*=UTF-8''…` and `AxiosHeaders` in any case. A third helper, `parseBlobError(error)`, turns a JSON error blob back into an object so the usual `errorMessage()` works.
- [x] `hooks/useVisits.js`: `useVisitExport()` mutation that downloads the file and shows a toast "PDF saved." / "Word file saved."; on error, reads the JSON message out of the error blob and shows it.
  Note: called as `mutate({ id, format })`; the fallback name is `visit-<id>.<format>`. With no server message (network down) it shows "The file could not be downloaded. Please try again." The parsed error stays on `mutation.error`, for T10-08.
- [x] i18n keys in both `ar.json` and `en.json` (identical key sets).
  Note: a new `visitFile` section: `pdfSaved`, `docxSaved`, `failed`.

## Done when
- [x] Vitest: `fileNameFrom` handles quoted, unquoted and missing headers; `useVisitExport` calls the API with `responseType: 'blob'`, creates and revokes the object URL, and shows the error message from a 403/422 blob.
  Note: `src/utils/download.test.js` and `src/hooks/useVisits.test.jsx` (the real axios client with a fake adapter). Frontend 241 passed, backend 637 passed, oxlint clean.
