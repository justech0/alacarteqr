# Task 03: Secure the HVK admin foundation

Owner: Codex. Preferred model: GPT-6.1 Sol. Effort: High.
Prerequisite: Task 02 database checks pass.

Read AGENTS.md, PROJECT.md, and chef-workflow/CONTRACT.md.
Read docs/DATABASE.md and docs/API_CONTRACT.md.
Use Ponytail and the installed code-security skill.
Inspect HVK login, logout, configuration, shared header, and authorization helpers.
Implement changes now and verify their behavior locally.

## Configuration and errors

Move real configuration outside public_html and tracked source.
Use placeholder values in committed configuration examples.
Keep SQL dumps, backups, logs, and private storage outside the public document root.
Return generic public errors with a request identifier.
Write detailed exceptions to private server logs.
Keep passwords, tokens, contact information, and session identifiers out of logs.
Ensure API errors remain valid JSON with correct status codes.

## Authentication

Use PHP server sessions. Do not add JWT.
Use password_hash and password_verify with supported settings.
Renew the session ID after successful login.
Invalidate the session and cookie on logout.
Set HttpOnly, SameSite, and production Secure cookie flags.
Keep local HTTP behavior explicit without weakening production HTTPS configuration.
Set reasonable idle and absolute session limits.
Reject expired or invalid sessions on every protected route.
Apply persistent login throttling using bounded account and network signals.
Avoid permanent account lockout that attackers can trigger.
Trust forwarded addresses only through explicitly configured proxies.
Replace the reusable reset phrase with a controlled owner recovery method.
Do not create a public password reset endpoint without its required safeguards.
Do not copy the legacy admin password hash into the Chef seed.

## Authorization and mutations

Enforce login and authorization in shared server helpers.
Inventory every admin endpoint and direct file route.
Require CSRF validation and appropriate methods for state changes.
Make logout a protected state-changing action.
Retain prepared queries and context-specific output escaping.
Keep anonymous menu reads separate from admin sessions.
Prevent caching of admin pages and authentication responses.
Keep the existing admin navigation functional for later tasks.

## Checks

Test valid login, wrong passwords, throttling, expiration, logout, and session renewal.
Test direct unauthorized access to every discovered admin route.
Test missing and invalid CSRF tokens on representative mutations.
Check cookies under the configured local and production modes.
Confirm that public errors reveal no stack traces, database names, or configuration paths.
Run focused Semgrep checks and inspect each relevant finding.
Write docs/reviews/03-auth.md with evidence and unresolved risks.
Update PROJECT.md and set task 04 as the next action.
