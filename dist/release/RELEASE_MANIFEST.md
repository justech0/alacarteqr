# Chef release manifest

Status: LOCAL RELEASE CANDIDATE. Not deployed. No file was uploaded to any server.
Date: 2026-10-19 (Europe/Istanbul).
Source: branch `feature/chef-redesign`, commit `3d997a0b296d7ec59bbd95b7d1a94509aef48a81` plus the worktree changes recorded in PROJECT.md.
Per-file source hashes: `dist/release/build/build-info.json` (git-ignored build output, regenerate with `deploy/build-release.ps1`).
Schema version: `2026101501`.

## Artifacts

| File | SHA-256 | Bytes | Entries |
|---|---|---|---|
| `public_html.zip` | `8464878737d01fa6b5292a6a2c7cfecfe2e401c047dff7c5efe477aa2a04bfd5` | 121714 | 30 |
| `chef-private-setup.zip` | `89afe469567dde69ca24728a416781537f3cd16312cd9722a916fb9cbc409d3b` | 40348 | 14 |
| `INSTALL_TR.md` | `dec52a1edc3fccfdb1bd082d06e098e4da867ecad7d347d4e287c9944072fd2d` | 10195 | - |
| `sql/chef-install-2026101501.sql` (inside the private archive) | `3168a659a4b02553930dc947c54d267b7633c5904af6167c78719aa33a753d87` | 19839 | - |

The build is reproducible. Two consecutive builds from the same inputs gave identical archive hashes.
Entries are sorted, use a fixed timestamp (2026-10-19T00:00:00Z), and carry Unix mode 0644.

## Build procedure

```powershell
pwsh -NoProfile -File .\deploy\export-install-sql.ps1   # disposable MariaDB 11.8.9 container
pwsh -NoProfile -File .\deploy\build-release.ps1        # guest build, admin CSS build, archives, scans
node .\deploy\test-release.mjs                          # fresh installation test, variants A and B
```

Build tools: Node.js v26.8.1, npm `package-lock.json`, Tailwind CSS 3.4.17, PHP 8.3.33, Docker 29.8.2.
Hostinger needs no Node.js. All assets are prebuilt.

## Contents of public_html.zip

Extract into the public document root.

- `index.html`: guest menu. SHA-256 `456b7bea550af767624494ebe0f2a168c10bed79bdef26115800b19886af3a8e` = `current_revision` (rev1-feedback). Result: PASS.
- `.htaccess`: no listing, guest CSP and headers, 404 for `database/`.
- `media/.htaccess`: denies script execution and double extensions.
- `database/lib.php`: required by `hvk/admin/security.php`. Apache returns 404 for it.
- `hvk/admin/*.php` (19 files), `hvk/admin/api/menu.php`, `hvk/admin/api/feedback.php`, `hvk/admin/.htaccess`.
- `hvk/admin/assets/admin.css`, `admin.js`, `chef-logo.svg`, `THIRD_PARTY_NOTICES.txt`.

## Contents of chef-private-setup.zip

Extract outside the public document root, for example `/home/<account>/chef-private/`.

- `config.production.example.php`: placeholders only. No real secret.
- `INSTALL_TR.md`, `THIRD_PARTY_NOTICES.txt`.
- `database/cli.php`, `lib.php`, `media-cli.php`, `recover-owner.php`, `seed-data.php`, `migrations/*.php` (3 files).
- `hvk/admin/security.php`, `hvk/admin/media-service.php`: CLI dependencies. Identical to the public copies.
- `sql/chef-install-2026101501.sql`: phpMyAdmin installation file. Contains schema, Chef seed, `meta`, and `schema_migrations` rows. Contains no administrator, feedback, upload, or throttle rows. Contains no `DROP TABLE`.

## Excluded path groups

| Group | Paths |
|---|---|
| Tests and test data | `database/tests/`, `scratch/`, `*.credentials`, `test-admin*`, `*.har`, fixtures |
| Reports and evidence | `docs/`, `docs/evidence/`, `docs/reviews/`, `dist/hostinger-staging/` |
| Workflow and agent files | `chef-workflow/`, `.agents/`, `.impeccable/`, `.vscode/`, `AGENTS.md`, `PROJECT.md`, `PROJECT_STATE.md` |
| Repository metadata | `.git/`, `.gitignore`, `.local-sourcemap.json` |
| Build tooling | `node_modules/`, `package.json`, `package-lock.json`, `tailwind.admin.config.js`, `hvk/admin/assets/admin-tailwind.css`, `deploy/` scripts |
| Legacy HVK files | `hvk/assets/`, `hvk/components/`, `hvk/utils/`, `hvk/index.html`, `hvk/admin/uploads/`, `hvk/logo.jpeg`, `hvk/thk.png` |
| Preview-era files | `chef-onizleme.html`, root `app.js`, `style.css`, `menu-data.js`, `menuler/`, `cheflogo.pdf`, root `chef-logo.*` |
| Unreferenced public copies | `public_html/app.js`, `public_html/style.css`, `public_html/chef-logo.jpg`, `.png`, `.svg` (index.html inlines its script, styles, and logo) |
| Private and source data | `../Alacart.zip`, `../hvk.zip`, `../u220042353_havakuvvetleri.sql`, `../QR-menu-raporu.pdf`, `database/config.example.php`, any `config.php` |

No legacy file is kept. A fresh Chef installation needs no legacy upload. `media-cli.php migrate-legacy` is not part of this release.

## Runtime requirements

- PHP 8.3 (tested 8.3.33) with `pdo_mysql`, `fileinfo`, `mbstring`, `gd` with WebP, `ctype`, `json`.
- `upload_max_filesize` and `post_max_size` at least `6M`. `display_errors=0`.
- MariaDB 10.6+ or MySQL 8.0+ with InnoDB. Tested: MySQL 8.4.3 and MariaDB 11.8.9.
- Collation decision: only `utf8mb4_unicode_ci`, `utf8mb4_turkish_ci`, and `ascii_bin`. The build fails on `uca1400` or `0900` tokens.
- Apache or LiteSpeed with `.htaccess`, `mod_env` (`SetEnv`), `mod_headers`, `mod_alias`.
- HTTPS is mandatory. Production configuration refuses admin sessions over HTTP.
- Environment: `CHEF_CONFIG_FILE` for web requests. `CHEF_DB_CONFIG`, `CHEF_CONFIG_FILE`, and `DOCUMENT_ROOT` for CLI tools.
- Base path: set `<BASE_PATH>` in `security.session.path` and `media.public_base_url`. Guest and admin use relative URLs.
- Scheduled task: `media-cli.php cleanup` every 5 minutes.

## Test evidence

Fresh installation: `node deploy/test-release.mjs` returned 40/40 PASS.
Details: `docs/reviews/12-release.md`. Raw results: `dist/release/build/fresh-install-results.json` (SHA-256 `6046d77cacd25c2090e9dd014de2b95ee8aac0ccd14b58494efeccb1c4ba41e6`).

| Check | A: MySQL 8.4.3, CLI | B: MariaDB 11.8.9, SQL file |
|---|---|---|
| Extract archives into new directories | PASS | PASS |
| Install schema and seed | PASS (migrate, seed, verify) | PASS (import, verify, migrate = no pending) |
| Repeat install refused | PASS (second `--create` exit 1) | PASS (second import fails, no data loss) |
| First owner | PASS (`--create`) | PASS (manual insert) |
| Guest index hash and CSP | PASS | PASS |
| Guest menu API: 4 categories, 24 products | PASS | PASS |
| Admin login over HTTPS, Secure and HttpOnly cookie | PASS | PASS |
| Catalog price edit visible in menu, version bump | PASS | PASS |
| Public media upload served as `image/webp` | PASS | PASS |
| Feedback with image stored privately | PASS | PASS |
| Private image: anonymous 302, owner 200 | PASS | PASS |
| Feedback deletion removes files, image 404 | PASS | PASS |
| 13 private paths denied (403/404) | PASS | PASS |
| `.php`, `.phtml`, `.phar`, `.php.webp` in media denied | PASS | PASS |
| Logout ends session | PASS | PASS |
| Log outside public root, no secret | PASS | PASS |

Other release gates:

| Check | Result |
|---|---|
| `npm test` (guest static, CSP hashes) | PASS |
| PHP lint, all packaged PHP | PASS |
| Task 10 HTTP suites (headers, auth, media, catalog) on a disposable MySQL 8.4.3 | PASS 3 of 4 runs. One run failed `Concurrent account counter was not atomic`. Throttle code is unchanged |
| Semgrep 1.180.0 (`p/php`, `p/security-audit`, `p/secrets`), 7 changed files | PASS, 0 findings, 0 errors |
| Archive content scan (test markers, credential names, 3 test password values) | PASS |
| Git worktree secret scan | PASS, only scanner marker strings matched |
| Linux path rules (ASCII, `/`, no case collision) | PASS |

## Unresolved hosting checks

These checks need the Hostinger account. Status: NOT RUN.

- LiteSpeed passes `SetEnv CHEF_CONFIG_FILE` to PHP.
- LiteSpeed applies `media/.htaccess` and denies script execution.
- Public media directories created with mode 0700 are readable by the web server.
- The cron job runs `media-cli.php cleanup` every 5 minutes.
- Hostinger CDN bypasses `/hvk/admin/*`.
- The account MariaDB version is 10.6 or later.
- Hostinger CLI `php` matches PHP 8.3.
- Real phpMyAdmin import of the SQL file (variant B used the `mariadb` client).
- `recover-owner.php --hash-only` interactive prompt (not automatable on Windows).

## Remaining deployment action

The owner uploads both archives by File Manager or SSH and follows `INSTALL_TR.md`.
After the first install, the owner runs INSTALL_TR.md section 8 and the checks above.
The live deployment is not complete.
