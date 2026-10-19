# Task 02 Database Review

Date: 2026-10-14 13:16 +03:00  
Status: **PASS**  
Owner: Codex  
Actual model: unavailable to the client session  
Branch: `feature/chef-redesign`  
Reviewed HEAD: `8fd4a8762eecbd3e1776219e11f427af5b24550e`

## Evidence

| Check | Result | Evidence |
|---|---|---|
| Task 01 prerequisite | PASS | `docs/reviews/01-browser-baseline.md` has no open blocking defect |
| Private dump structure review | PASS | Seven expected tables. No destructive administration, grants, definers, triggers, events, routines, or external file operations |
| Empty MySQL install | PASS | MySQL 8.4.3, schema `2026101402` |
| Empty MariaDB install | PASS | MariaDB 11.8.9, schema `2026101402` |
| Repeat migration | PASS | Second run applied no migration |
| Sanitized legacy migration | PASS | Synthetic rows remained intact |
| Failed migration recovery | PASS | Orphan prerequisite failed without a migration record. Repair and rerun passed |
| Approved seed source | PASS | Four categories and 24 unique products |
| Repeat seed | PASS | Counts stayed 4 and 24. A staff edit remained unchanged |
| Private seed exclusion | PASS | No admin, branch, campaign, feedback, password hash, WiFi, or customer row |
| Price precision | PASS | `DECIMAL(10,2)` preserved exact values |
| Nutrition state | PASS | Ingredients, calories, and verification times are null |
| Foreign keys | PASS | Invalid product category was rejected |
| Turkish comparison | PASS | Dotted and dotless pairs matched with `utf8mb4_turkish_ci` |
| Arabic round trip | PASS | Approved Arabic serving text matched exactly |
| Public ID index | PASS | ASCII, binary comparison, 64-character limit |
| CLI smoke | PASS | `status`, `migrate`, `seed`, and `verify` returned success |
| Runtime DDL scan | PASS | No `CREATE TABLE`, `ALTER TABLE`, or `SHOW COLUMNS` remains under `hvk/admin` |
| PHP syntax | PASS | 26 PHP files passed PHP 8.3.33 lint |
| Existing JavaScript checks | PASS | `npm.cmd test` passed |
| Changed-code Semgrep | PASS | Semgrep CE 1.180.0, 23 PHP rules, 10 targets, zero findings |
| Full admin Semgrep triage | PASS | Eight old alerts. Two already use `sanitize()`. Six print integer casts. No Task 02 finding |
| Hostinger account version | NOT RUN | Deployment access is unavailable. MariaDB 11.8.9 compatibility passed locally |

## Container evidence

- `mysql:8.4.3` resolved image: `sha256:106d5197fd8e4892980469ad42eb20f7a336bd81509aae4ee175d852f5cc4565`.
- `mariadb:11.8.9` resolved image: `sha256:6422478cb8e159f080fb1d8ccf65101e26fe51385787fde7d16c3b165a331f15`.
- Test databases: `chef_alacarte_test` and `chef_alacarte_legacy_test`.
- Tests used synthetic records and synthetic container credentials.
- Laragon and HVK databases received no Task 02 writes.

## Source changes

- Added versioned CLI migrations, seed data, schema checks, and integration tests under `database/`.
- Moved runtime credentials to the private `CHEF_DB_CONFIG` file contract.
- Removed request-time schema creation and alteration from HVK PHP.
- Kept the approved frontend and preview files unchanged during Task 02.

## Remaining work

- Verify the exact Hostinger MariaDB version during deployment preparation.
- Task 03 must implement the full authentication and generic error contract.
- Task 07 must switch the menu endpoint to the documented public shape.
- Task 08 must implement feedback idempotency and the 150-character client limit.
