# Task 03 Authentication Review

Date: 2026-10-15 19:42 +03:00  
Status: PASS  
Branch: `feature/chef-redesign`  
HEAD: `8fd4a8762eecbd3e1776219e11f427af5b24550e`

## Environment

- CLI PHP: 8.3.33.
- Loopback web PHP: 8.3.33.
- Test database: `chef_alacarte_test` only.
- Database server: MySQL 8.4.3.
- Container: `mysql@sha256:106d5197fd8e4892980469ad42eb20f7a336bd81509aae4ee175d852f5cc4565`.
- Semgrep CE: 1.180.0.
- Client: Codex CLI.
- Model label: unavailable to the client session.

## Required Checks

| Check | Result | Evidence |
|---|---|---|
| Task 02 prerequisite | PASS | Schema `2026101402` created in the dedicated test database |
| PHP syntax | PASS | 33 changed PHP files passed PHP 8.3.33 lint |
| Route inventory | PASS | 12 protected routes, 3 public routes, and 6 includes matched the filesystem |
| Valid login | PASS | Synthetic owner received an authenticated session |
| Wrong and unknown users | PASS | Both returned the same `401` response and message |
| Dummy verification | PASS | Unknown users used the configured dummy password hash |
| Login CSRF | PASS | Missing and invalid tokens returned `403` |
| Session renewal | PASS | Login changed the session identifier and invalidated the old cookie |
| Idle and absolute expiry | PASS | `database/tests/session-expiry.php` rejected both expired sessions |
| Password change invalidation | PASS | `auth_version` increased and the old session lost access |
| Owner recovery | PASS | Local CLI recovery consumed its private file and invalidated the old session |
| Logout | PASS | Protected POST destroyed server data and expired the matching cookie |
| Old logout cookie | PASS | The old cookie could not restore access |
| Account throttle | PASS | Five failures allowed. The next request returned `429` with `Retry-After` |
| Full-address throttle | PASS | Ten failures allowed. The next request returned `429` |
| Network prefix signal | PASS | IPv4 `/24` and IPv6 `/56` remained monitoring-only signals |
| Atomic throttle updates | PASS | Twelve concurrent attempts produced a bounded account row |
| Stable block expiry | PASS | Blocked requests did not change counters or `blocked_until` |
| Throttle storage failure | PASS | Login failed closed with generic `503` |
| Administrator storage failure | PASS | Protected access failed closed with generic `503` |
| Proxy trust | PASS | Untrusted forwarded headers were ignored |
| Proxy chain | PASS | Trusted chains resolved from right to left |
| Unauthorized routes | PASS | Every protected route denied access before database or file changes |
| Authenticated routes | PASS | Pages returned `200`; a missing private image returned `404`; all responses used `no-store` |
| Mutation CSRF | PASS | Missing and invalid tokens failed for every inventoried action and logout |
| Mutation methods | PASS | Unsupported methods returned `405` with `Allow` |
| Anonymous menu | PASS | The menu response sent no `Set-Cookie` header |
| Local cookie | PASS | HTTP used HttpOnly and SameSite Strict without Secure |
| Production cookie | PASS | A real local TLS request used Secure, HttpOnly, and SameSite Strict |
| Production HTTP rejection | PASS | Direct production HTTP returned generic `503` |
| Configuration failure | PASS | Missing and public-root configuration returned generic JSON `503` with `requestId` |
| Log target checks | PASS | Missing parents and non-file targets were rejected |
| Log symlink runtime check | NOT RUN | Windows denied symlink creation without elevated permission |
| Public error disclosure | PASS | Responses contained no stack, database name, or configuration path |
| Private log fields | PASS | JSON logs used only approved fields and contained no test secrets or session markers |
| Focused Semgrep | PASS | 25 PHP security rules scanned 9 files with zero findings |
| Hostinger CLI recovery | NOT RUN | SSH and Hostinger PHP CLI access are unavailable |
| Hostinger proxy and HTTPS | NOT RUN | Deployment access is unavailable |

## Implementation Evidence

- `hvk/admin/security.php`: `ce355b4d68c633a8833cdeee398d28f7b6564f85adeb1a6425af08c5b5128677`.
- `database/recover-owner.php`: `7127271a8a0c21122a3b35160e41f1ed0fc3232b88fe8de5c02f2e0cb186c682`.
- `database/tests/auth-http.mjs`: `da29bcdaa7aa017f9aee4e10ea1e423bd82c5c7849a18113f694c2a52cfc4f16`.
- `database/tests/verify-auth-static.php`: `0d995c728e9dbd8f38b08300791e5a623f05a70780e9d3437b1568e0027c71b5`.

## Unresolved Deployment Prerequisites

- Verify Hostinger PHP CLI or SSH access before owner recovery is required.
- Verify trusted proxy values and Secure cookies on the Hostinger HTTPS route.
- Verify the log and configuration permissions under the Hostinger account.

These deployment checks do not invalidate the isolated local security checks.
