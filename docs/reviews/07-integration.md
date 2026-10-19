# Task 07 integration review

Date: 2026-10-19. Owner: Codex / GPT-5.
Branch: `feature/chef-redesign`. Commit: `3d997a0` plus worktree changes.

## Result

Status: PASS.

- The public menu reads categories, products, WiFi, and campaign state from the database.
- Product and price imports are atomic and bounded to 2 MiB and 500 rows.
- CSV and JSON fields use the documented allowlist.
- Remote image URLs are rejected. Local product uploads remain required.
- Price parsing accepts Turkish decimals and rejects ambiguous separators.
- CSV export neutralizes spreadsheet formulas in text fields.
- Staff can edit English and Arabic category and product text.
- Missing translations remain `null`. The guest falls back to Turkish.
- Automatic external translation requests were removed.
- All tested mutations change `menu_version` and the public ETag.
- Database outage returns generic JSON `503` without menu data or internal details.

## Evidence

| Check | Result | Evidence |
|---|---|---|
| Guest static and JavaScript parse | PASS | `npm test` |
| PHP syntax | PASS | All admin PHP files and outage fixture |
| Catalog unit and HTTP suite | PASS | Prices, imports, limits, exports, moves, translations |
| Public menu HTTP suite | PASS | API reads, mutation visibility, WiFi, campaign, ETag |
| Database outage | PASS | Generic JSON `503`; no internal details |
| Focused Semgrep `p/php` | PASS | Six reviewed safe echoes; zero scan errors |
| Guest revision hashes | PASS | Frozen preview and `rev1-data` hashes unchanged |
| Browser and visual checks | NOT RUN | Task 07 forbids visual checks |
| Combined legacy HTTP runner | FAIL (environment) | Local TLS key and certificate fixture absent |

The combined legacy HTTP runner stopped because its local TLS fixture was absent.
Task 07 focused suites ran independently and passed.

## Reviewed Semgrep findings

Six `echoed-request` findings are safe.
They contain escaped search text, canonical numeric IDs, or fixed server choices.

## Handoff

Task 08 is next. Implement persistent feedback and physical image deletion checks.
