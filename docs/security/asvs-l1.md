# OWASP ASVS 4.0.3 Level 1 review

**Task:** T11-07 · **Requirement:** NFR-S.7 (plan §11.2) · **Reviewed:** Oct 7, 2026 against `main` after T11-06.

Every Level 1 item of ASVS 4.0.3 is listed with one result:

- **Pass**: met by the code or config; the reason names the code or test.
- **Pass (fixed)**: failed at review time and was fixed in T11-07.
- **Deploy**: met by a server setting written into the T7-05 checklist; checked when staging is built.
- **N/A**: the feature the item is about does not exist in this app.
- **Accepted**: fails today and is accepted on purpose; the reason and the risk are in plan §11.3 under the ID shown (A-1 … A-5).

There is no open fail.

Tests written for this review: `tests/Feature/Security/IdorSweepTest.php`, `tests/Feature/Security/MassAssignmentTest.php`, `tests/Feature/Security/ErrorDisclosureTest.php`, plus new cases in `SecurityHeadersTest.php`, `Auth/AuthTest.php`, `Services/WordVisitReportTest.php`, `Services/PdfVisitReportTest.php` and the frontend `auth-pages.test.jsx`.

## Fixed during the review

| Item | What was wrong | Fix |
| --- | --- | --- |
| 5.3.1, 5.3.10 | The Word visit file (T10-04) wrote the doctor's text into the XML without escaping. A `&` in the work done ("Scaling & polishing") made a corrupt file, and `</w:t>…` could add its own XML. | `Settings::setOutputEscapingEnabled(true)` in `WordVisitReport`; test with `&`, `<`, quotes and an injection attempt. |
| 2.1.2 | No upper limit on the register password. | `max:128` in `RegisterRequest`; a 64-character password is accepted, 129 is refused. |
| 2.1.12 | The password could not be shown. | `PasswordField` with a Show / Hide button on Login and Register. |
| 14.4.2, 12.3.4 | API responses had no `Content-Disposition`. | `SecurityHeaders` adds `attachment; filename="api.json"` to every `api/*` response that has none (the visit file keeps its own). |
| 14.4.3 | API responses had no CSP. | `SecurityHeaders` adds `default-src 'none'; frame-ancestors 'none'` to every `api/*` response. |

## V1 Architecture

ASVS 4.0.3 has no Level 1 items in V1.

## V2 Authentication

| Item | Result | Reason |
| --- | --- | --- |
| 2.1.1 Passwords of at least 12 characters | Accepted (A-1) | Minimum is 8 (`Password::min(8)`), the NIST SP 800-63B floor; login is rate-limited to 5/min per login and IP. |
| 2.1.2 At least 64 characters allowed, more than 128 refused | Pass (fixed) | `max:128` on register; test in `AuthTest`. Other accounts get a generated password. |
| 2.1.3 No truncation | Accepted (A-2) | bcrypt (plan §9.2) uses only the first 72 bytes; an ASCII password is cut only past 72 characters, an Arabic one past about 36. |
| 2.1.4 Any printable Unicode character allowed | Pass | `string` rule only, no character-set limit. |
| 2.1.5 Users can change their password | Accepted (A-3) | No change-password page in v1; the doctor resets an assistant's password (FR-I.1). |
| 2.1.6 Change needs the current and the new password | N/A | No change-password feature (A-3). |
| 2.1.7 Breached passwords refused | Accepted (A-1) | `Password::uncompromised()` would call the Have I Been Pwned API on every register; not chosen for v1. |
| 2.1.8 Password strength meter | Accepted (A-1) | Not in v1; the register hint states the minimum. |
| 2.1.9 No composition rules | Pass | Length is the only rule. |
| 2.1.10 No forced periodic change | Pass | None. |
| 2.1.11 Paste and password managers allowed | Pass | Plain inputs with `autocomplete="current-password"` / `"new-password"`. |
| 2.1.12 The password can be viewed | Pass (fixed) | `PasswordField` Show / Hide button; test in `auth-pages.test.jsx`. |
| 2.2.1 Anti-automation against credential stuffing and brute force | Pass | `throttle:login` 5/min per login + IP, `throttle:register` 5/min per IP (T11-02, `RateLimitsTest`). |
| 2.2.2 Weak authenticators (SMS, email) only as a second factor | N/A | No SMS or email authentication. |
| 2.2.3 Notification after changes to authentication details | Accepted (A-3) | v1 sends no SMS or email at all. |
| 2.3.1 Initial passwords random, short-lived, not kept | Accepted (A-3) | `InitialPassword` uses `random_int` over 31 characters, 8 long (about 40 bits), shown once; there is no forced change at first login. |
| 2.5.1 Activation or recovery secret not sent in clear text | Pass | The initial password is shown once on the doctor's or the assistant's screen and handed over in person; nothing is emailed or texted. |
| 2.5.2 No password hints or secret questions | Pass | None. |
| 2.5.3 Recovery does not reveal the current password | Pass | A reset generates a new password (`StaffController::update`). |
| 2.5.4 No shared or default accounts | Pass | The doctor account comes from `DOCTOR_PHONE` / `DOCTOR_PASSWORD`; `DoctorSeeder` stops when either is empty. |
| 2.5.5 Notification when an authentication factor changes | Accepted (A-3) | As 2.2.3. |
| 2.5.6 Forgotten password uses a secure recovery path | Pass | No self-service recovery; the doctor resets in person, which revokes every token of that account. |
| 2.7.1 – 2.7.4 Out-of-band verifiers | N/A | None. |
| 2.8.1 Time-based OTP lifetime | N/A | None. |

## V3 Session management

| Item | Result | Reason |
| --- | --- | --- |
| 3.1.1 Session token never in the URL | Pass | Sanctum token sent only in the `Authorization` header. |
| 3.2.1 New token at login | Pass | `createToken()` on every register / login. |
| 3.2.2 Token has at least 64 bits of entropy | Pass | Sanctum: 40 random characters (plus a CRC), stored hashed (SHA-256). |
| 3.2.3 Token stored in the browser safely | Accepted (A-4) | The token is in `localStorage` (`clinic.token`), where any XSS could read it. See "Token storage" below. |
| 3.3.1 Logout invalidates the token | Pass | `AuthController::logout` deletes the current token; the SPA clears the token and the query cache (`AuthContext`). |
| 3.3.2 Re-authentication at least every 30 days | Accepted (§11.3 "Session and token hardening") | `sanctum.expiration = null`: tokens do not expire. Already deferred in the plan. |
| 3.4.1 – 3.4.5 Cookie attributes | N/A | The API is stateless (bearer tokens, no session cookie; `supports_credentials = false`). |
| 3.7.1 Re-authentication before sensitive account changes | Accepted (A-3) | A patient can change their phone (their login name) without typing the password. |

## V4 Access control

| Item | Result | Reason |
| --- | --- | --- |
| 4.1.1 Rules enforced on the server | Pass | `role:` middleware on every group, Policies, ownership checks in controllers; the SPA guards are a convenience only. |
| 4.1.2 Users cannot change attributes used for access control | Pass | Every write passes only `validated()` / `safe()->only()`; `MassAssignmentTest` sends `role`, `is_active`, `doctor_id`, `patient_id`, `user_id`, `recorded_by`, `total_amount`, `password`, `id` and timestamps to all 27 write routes and checks none is stored. |
| 4.1.3 Least privilege | Pass | Three roles (§2); the assistant never sees medical data (`AccessAndPrivacyTest`); patients have no route with another record's id except their own cancel. |
| 4.1.5 Access control fails securely | Pass | The role is checked before route-model binding (403 before 404, no id probing); any exception ends as a 4xx / 500 without data. |
| 4.2.1 Protection against IDOR | Pass | `IdorSweepTest`: every route with an id (23) is listed; a patient gets 403 on all of them for another patient's record; the doctor and the assistant get 403 outside their area and 404 for another doctor's appointment or block, a non-assistant on `/staff/{id}` and a history entry under the wrong patient; nothing changes in the database. |
| 4.2.2 CSRF protection | N/A | Bearer tokens, not cookies; CORS allows only `FRONTEND_URL` without credentials. |
| 4.3.1 Admin interfaces use MFA | Accepted (A-5) | The doctor's area has no second factor in v1. |
| 4.3.2 No directory listing or metadata (`.git`, `.DS_Store`) served | Deploy | Nginx roots are `backend/public` and `frontend/dist` only, `autoindex off`, dotfiles denied (T7-05). |

## V5 Validation, sanitization and encoding

| Item | Result | Reason |
| --- | --- | --- |
| 5.1.1 HTTP parameter pollution | Pass | Every input goes through a Form Request with types; duplicate keys cannot change the type. |
| 5.1.2 Mass assignment | Pass | As 4.1.2. |
| 5.1.3 Allow-list input validation | Pass | One Form Request per write (plan §6.2): types, enums, lengths, date formats. |
| 5.1.4 Structured data strongly typed | Pass | Enums (`status`, `type`, `method`, `category`), `integer`, `date_format`, money rules. |
| 5.1.5 Redirects only to allowed destinations | N/A | No redirect takes a URL from input; after login the SPA returns to a route kept in router state (`ProtectedRoute` / `GuestRoute`). |
| 5.2.1 HTML from WYSIWYG sanitized | N/A | No rich text; every text is plain. |
| 5.2.2 Unstructured data sanitized | Pass | Length limits on every text; output is escaped (5.3). |
| 5.2.3 SMTP / IMAP injection | N/A | The app sends no email. |
| 5.2.4 No `eval()` or dynamic code | Pass | None in the backend or the SPA (also ruled out by the CSP: no `unsafe-eval`). |
| 5.2.5 Template injection | Pass | Blade templates are fixed files; user text only goes through `{{ }}`. |
| 5.2.6 SSRF | N/A | The server never fetches a URL from input. |
| 5.2.7 Scriptable SVG | N/A | No uploads. |
| 5.2.8 Expression language / Markdown injection | N/A | None used. |
| 5.3.1 Output encoding for the context | Pass (fixed) | React escapes; Blade escapes the PDF; the Word file now escapes XML (see "Fixed"). |
| 5.3.2 Encoding keeps the character set | Pass | UTF-8 end to end (JSON, `utf8mb4`, mPDF, PHPWord). |
| 5.3.3 Context-aware escaping against XSS | Pass | No `dangerouslySetInnerHTML`; PDF test prints `<b>` as text. |
| 5.3.4 Parameterized queries | Pass | Eloquent / query builder; the raw fragments (`orderByRaw`, `whereRaw`, `selectRaw`, `havingRaw`) contain no input or use bindings. |
| 5.3.5 Escaping where parameters are not enough | Pass | Patient and drug searches escape `%`, `_` and `\` before `LIKE` (`Patient::scopeForList`, `Drug::scopeMatching`). |
| 5.3.6 JSON injection | Pass | `response()->json()` / API Resources; the SPA never `eval`s JSON. |
| 5.3.7 LDAP injection | N/A | No LDAP. |
| 5.3.8 OS command injection | N/A | No shell calls. |
| 5.3.9 Local / remote file inclusion | N/A | No path is built from input; export file names are built on the server. |
| 5.3.10 XPath / XML injection | Pass (fixed) | The only XML written is the Word file, now escaped. |
| 5.5.2 XML parsers restrict external entities | N/A | The app parses no XML from users. |
| 5.5.3 No deserialization of untrusted data | Pass | JSON only; no `unserialize()` of input. |
| 5.5.4 `JSON.parse` in the browser | Pass | Axios parses JSON; no `eval`. |

## V6 Stored cryptography

| Item | Result | Reason |
| --- | --- | --- |
| 6.2.1 Crypto modules fail securely, no padding oracle | Pass | Laravel's encrypter (AES-256-CBC + HMAC-SHA256, checked before decrypting) for the medical text (T11-06); a wrong MAC throws and becomes a 500 without detail. |

## V7 Error handling and logging

| Item | Result | Reason |
| --- | --- | --- |
| 7.1.1 No credentials or tokens in logs | Pass | Laravel does not log request bodies; tokens are stored hashed; the audit log holds field names only (T11-04). |
| 7.1.2 No other sensitive data in logs | Pass | The audit log records **names** of changed fields, never values (NFR-S.4). |
| 7.4.1 Generic message on an unexpected error | Pass | `ErrorDisclosureTest`: with `APP_DEBUG=false` a failed query or any exception returns exactly `{ "message": "Server Error" }`, without SQL, file or trace; `config/app.php` defaults `debug` to false. |

## V8 Data protection

| Item | Result | Reason |
| --- | --- | --- |
| 8.2.1 Anti-caching headers on sensitive data | Pass | `Cache-Control: no-store, private` and `Pragma: no-cache` on every authenticated response (T11-01). |
| 8.2.2 No sensitive data in browser storage | Pass | `localStorage` holds the token (3.2.3, A-4), the language and the visit-file format choice; no medical or money data. |
| 8.2.3 Client data cleared at logout | Pass | `AuthContext.logout` removes the token and clears the React Query cache. |
| 8.3.1 Sensitive data in the body or headers, not the query string | Pass | Passwords, medical text and money go in JSON bodies. Note: the patient search term (a name or phone) is in the query string, so access-log retention is set in T7-05. |
| 8.3.2 Users can export or remove their data | Accepted (A-5) | No self-service export or deletion; a clinic has to keep its medical records, so deletion needs a retention rule agreed with the client; the doctor can already export a visit file (FR-K). |
| 8.3.3 Clear notice about the data collected | Accepted (A-5) | No privacy notice on Register yet; to be written with the client before launch. |
| 8.3.4 Sensitive data identified and handled by policy | Pass | Plan §11 lists the medical fields (encrypted at rest, NFR-S.6), the audited reads and writes (NFR-S.4) and who may see what (§2). |

## V9 Communication

| Item | Result | Reason |
| --- | --- | --- |
| 9.1.1 TLS for all client connections, no plain-text fallback | Deploy | HTTPS with Let's Encrypt and an HTTP → HTTPS redirect (T7-05); HSTS sent by the API outside local / testing (T11-01) and by Nginx for the SPA. |
| 9.1.2 Only strong cipher suites | Deploy | Mozilla "intermediate" cipher list (T7-05). |
| 9.1.3 Only TLS 1.2 and 1.3 | Deploy | `ssl_protocols TLSv1.2 TLSv1.3;` (T7-05). |

## V10 Malicious code

| Item | Result | Reason |
| --- | --- | --- |
| 10.3.1 Auto-update over a secure channel | N/A | No auto-update. |
| 10.3.2 Integrity of third-party code | Pass | All JS is bundled from npm with a lockfile; no CDN scripts. Google Fonts CSS cannot carry SRI; the CSP limits styles and fonts to Google's two hosts. |
| 10.3.3 Protection from subdomain takeover | Deploy | One domain; no dangling DNS records (T7-05). |

## V11 Business logic

| Item | Result | Reason |
| --- | --- | --- |
| 11.1.1 Steps in order only | Pass | Appointment status lifecycle §4.3 (`canTransitionTo`), checked under a row lock. |
| 11.1.2 Steps at human speed only | Pass | Rate limits on every write (60/min) and search (120/min) (T11-02). |
| 11.1.3 Business limits enforced | Pass | One active appointment per patient, booking window, cancel cut-off, payment ≤ remaining (PR-1 … PR-3), 1–15 prescription lines. |
| 11.1.4 Anti-automation for expensive actions | Pass | As 11.1.2; register 5/min per IP. |
| 11.1.5 Limits against known business risks | Pass | Double booking blocked by the unique `(doctor_id, active_slot)` index; payments locked per visit. |

## V12 Files and resources

| Item | Result | Reason |
| --- | --- | --- |
| 12.1.1 Large files refused | N/A | No uploads. Request size is capped by Nginx `client_max_body_size 1m` (T7-05). |
| 12.3.1 User file names not used for paths | N/A | No uploads; export names are built on the server. |
| 12.3.2 File name metadata validated | N/A | As 12.3.1. |
| 12.3.3 No remote file inclusion | N/A | As 5.2.6. |
| 12.3.4 Reflective file download | Pass (fixed) | Fixed `Content-Disposition` on every API response (14.4.2); JSON is served as `application/json`. |
| 12.3.5 No OS commands from file names | N/A | No shell calls. |
| 12.4.1 Files stored outside the web root | N/A | Nothing is stored; visit files are built in memory per request (mPDF's temp folder is `storage/`). |
| 12.4.2 Files scanned for malware | N/A | No uploads. |
| 12.5.1 Web server serves only expected file types | Deploy | Nginx serves `frontend/dist` and sends everything else to `public/index.php` (T7-05). |
| 12.5.2 Uploads never served as HTML | N/A | No uploads. |
| 12.6.1 SSRF allow-list | N/A | As 5.2.6. |

## V13 API and web service

| Item | Result | Reason |
| --- | --- | --- |
| 13.1.1 Same encoding and parsers everywhere | Pass | JSON in UTF-8 between the SPA and the API. |
| 13.1.3 No keys or tokens in API URLs | Pass | Tokens only in the `Authorization` header. |
| 13.2.1 Only allowed HTTP methods per route | Pass | Laravel answers other methods with 405; `role:` middleware per group. |
| 13.2.2 JSON validated against a schema | Pass | Form Requests on every write. |
| 13.2.3 CSRF for cookie-based REST | N/A | No cookies (4.2.2). |
| 13.3.1 XSD validation for SOAP | N/A | No SOAP. |

## V14 Configuration

| Item | Result | Reason |
| --- | --- | --- |
| 14.2.1 Components up to date | Pass | `composer audit` and `npm audit --omit=dev --audit-level=high` clean; Dependabot weekly (T11-03). |
| 14.2.2 Unneeded features removed | Pass | Only the API and the default welcome page; no sample routes, no Telescope / debug bar in production. |
| 14.2.3 SRI for third-party assets | Pass | As 10.3.2. |
| 14.3.2 Debug mode off in production | Pass | `ErrorDisclosureTest`; `.env` for staging / production sets `APP_DEBUG=false` (T7-05). |
| 14.3.3 Headers do not reveal versions | Deploy | `expose_php = Off`, `server_tokens off;` (T7-05, from T11-01). |
| 14.4.1 Content-Type with a safe charset | Pass | JSON responses are `application/json`; the SPA's HTML has `<meta charset="UTF-8">`. |
| 14.4.2 API responses have `Content-Disposition: attachment` | Pass (fixed) | `SecurityHeaders`; test in `SecurityHeadersTest`. |
| 14.4.3 Content Security Policy | Pass (fixed) | SPA: Nginx CSP without `unsafe-eval` (T11-01). API: `default-src 'none'; frame-ancestors 'none'`. |
| 14.4.4 `X-Content-Type-Options: nosniff` | Pass | T11-01. |
| 14.4.5 HSTS | Pass | T11-01, outside local / testing. |
| 14.4.6 Referrer-Policy | Pass | `no-referrer` (T11-01). |
| 14.4.7 Framing blocked | Pass | `X-Frame-Options: DENY` and `frame-ancestors 'none'`. |
| 14.5.1 Only needed HTTP methods | Pass | As 13.2.1. |
| 14.5.2 `Origin` not used for access control | Pass | Access is decided by the token and the role only. |
| 14.5.3 CORS allow-list | Pass | `allowed_origins` = `FRONTEND_URL` only, `supports_credentials = false`. |

## Token storage (3.2.3, A-4)

The SPA keeps the Sanctum token in `localStorage` (`frontend/src/api/client.js`). Any script that runs on the page can read it, so a single XSS bug would let an attacker copy the token and use the API as that user (for the doctor: every patient's medical record) from anywhere until the user logs out, because tokens do not expire (§11.3).

Why it is accepted for now, and what limits the risk:

1. **The CSP is the main control** (T11-01): `script-src 'self'` without `unsafe-inline` or `unsafe-eval`, so injected markup cannot run script, and `connect-src 'self'` stops a script that did run from sending the token to another origin with `fetch` / XHR.
2. React escapes all text; the code has no `dangerouslySetInnerHTML`, no `eval` and no inline scripts (checked here, 5.2.4 / 5.3.3).
3. No third-party script runs on the page (10.3.2).
4. Logout deletes the token on the server and in the browser.

The fix that removes the risk is Sanctum's cookie (SPA) authentication: an `HttpOnly`, `Secure`, `SameSite` session cookie that scripts cannot read, plus CSRF protection. It changes login, the API client and CORS, so it belongs with "Session and token hardening" in §11.3.
