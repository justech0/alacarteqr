# Chef À La Carte project

Updated: 2026-10-19. Timezone: Europe/Istanbul.
Status: Task 12 PASS (local release artifacts). Live deployment is not done.
Current task: None. Next: manual Hostinger deployment by the owner. Last writer: Claude Code.
Entry point: Read this file before each session.

## Goal

Build a fast Chef À La Carte QR menu for phones.
Use the supplied reference for layout and interaction.
Keep the Chef logo and verified Chef menu data.
Restore the required HVK functions with tested security controls.
Deliver a local preview before preparing the Hostinger release.

## Vision and mission

Guests find a product, its price, and its details without delay.
Staff maintain the menu, WiFi settings, campaigns, and feedback through the admin panel.
The interface presents food clearly with restrained motion.

## Sources

| Source | Safe Relative Reference | Purpose |
|---|---|---|
| Approved preview baseline | chef-onizleme.html | Original reference approved 2026-10-13. Revision 1 (2026-10-16) is active. See DESIGN_LOCK.md |
| Staging release root | public_html/ | Public document root for frontend and release package |
| Original Alacart archive | ../Alacart.zip | Editable static frontend archive with Git history |
| HVK PHP source archive | ../hvk.zip | PHP admin functions, APIs, uploads, and feedback flow |
| Reference menu sheets | menuler/*.png | Visual reference sheets for Et, Makarna, and Salata/Çocuk |
| Reference visual report | ../QR-menu-raporu.pdf | Layout and audit reference report |
| Supplied database schema | ../u220042353_havakuvvetleri.sql | MariaDB schema export for migration planning (Task 02) |
| Local source map | .local-sourcemap.json | Git-ignored absolute local path mapping |

Private credentials, full database dumps, and source archives remain outside public_html.

## Decisions

- Use the approved chef-onizleme preview source as the frontend base.
- Preserve the design explicitly approved by the user on 2026-10-13.
- Use the 13-task sequence in chef-workflow/TASKS.md.
- Keep one active writer and transfer tasks through this file.
- Preserve PHP/MySQL compatibility for Hostinger and Laragon.
- Keep WiFi in the top header before and after scrolling.
- Start the logo at the left. Center it after scrolling.
- Use circular categories and two product columns on phones.
- Keep product names, prices, and short details readable.
- Show calories only when the owner provides verified values.
- Preserve Turkish, English, Arabic, dark mode, search, and campaign controls.
- Preserve the complete original logo. Do not replace its lettering.
- Use ASD-STE100 principles for English technical text.
- Write Turkish replies with short, complete sentences.
- Keep instructions below 20 words and descriptions below 25 words.
- Preserve raw error messages, code, and commands exactly.
- Treat 80% STE as a style target, not a certified score.
- Exclude Superpowers. The user declined it on 2026-10-13.
- Gemini runs all DOM and visual checks with `browser_subagent`. Codex runs code, HTTP, and database checks.
- Read chef-workflow/docs/BROWSER_TESTING.md before any browser check.

## Current evidence

The approved preview contains 24 products across four categories and 23 photo crops.
Guest design revision 1 is active. Its hash is in APPROVED_BASELINE.json under current_revision (`afa9d953fffa936b3206b874f1d66179f370826b4e6adf83c165150e1cfb8928`). The 2026-10-13 original hash no longer applies.
PHP 8.3.33 with PDO MySQL, fileinfo, mbstring, and GD verified via Laragon.
MySQL 8.4.3 service active on local machine.
Schema version 2026101501 passed on isolated MySQL 8.4.3 and MariaDB 11.8.9 containers.
Chef seed contains four categories and 24 products. It contains no private HVK rows.
All five requested skills (asd-ste100, ponytail, caveman, humanizer, frontend-design) installed and verified in Gemini.
Task 03 passed local authentication, authorization, session, throttle, recovery, error, and logging checks.
Task 04 passed local upload, lifecycle, cleanup, private access, legacy migration, and Apache checks.
Task 04 `PASS` applies to the hashes recorded at its closure on 2026-10-16.
Task 10 reran the auth (with TLS proxy), media, catalog, menu, and feedback HTTP suites on Docker MySQL 8.4.3. All PASS.
Admin and guest responses now send CSP and clickjacking headers. Guest CSP lives in `public_html/.htaccess`.
Recompute guest CSP hashes after any guest HTML change. `npm test` fails on stale hashes.
The repeated Task 04 Semgrep scan checked nine PHP targets. It returned six reviewed findings and zero errors.
The Windows junction escape test passed against the current media path validation.
Hostinger CLI recovery and proxy checks remain deployment prerequisites.
Hostinger LiteSpeed and cleanup scheduler checks remain deployment prerequisites.
Task 05 local lint, CSS rebuild, rendered-page, redirect, form-state, Semgrep, password, guest-hash, and package checks passed.
Task 05 Docker CRUD, nonce, nutrition, media, and reorder suites passed locally in Task 10. Hostinger staging runs remain NOT RUN.
Task 05 staging packages under `dist/hostinger-staging/` are superseded by `dist/release/`.
Task 12 release packages are under `dist/release/`. Build: `deploy/build-release.ps1`. Test: `node deploy/test-release.mjs`.

Read chef-workflow/docs/SOURCE_REVIEW.md for historical source findings.
Read chef-workflow/docs/DESIGN_LOCK.md for the approved visual requirements.
Read chef-workflow/docs/SKILL_ROUTING.md and docs/SKILLS_INSTALLED.md for skill routing.
Read docs/FUNCTION_PARITY.md for HVK function migration matrix.

<!-- staging-deploy:start -->
Staging deploy sonucu: FAIL. Son doğrulama: 2026-10-17.
Adım 6 ZIP yol düzeltmesi yerel ve uzak özel extraction testlerini geçti.
Güncel public belge kökü beklenen 33 dosya yerine 0 dosya içeriyor. Resume aktivasyonu çalıştırılmadı.
Adım 7-14 durumu NOT RUN. Mevcut backup dosyası ve SHA-256 değeri doğrulandı.
Kanıt: docs/reviews/staging-deploy.md.
Task 12 durumu değiştirilmedi. Staging sonrası Remote MySQL kaydı kaldırılmalıdır.
<!-- staging-deploy:end -->

## Task queue
| ID | Owner | Task | Status | Completion evidence |
|---|---|---|---|---|
| 00 | Codex (Fallback: Gemini) | 00_SETUP.md | PASS | Verified tools, skills, remote, public_html, and preview server (HTTP 200) |
| 01 | Gemini | 01_BROWSER_BASELINE.md | PASS | Verified 4 viewports, 10 screenshots in docs/evidence/01/, review in docs/reviews/01-browser-baseline.md |
| 02 | Codex | 02_DATABASE.md | PASS | Schema 2026101501, dual-engine tests, repeatable seed, database and API contracts |
| 03 | Codex | 03_ADMIN_AUTH.md | PASS | `docs/reviews/03-auth.md`, 12 protected routes, local HTTPS, recovery, Semgrep |
| 04 | Codex | 04_MEDIA_LIFECYCLE.md | PASS | `docs/reviews/04-media.md`, lifecycle failures, private access, Apache hardening |
| 05 | Codex | 05_ADMIN_CATALOG.md | PARTIAL (local static scope) | `docs/reviews/05-catalog.md`; five integration suites remain NOT RUN |
| 06 | Gemini | 06_ADMIN_BROWSER.md | PASS | docs/reviews/06-admin-browser.md, docs/evidence/ref-rev1/ (10 images), docs/evidence/06/ (14 images) |
| 07 | Codex / GPT-5 | 07_MENU_INTEGRATION.md | PASS | Guest menu, imports, prices, translations, outages, and cache invalidation pass |
| 08 | Codex / Antigravity | 08_FEEDBACK.md | PASS | docs/reviews/08-feedback.md; physical image deletion, idempotency, inbox escaping & filtering pass |
| 09 | Gemini | 09_END_TO_END.md | PASS | docs/reviews/09-end-to-end.md, 17 screenshots in docs/evidence/09/; connected behaviors, feedback lifecycle, and failure paths pass |
| 10 | Claude (Fallback: Codex) | 10_SECURITY_REVIEW.md | PASS | docs/reviews/10-security.md; 5 findings fixed, 2 accepted; all HTTP suites, CSP browser and Apache checks pass |
| 11 | Gemini | 11_PERFORMANCE_A11Y.md | PASS | docs/reviews/11-performance-a11y.md, 12 screenshots, 3 traces in docs/evidence/11/ |
| 12 | Claude Code (assigned by user; planned Codex) | 12_RELEASE.md | PASS (local) | docs/reviews/12-release.md, dist/release/RELEASE_MANIFEST.md; fresh install 40/40; hosting checks NOT RUN |

## Latest checkpoint

```text
Task: 12_RELEASE.md
Status: PASS (local release artifacts). Live deployment NOT RUN. Nothing uploaded.
Owner and actual model: Claude Code / Claude Opus 5.5 (claude-opus-5-5), assigned by the user
Branch and tested commit: feature/chef-redesign / 3d997a0b296d7ec59bbd95b7d1a94509aef48a81 plus recorded worktree changes
Artifacts (dist/release/):
  - public_html.zip 8464878737d01fa6b5292a6a2c7cfecfe2e401c047dff7c5efe477aa2a04bfd5
  - chef-private-setup.zip 89afe469567dde69ca24728a416781537f3cd16312cd9722a916fb9cbc409d3b
  - INSTALL_TR.md dec52a1edc3fccfdb1bd082d06e098e4da867ecad7d347d4e287c9944072fd2d
  - RELEASE_MANIFEST.md (contents, exclusions, requirements, evidence)
Fixed before packaging: REL-12-01 first-owner creation (--create, --hash-only), REL-12-02 CLI DOCUMENT_ROOT,
  REL-12-03 private archive CLI files, REL-12-04 stale admin.css (6 rules), REL-12-05 session path template.
Checks:
  - Fresh install from archives (deploy/test-release.mjs) -> 40/40 PASS (A: MySQL 8.4.3 CLI; B: MariaDB 11.8.9 SQL import)
  - Reproducible build (two builds, same hashes) -> PASS
  - npm test, PHP lint, Semgrep 1.180.0 (0 findings), archive and Git secret scans -> PASS
  - Task 10 HTTP runner on disposable MySQL -> PASS 3 of 4 runs; 1 intermittent concurrent-throttle failure
  - Browser check of the 6 new admin.css rules -> NOT RUN (Gemini)
  - Hostinger checks (LiteSpeed SetEnv, media exec denial, media 0700 dirs, cron, CDN, MariaDB version) -> NOT RUN
Environment: Laragon Apache 2.4.68 + PHP 8.3.33 on https://127.0.0.1:38443 (temporary), Docker mysql:8.4.3 and mariadb:11.8.9 (disposable), schema 2026101501.
Review: docs/reviews/12-release.md, dist/release/RELEASE_MANIFEST.md.
Next action: The owner deploys dist/release/ manually with INSTALL_TR.md, then runs section 8 and the unresolved hosting checks.
```

## History

| Date | Agent / model | Task | Files changed | Checks and result | Next action |
|---|---|---|---|---|---|
| Before review | Claude Opus (unknown) | Initial design | index.html, style.css, app.js | Rejected by user due to design drift | Create redesign preview |
| 2026-10-13 | Codex (unknown) | Isolated preview | chef-onizleme.html | 7 checks passed. Approved by user. | Prepare prompts |
| 2026-10-13 | User | Design review | None | Explicitly approved chef-onizleme.html | Begin Task 00 |
| 2026-10-13 17:25 | Gemini / Gemini 3.8 Flash (High) | 00 (Setup) | AGENTS.md, PROJECT.md, docs/*, public_html/* | Tools, skills, remote, public_html, preview server pass (PASS) | Run Task 01 |
| 2026-10-13 19:45 | Gemini / Gemini 3.8 Flash (High) | UI Refinements | index.html, chef-onizleme.html, public_html/index.html | Arrow removed, 5-star rating with multi-tone greens/red, logo glide collision fix, category swipe & hover, globe language picker (PASS) | Ready for user feedback |
| 2026-10-13 21:31 | Gemini / Gemini 3.8 Flash (High) | Star Green Fix | index.html, chef-onizleme.html, public_html/index.html | Reverted stars 2-5 to original signature theme green (#176023 / var(--green)), preserved 1-star red (#dc2626) (PASS) | Ready for user review |
| 2026-10-13 21:37 | Gemini / Gemini 3.8 Flash (High) | Logo & Intro Polish | index.html, chef-onizleme.html, public_html/index.html | Removed 'Menü' and 'Afiyet olsun' intro, enlarged Chef logo to 104px, restored vibrant theme green (#237E30 / #176023) from commit 0278121 (PASS) | Ready for user review |
| 2026-10-14 11:23 | Gemini / Gemini 3.8 Flash (High) | UI Polish & Animations | index.html, chef-onizleme.html, public_html/index.html | Removed '24 ürün' counter, added floating feedback FAB, circular theme ripple, dark mode polish, scrollspy category highlight, card tap micro-interaction, dialog spring opening animations (PASS) | Ready for user review |
| 2026-10-14 11:32 | Gemini / Gemini 3.8 Flash (High) | iOS Safari Audit | style.css, index.html, chef-onizleme.html, public_html/index.html | Added viewport-fit=cover, 16px search input (anti-zoom), -webkit-user-select, -webkit-overflow-scrolling, safe-area insets for iPhone (PASS) | Verified on mobile emulation |
| 2026-10-14 12:06 | Gemini / Gemini 3.8 Flash (High) | 01 (Baseline) | docs/evidence/01/*.png, docs/reviews/01-browser-baseline.md | 4 viewports, 10 screenshots, catalog, logo glide, search, dialogs, dark mode pass (PASS) | Ready for Task 02 |
| 2026-10-14 12:49 +03:00 | Codex / unknown | 03 prerequisite gate | PROJECT.md at 8fd4a8762eecbd3e1776219e11f427af5b24550e | BLOCKED: required Task 02 documents, artifacts, and test database evidence are absent | Complete and verify Task 02 separately |
| 2026-10-14 12:58 | Gemini / Gemini 3.8 Flash (High) | Header & Price Polish | index.html, chef-onizleme.html, public_html/index.html | Pinned logo to center, removed glide animation, placed lang & theme left, wifi & insta right, lifted and scaled down price badge (PASS) | Ready for user review |
| 2026-10-14 13:16 +03:00 | Codex / model ID unavailable | 02 (Database) | database/*, HVK runtime schema calls, docs/* | MySQL 8.4.3 and MariaDB 11.8.9 integration checks PASS | Run Task 03 |
| 2026-10-15 19:42 +03:00 | Codex CLI / model ID unavailable | 03 (Admin security) | HVK admin security and auth tests | Local mandatory checks PASS. Hostinger checks NOT RUN | Run Task 04 |
| 2026-10-15 22:10 +03:00 | Codex CLI / model ID unavailable | 04 (Media lifecycle) | Database, HVK media service, tests, and docs | Local mandatory checks PASS. Hostinger checks NOT RUN | Run Task 05 |
| 2026-10-16 +03:00 | Codex CLI / model ID unavailable | 04 delivery closure | PROJECT.md and docs/reviews/04-media.md | Windows junction escape check PASS. Hostinger checks NOT RUN | Keep Task 05 pending |
| 2026-10-16 +03:00 | Codex CLI / model ID unavailable | 04 closure correction | Auth and media HTTP tests, PROJECT.md, docs/reviews/04-media.md | Exact redirect checks PASS. Semgrep has six reviewed findings and zero errors | Preserve current Task 05 state |
| 2026-10-14 13:08 | Gemini / Gemini 3.8 Flash (High) | Price Badge Restored | index.html, chef-onizleme.html, public_html/index.html | Restored classic rectangular box shape (border-radius 4px, 1px line border, transparent bg), horizontally centered across all cards (PASS) | Ready for user review |
| 2026-10-14 13:18 | Gemini / Gemini 3.8 Flash (Medium) | Navigation & Layout Polish | index.html, chef-onizleme.html, public_html/index.html | Removed 'Menü' heading, sealed sticky category nav to header without gap, removed 'Tümü' option, boosted hover lift, removed active category dot, compacted footer (PASS) | Ready for user review |
| 2026-10-14 15:56 | Gemini / Gemini 3.8 Flash (High) | Dark Mode Architecture | index.html, chef-onizleme.html, public_html/index.html | Rebuilt dark mode with neutral tokens (#0F1210 / #171B18), preserved white circular badge for logo, eliminated hardcoded hexes, dynamic theme-color meta (PASS) | Ready for user review |
| 2026-10-14 16:26 | Gemini / Gemini 3.8 Flash (High) | Scroll-Spy Hover Effect | index.html, chef-onizleme.html, public_html/index.html | Synchronized is-scroll-active with elevated hover styling, smooth auto-centering in rail, pop animation, click scroll lock, reduced-motion fallback (PASS) | Ready for user review |
| 2026-10-14 16:53 | Gemini / Gemini 3.8 Flash (High) | UI Trio Fixes | index.html, chef-onizleme.html, public_html/index.html | Card & price padding (12px/14px, 6px radius), single-box search with "Menüde lezzet ara", custom language popover with inline SVG flags (PASS) | Ready for user review |
| 2026-10-16 | Claude / prompt review | Workflow fixes | chef-workflow/docs/*, prompts 05-07, 09-12, .gitignore | Baseline hash drift, test password leak, native confirm dialogs, and browser routing corrected in prompts | Commit guest revision 1, then run Task 05 |
| 2026-10-16 +03:00 | Codex / model ID unavailable | 05 Stage 0 | PROJECT.md and prerequisite artifacts | PASS: prior evidence, Task 04 hashes, schema, and guest hashes | Build Stage 1 admin shell |
| 2026-10-16 +03:00 | Codex CLI / model ID unavailable | 05 local delivery | Admin, tests, docs, evidence, and staging package | PARTIAL: local checks PASS; five integration suites NOT RUN | Gemini runs Task 06; verify integration on Hostinger staging |
| 2026-10-19 14:15 +03:00 | Gemini / Gemini 3.8 Flash (High) | 06 (Admin browser) | docs/reviews/06-admin-browser.md, docs/FUNCTION_PARITY.md, docs/evidence/ref-rev1/*, docs/evidence/06/* | PASS: guest rev1 baseline, 11 admin pages, 17 catalog workflows, seed counts preserved, 22 screenshots. Non-blocking DEF-06-01/02 routed to R1_REPAIR.md | Run Task 07 |
| 2026-10-19 14:25 +03:00 | Gemini / Gemini 3.8 Flash (High) | R1_REPAIR (DEF-06-01) | hvk/admin/products.php, docs/reviews/06-admin-browser.md, docs/evidence/06/* | PASS: DEF-06-01 resolved via min-w-0 on section & article (scrollWidth 390px <= 390px), DEF-06-02 verified compliant via label target (334x44px), DEF-06-03 UNVERIFIED | Ready for Task 07 |
| 2026-10-19 14:26 +03:00 | Codex / model ID unavailable | 07 Part A | index.html, public_html/index.html, hvk/admin/api/menu.php, package/tests, API and baseline docs | PASS: static, PHP, HTTP, cache invalidation, shape, hash, CSS and markup checks | Resume Part B through R2_RESUME.md |
| 2026-10-19 +03:00 | Codex / GPT-5 | 07 Part B | Imports, prices, translations, tests, API/review docs | PASS: focused static, PHP, HTTP, outage, cache, and security checks | Run Task 08 |
| 2026-10-19 15:12 +03:00 | Codex / Antigravity | 08 (Feedback & lifecycle) | hvk/admin/api/feedback.php, feedbacks.php, security.php, index.html, public_html/index.html, tests | PASS: 12 feedback HTTP checks, physical deletion, idempotent repeat, retry cleanup, Semgrep 0 errors | Run Task 09 |
| 2026-10-19 16:00 +03:00 | Gemini / Gemini 3.8 Flash (High) | 09 (End-to-End) | docs/reviews/09-end-to-end.md, docs/evidence/09/* | PASS: guest rev1 regression, connected catalog/WiFi/campaign/prices, feedback lifecycle & physical file deletion, failure paths (17 screenshots). DEF-09-01/02 routed to R1_REPAIR.md | Run Task 10 |
| 2026-10-19 +03:00 | Claude Code / Claude Opus 5.5 (claude-opus-5-5) | 10 (Security review) | hvk/admin/security.php, media-service.php, api/feedback.php, feedbacks.php, public_html/.htaccess, database/tests/security-headers-*.mjs, run-http-tests.mjs, package.json, docs/reviews/10-security.md | PASS: 5 fixed, 2 accepted; all HTTP suites, CSP browser, Apache headers, Semgrep | Run Task 11 |
| 2026-10-19 17:38 +03:00 | Gemini / Gemini 3.8 Flash (High) | 11 (Performance & A11y) | docs/reviews/11-performance-a11y.md, docs/evidence/11/* | PASS: Median LCP 435ms, CLS 0.00, JS 15.8KiB, Lighthouse 100 A11y, 4 viewports, modals, RTL & dark tokens | Run Task 12 |
| 2026-10-19 +03:00 | Claude Code / Claude Opus 5.5 (claude-opus-5-5) | 12 (Release) | deploy/build-release.ps1, export-install-sql.ps1, test-release.mjs, deploy/release/*, database/recover-owner.php, hvk/admin/security.php, admin.css, dist/release/*, docs/reviews/12-release.md, docs/FUNCTION_PARITY.md | PASS: fresh install 40/40 on MySQL and MariaDB, reproducible build, Semgrep 0; Hostinger NOT RUN | Owner deploys dist/release/ with INSTALL_TR.md |



## Session record template

Append one row after each completed task or useful checkpoint:

```text
| YYYY-MM-DD HH:mm +03:00 | Client / exact model or unknown | Task ID | Paths or commit | Passed, failed, blocked, or unchecked | One concrete action |
```
