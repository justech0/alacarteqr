# Task 12 release review

Status: PASS (local release artifacts). Live deployment: NOT RUN.
Date: 2026-10-19 (Europe/Istanbul).
Owner and actual model: Claude Code / Claude Opus 5.5 (`claude-opus-5-5`). PROJECT.md listed Codex. The user assigned this run.
Branch and commit: `feature/chef-redesign` at `3d997a0` plus worktree changes.
Manifest: `dist/release/RELEASE_MANIFEST.md`.

## Result

- `dist/release/public_html.zip`, `chef-private-setup.zip`, `INSTALL_TR.md`, and `RELEASE_MANIFEST.md` exist.
- A fresh installation from the archives passed 40 of 40 checks on two database engines.
- Nothing was uploaded. No hosted database or server was contacted.

## Parity confirmation

Evidence comes from earlier reviews and the Task 12 runs. See `docs/FUNCTION_PARITY.md`.

| Function | Status | Evidence |
|---|---|---|
| Product and category CRUD, order, images, category move | PASS | 06, 07, 09; catalog suite rerun in Task 12 |
| Bulk prices, reset, CSV export, CSV/JSON import | PASS | 07; catalog suite rerun in Task 12 |
| WiFi, campaigns, translations, search, cache invalidation | PASS | 07, 09; Task 12 campaign upload and version bump |
| Ingredient and calorie verification | PASS | 07, 09 |
| Login, logout, recovery, feedback inbox, private images | PASS | 03, 08, 09; Task 12 fresh install |
| Physical feedback image deletion | PASS | 08, 09; Task 12 fresh install (0 files remain, image 404) |
| First owner creation on a fresh install | FIXED in Task 12 | See finding REL-12-01 |

Admin translation form browser check remains PARTIAL. Guest TR, EN, and AR rendering passed in Task 09.

## Findings resolved before packaging

### REL-12-01: No first-owner path on a fresh install

- Evidence: only `database/tests/setup-auth-test.php` inserted an admin. `recover-owner.php` updates an existing admin only.
- Fix: `recover-owner.php --create` adds the first owner while `admins` is empty. It refuses when an admin exists.
- Fix: `recover-owner.php --hash-only` prints a `password_hash` value for a phpMyAdmin insert.
- Usage errors now print a safe message. Other errors keep the generic message.
- Verification: fresh install variant A. A second `--create` returned exit 1 and kept one admin row.

### REL-12-02: CLI tools ignored the DOCUMENT_ROOT environment value

- Evidence: PHP CLI sets `$_SERVER['DOCUMENT_ROOT']` to `""`. `chef_document_root()` then used the working directory.
- Impact: from the private directory, `recover-owner.php` rejected the private config as "inside the document root". `deploy/deploy-staging.ps1` step 11 depends on this path.
- Fix: in CLI only, an empty `$_SERVER['DOCUMENT_ROOT']` falls back to `getenv('DOCUMENT_ROOT')`, then to the application root. Web requests are unchanged.
- Verification: fresh install variant A. Task 10 HTTP suites rerun.

### REL-12-03: Private archive missed CLI dependencies

- Evidence: `media-cli.php` and `recover-owner.php` load `../hvk/admin/security.php` and `media-service.php`. The staging private archive did not contain them.
- Fix: the private archive ships both files. Both copies come from the same source file. `build-info.json` shows equal hashes.

### REL-12-04: Stale admin stylesheet

- Evidence: a rebuild of `admin.css` added 6 rules used by `feedbacks.php`, `products.php`, and `product-order.php` (`items-end`, `self-center`, `text-amber-500`, `bg-amber-500/10`, `border-amber-500/40`, `hover:underline`).
- Fix: the release build always runs `npm run build:admin-css`. A second rebuild gave the same hash.
- Browser check of these 6 rules: NOT RUN. Gemini owns visual checks.

### REL-12-05: Example config session path

- Evidence: `database/config.example.php` uses session path `/admin`. The admin lives under `/hvk/admin`.
- Fix: `deploy/release/config.production.example.php` uses `<BASE_PATH>/hvk/admin`. Fresh install verified the cookie path.

## Fresh installation test

Command: `node deploy/test-release.mjs`. Result: 40/40 PASS.

- Both archives were extracted with Windows `tar.exe` into new temporary directories.
- Each variant used a new Docker database container with random credentials and one application user.
- Configuration came from the shipped template. The SetEnv line was added to `.htaccess` as INSTALL_TR.md says.
- A separate Laragon Apache 2.4.68 instance served the extracted document root over HTTPS on `127.0.0.1:38443` with mod_php 8.3.33.
- Variant A (MySQL 8.4.3): INSTALL_TR.md section 6A, CLI migrate, seed, verify, media prepare, `--create`.
- Variant B (MariaDB 11.8.9): section 6B, SQL import, manual owner insert, manual media directories.
- The `mariadb` client import stands in for phpMyAdmin. A real phpMyAdmin import is NOT RUN.
- All containers and temporary directories were removed after the run.

Smoke checks per variant: guest index hash and CSP, menu API counts, HTTPS login and cookie flags, price edit and version bump,
campaign image upload and `image/webp` delivery, private feedback image storage, private image access control,
deletion with physical file removal, 13 denied paths, 4 denied script names in `media/`, logout, private log.

## Other checks

| Check | Result | Evidence |
|---|---|---|
| Guest hash vs `current_revision` | PASS | `456b7bea…3a8e` |
| `npm test` | PASS | Guest static and CSP hash checks |
| PHP lint of all packaged and changed PHP | PASS | Laragon PHP 8.3.33 |
| Task 10 runner (headers, auth incl. recovery, media, catalog) | PASS 3 of 4 runs | Port-shifted copy on disposable MySQL 8.4.3 at 33320 |
| Semgrep 1.180.0, 3 rule packs, 7 files | PASS | 0 findings, 0 errors |
| Release archive secret scan | PASS | 3 test password values and fixed markers absent |
| Git worktree secret scan | PASS | Only scanner marker strings in `deploy/build-release.ps1` |
| Reproducible build | PASS | Two builds, same archive hashes |
| Linux path and case rules | PASS | Build script gate |

The failed Task 10 run reported `Concurrent account counter was not atomic.` once. Runs 1, 3, and 4 passed.
The throttle code did not change. The cause is not confirmed. The check sends 12 parallel requests and is timing-sensitive.
Record: intermittent, not linked to a Task 12 change. Repeat on Hostinger staging.

Port 38082 was in use by two earlier `php -S` processes. Task 12 did not stop them.

## Not run

- All Hostinger checks. See `RELEASE_MANIFEST.md` "Unresolved hosting checks".
- Browser and visual checks. Gemini owns them.
- `recover-owner.php --hash-only` interactive input.
- `deploy/deploy-staging.ps1` still reads `dist/hostinger-staging/`. It was not changed or run.
