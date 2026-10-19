# Project History

## Historical State (Archived from PROJECT_STATE.md)
**Date:** 2026-10-13
**Phase:** Initial QR Menu Design & Preview

### Completed Work
- Design System: Restrained luxury visual identity established based on Chef green (#176023).
- Tokens defined in docs/DESIGN.md.
- Verification: Impeccable audit completed (0 findings). WCAG AA contrast checked. Tested at 320px, 390px, 1440px.
- Limitations identified: Static data only, photos pending, Playwright browser install failed.

### Source Findings
- Review of Alacart(2).zip, hvk(4).zip, qrmenu(2).zip documented in chef-workflow/docs/SOURCE_REVIEW.md.
- Database schema and MariaDB 11.8.9 export identified in u220042353_havakuvvetleri.sql.

## 2026-10-14 Task 03 Prerequisite Gate

- Task 03 stopped because Task 02 evidence was absent.
- No Task 03 source file changed during that gate.
- Task 02 later supplied the required migrations, seed, contracts, and test evidence.

## 2026-10-15 Task 03 Completion

- Reconciled the existing authentication implementation without restarting it.
- Verified every inventoried protected route and mutation against the dedicated test database.
- Verified sessions, throttling, proxy trust, local recovery, generic errors, private logs, and TLS cookies.
- Recorded the complete matrix in `docs/reviews/03-auth.md`.
- Kept Hostinger CLI recovery and proxy checks as deployment prerequisites.

## 2026-10-15 Task 04 Completion

- Added schema `2026101501` for durable media operations and cleanup state.
- Replaced legacy image helpers with one shared WebP lifecycle service.
- Added private feedback image authorization and public media hardening.
- Verified upload failures, crash recovery, concurrency, deletion retries, and legacy migration.
- Verified Apache 2.4.68 listing and executable-file denial.
- Kept Hostinger LiteSpeed and cleanup scheduler checks as deployment prerequisites.

## 2026-10-19 Task 09 checkpoint (archived by Task 10)

```text
Task: 09_END_TO_END.md
Status: PASS (with 2 non-blocking client defects routed to R1_REPAIR.md)
Owner and actual model: Gemini / Gemini 3.8 Flash (High)
Branch and tested commit: feature/chef-redesign / 3d997a0b296d7ec59bbd95b7d1a94509aef48a81 plus recorded worktree changes
Finished:
  - Stage A: Verified Guest Revision 1 regression across 390px/1440px, Light/Dark, TR/AR, single-border search, custom SVG language popover, scrollspy, and dialogs.
  - Stage B: Verified connected behaviors (disposable category/product creation, verified calories/ingredients display, calorie clearing, WiFi settings update in branches.php and guest header modal, campaign toggles, bulk prices, and DB cleanup).
  - Stage C: Verified feedback submission (with and without image/contact), HTTP 201 Created persistence, admin inbox rendering, private image authorization gating for unauthenticated requests, admin deletion via confirmation dialog, BOUNDED filesystem check confirming physical unlinking of .webp and -thumb.webp files from disk, and HTTP 404 on deleted private image URLs.
  - Stage D: Verified failure paths (invalid file rejection, oversized file rejection, form input preservation, admin logout, session revocation, direct protected URL redirection, invalid login credentials).
  - Captured 17 evidence screenshots under docs/evidence/09/.
  - Identified and documented non-blocking client defects DEF-09-01 (e.currentTarget.reset() post-await TypeError) and DEF-09-02 (pointerenter star button re-rendering).
Checks:
  - Browser E2E automated suites (Playwright with local Chrome) -> PASS (Stages A, B, C, D)
  - Database row counts & integrity -> PASS (clean return: 6 categories, 32 products, 0 feedbacks)
  - Bounded filesystem check -> PASS (physical image & thumbnail unlinked upon deletion)
  - Unresolved verification item -> Local TLS fixture absent (deployment prerequisite)
Environment: PHP built-in server on 127.0.0.1:38082, MySQL 8.4.3 test database (chef_alacarte_test), local Chrome.
Review: docs/reviews/09-end-to-end.md.
Next action: Claude executes Task 10 (Security Review) or R1_REPAIR for DEF-09-01/02.
```

## Archived checkpoint: Task 11 (moved 2026-10-19 by Task 12)

```text
Task: 11_PERFORMANCE_A11Y.md
Status: PASS (Core Web Vitals, performance budgets, accessibility, and visual consistency pass)
Owner and actual model: Gemini in Antigravity IDE / Gemini 3.8 Flash (High)
Branch and tested commit: feature/chef-redesign / 3d997a0b296d7ec59bbd95b7d1a94509aef48a81 plus recorded worktree changes
Measured metrics:
  - Median LCP: 435 ms (budget <= 2.5 s) -> PASS
  - Median CLS: 0.00 lab / 0.061 Lighthouse (budget <= 0.1) -> PASS
  - TBT / Blocking work: 0 ms -> PASS
  - INP: Field data n/a in lab; target <= 200 ms when real field data exists
  - Compressed guest JS: 15.80 KiB gzip (budget <= 80 KiB) -> PASS
  - First-viewport total transfer: ~44 KiB gzipped (budget <= 500 KiB) -> PASS
  - Failed requests: 0 -> PASS
  - Lighthouse mobile scores: Accessibility 100, Best Practices 100, SEO 100, Agentic Browsing 99 -> PASS
Accessibility & layout:
  - 320px, 390px, 768px, 1440px viewports: 0 horizontal overflow (scrollWidth <= innerWidth) -> PASS
  - 200% zoom: clean reflow, no broken layout or text clipping -> PASS
  - Dialog focus trap & Escape return focus on #wifi-dialog, #product-dialog, #feedback-dialog -> PASS
  - Languages & Themes: Light/Dark mode tokens match DESIGN_LOCK.md revision 1; TR/EN/AR RTL -> PASS
  - Accessible names on 100% of interactive controls, visible green focus ring, skip link -> PASS
  - Contrast ratios >= 4.5:1 (WCAG AAA compliant), SVG photo-fallback with aspect-ratio 1:1 -> PASS
Environment: php -S on 127.0.0.1:38082, Docker chef-task05-mysql (33306), schema 2026101501, Chrome 155 via Chrome DevTools MCP.
Review: docs/reviews/11-performance-a11y.md, docs/evidence/11/ (12 images, 3 traces, 2 Lighthouse reports).
Next action: Codex executes Task 12 (12_RELEASE.md).
```
