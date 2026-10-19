# Task 10: Security review

Date: 2026-10-19 +03:00.
Client and model: Claude Code (VS Code extension) / Claude Opus 5.5 (`claude-opus-5-5`).
Branch: `feature/chef-redesign`. Base commit: `3d997a0b296d7ec59bbd95b7d1a94509aef48a81` plus recorded worktree changes.
Result: PASS. No unresolved critical or high-impact exploitable finding remains.
This review lowers known risk. It does not prove complete security.

## Scope and method

- Manual source review of `hvk/admin/*.php`, `hvk/admin/api/*.php`, `database/*.php`, `index.html`, and Apache rules.
- Trace of form, query, upload, and JSON input to SQL, HTML, files, and redirects.
- Semgrep CE 1.180.0 with `p/php`, `p/security-audit`, and `p/secrets` (91 rules, 37 files).
- Local HTTP suites against an isolated MySQL 8.4.3 container (`chef-task05-mysql`, port 33306).
- Headless Chrome comparison of the guest page with and without the new policy.
- Laragon Apache 2.4.68 response-header check on a disposable package copy.

## Findings

| ID | Severity | Status | Summary |
|---|---|---|---|
| SEC-10-01 | Medium | Fixed | No CSP, clickjacking, or referrer headers on admin, API, or guest responses. |
| SEC-10-02 | Medium | Fixed | Media hardening policy in PHP differed from the shipped `media/.htaccess`. |
| SEC-10-03 | Low | Fixed | Feedback API accepted loopback and opaque `null` origins in production. |
| SEC-10-04 | Low | Fixed | Feedback pagination links repeated arbitrary query keys without HTML escaping. |
| SEC-10-05 | Low | Fixed | The public package served `database/lib.php` from the document root. |
| SEC-10-06 | Low | Accepted | Feedback rate limit fails open when its database write fails. |
| SEC-10-07 | Info | Accepted | `npm audit` reports build-time issues under `tailwindcss@3.4.17`. |
| SEC-10-08 | Info | Recorded | Laragon serves the full repository root locally. |

### SEC-10-01: Missing browser security headers

- Evidence: a repository search found no `Content-Security-Policy`, `X-Frame-Options`, or `Referrer-Policy`.
- Impact: another site could frame admin pages and trick a signed-in owner into clicks. CSRF tokens do not stop framing.
- Fix: `chef_send_security_headers()` in `hvk/admin/security.php` runs first in `chef_bootstrap()`.
- Admin policy: `default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; connect-src 'self'; font-src 'self'; manifest-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'`.
- Admin and API responses also send `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy: same-origin`, and `Cross-Origin-Opener-Policy: same-origin`.
- Production HTTPS responses add `Strict-Transport-Security: max-age=31536000`.
- Guest fix: new `public_html/.htaccess`. It hashes the single inline script and style. It allows no `unsafe-*` source.
- Verification: `security-headers-static.mjs` and `security-headers-http.mjs` PASS. Apache check PASS. Browser check PASS.
- Remaining exposure: Hostinger LiteSpeed must apply the same `.htaccess` headers. HSTS needs real HTTPS.

### SEC-10-02: Media hardening policy mismatch

- Evidence: PHP policy SHA-256 `35c0a7d9…` differed from `hvk/media/.htaccess` SHA-256 `34e58516…`.
- Impact: `chef_media_prepare_storage()` rejects the shipped file, so media setup fails on release.
- The embedded rule was also weaker. It did not deny double extensions such as `name.php.webp`.
- Fix: `CHEF_MEDIA_HARDENING_POLICY` in `hvk/admin/media-service.php` now equals the shipped file byte for byte.
- Verification: static equality check PASS. `setup-auth-test.php` ran `chef_media_prepare_storage()` with the new policy. Apache returned 403 for `media/probe.php.webp`.
- Note: the disposable Task 05 test root received the new policy file so its setup keeps working.

### SEC-10-03: Feedback origin control

- Evidence: `api/feedback.php` accepted `localhost` and `127.0.0.1` origins in every environment. `Origin: null` passed.
- Impact: low. The endpoint is anonymous and throttled. The gap allowed cross-origin spam from sandboxed or local pages.
- Fix: loopback origins pass only outside production. Empty or opaque hosts return 403.
- Verification: `security-headers-http.mjs` checks `null`, foreign, and production loopback origins. Feedback suite PASS.

### SEC-10-04: Pagination query reflection

- Evidence: `feedbacks.php` echoed `http_build_query(array_merge($_GET, …))` into `href` without `sanitize()`.
- Impact: low. URL encoding blocked markup injection, but arbitrary keys were reflected.
- Fix: links use only `topic`, `rating`, `search`, and `page`, escaped with `sanitize()`. Filters reject array input.
- Verification: PHP lint PASS. Feedback suite filtering checks PASS.

### SEC-10-05: Database library under the public root

- Evidence: `dist/hostinger-staging/public_html.zip` contains `database/lib.php`.
- Impact: low. The file defines functions only and prints nothing.
- Fix: `RedirectMatch 404 "(?i)(?:^|/)database/"` in `public_html/.htaccess`.
- Verification: Apache returned 404 for `/database/lib.php` while the file existed.

### SEC-10-06: Feedback throttle fails open (accepted)

- `chef_feedback_throttle_check()` returns `null` after a database error. The insert then fails on the same outage.
- Residual risk is low. No change was made.

### SEC-10-07: Build dependencies (accepted)

- `npm audit`: 7 issues (5 high, 2 moderate) in `braces`, `micromatch`, `chokidar`, `fast-glob`, `postcss-nested`, and `postcss-selector-parser`.
- All come from `tailwindcss@3.4.17`. It runs only for the local admin CSS build. Nothing ships to the server.
- The only offered fix is Tailwind 4, a major migration. The task forbids that migration for an audit summary.
- `http-server@14.1.1` has no reported issue. No dependency was changed.

### SEC-10-08: Local repository exposure (recorded)

- Laragon serves `C:\laragon\www\alacart`, a junction to the repository root, including `.git` and docs.
- This applies only to the local machine. The release package excludes those paths.

## Checked controls without findings

| Control | Result | Evidence |
|---|---|---|
| SQL binding | PASS | All user values use prepared parameters. Interpolated table names come from fixed maps. |
| Mass assignment | PASS | Forms read fixed field lists. Imports reject unknown and nested JSON keys. |
| Stored and reflected XSS | PASS | Output uses `sanitize()` or integer casts. Admin JS and guest JS use `textContent`. Feedback XSS fixture PASS. |
| Guest icon `innerHTML` | PASS | `C()` returns fixed SVG markup from a lookup. API values never reach `innerHTML`. |
| Login throttle, session, logout, expiry | PASS | Auth suite PASS, including TLS proxy, idle and absolute expiry, and auth version revocation. |
| Recovery | PASS | `recover-owner.php` runs only in CLI and consumes a private file. |
| CSRF and authorization | PASS | Route inventory: 12 protected routes. Every POST checks CSRF and a one-time submission token. |
| Record ownership | PASS | Reorder checks exact ID sets. `private-image.php` joins the active upload to its feedback row. |
| Uploads | PASS | Media suite: 4 valid formats, 7 rejected files, re-encoding to WebP, random names, path containment. |
| Cache separation | PASS | Menu API `public, no-cache` with ETag. Admin and private image `no-store`. |
| Error disclosure | PASS | Apache without private config returned a generic 503 with a request ID. |
| Credentials | PASS | 5 runtime secret values searched in 411 files: 0 hits. No credential file is tracked or in `public_html`. |
| External fetches | N/A | No server-side outbound request exists. No translation service exists. |
| Redirects | PASS | All `Location` targets are fixed admin pages with integer IDs. |
| JWT confusion | N/A | The application does not use JWT. None was added. |

The public WiFi password in the menu API is intended guest content.

## Semgrep

- Pre-fix and post-fix scans: 12 `echoed-request` results, 0 errors.
- All 12 values pass through `sanitize()`, integer casts, or `$pageQuery()`. They are false positives.
- The local `semgrep.exe` and `semgrep-core.exe` are blocked by Windows App Control. The scan ran in `semgrep/semgrep:1.180.0` under Docker.

## Test results

| Check | Result |
|---|---|
| `npm test` (guest static, security static, syntax) | PASS |
| `run-http-tests.mjs`: security headers, auth with TLS proxy, media, catalog unit, catalog HTTP | PASS |
| `run-menu-http-tests.mjs` on a fresh seed | PASS |
| `run-feedback-http-tests.mjs` | PASS |
| Guest browser with CSP, reduced and full motion: 24 cards, product and feedback dialogs, 0 violations, 0 errors | PASS |
| Guest screenshots with and without CSP are byte-identical (home and product dialog) | PASS |
| Laragon Apache headers on a disposable package copy | PASS |
| PHP lint of changed files | PASS |
| Guest hashes equal `current_revision` (`456b7bea…`, preview `afa9d953…`) | PASS |

A first menu-suite run failed with `Seed category count changed. 5 !== 4`. It reused the catalog suite database. The rerun after a fresh seed passed.

## Changed files

- `hvk/admin/security.php`: header function, admin CSP, production HSTS.
- `hvk/admin/media-service.php`: `CHEF_MEDIA_HARDENING_POLICY`.
- `hvk/admin/api/feedback.php`: origin rule.
- `hvk/admin/feedbacks.php`: scalar filters and escaped pagination.
- `public_html/.htaccess`: new guest headers and database rule.
- `database/tests/security-headers-static.mjs`, `database/tests/security-headers-http.mjs`: new regression tests.
- `database/tests/run-http-tests.mjs`, `package.json`: run the new tests.

No guest HTML, CSS, color value, or design token changed.

## Deployment prerequisites

- Task 12 must ship `public_html/.htaccess` at the document root.
- Verify CSP, `X-Frame-Options`, `RedirectMatch`, and HSTS under Hostinger LiteSpeed over HTTPS.
- Recompute guest CSP hashes after any guest HTML change. `npm test` fails when they are stale.
- Verify LiteSpeed denies `media/*.php.webp` execution.
