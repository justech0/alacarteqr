# Task 10: Review and fix release security risks

Owner: Claude in Antigravity. Preferred model: Claude Opus. Use the newest Opus in the picker.
Reason: Codex wrote Tasks 02-08. An independent model reviews them.
Fallback: Codex, GPT-6.1 Sol, High effort, when Claude usage is exhausted.
Do not change guest CSS, color values, or design tokens.
Prerequisite: Task 09 has no unresolved core workflow blockers.

Read AGENTS.md, PROJECT.md, and chef-workflow/CONTRACT.md.
Read the API contract, media lifecycle, and current defect reports.
Use code-security, Semgrep, and targeted manual source review.
Test only the authorized local application.
Treat scanner findings as evidence to review, not automatic proof.

## Targeted review

Trace untrusted values from forms and APIs to SQL, HTML, files, and server requests.
Check stored and reflected XSS with harmless local fixtures.
Check SQL parameter binding, malformed identifiers, and mass-assignment behavior.
Check login throttling, session renewal, logout, expiration, and recovery controls.
Check CSRF protection and server authorization on every admin endpoint.
Check record ownership and direct private image requests.
Check upload validation, execution prevention, path containment, deletion, and failure recovery.
Check clickjacking controls, MIME sniffing protection, and appropriate referrer policy.
Run the auth and media HTTP suites. PROJECT.md lists them as NOT RUN after Task 05.
Build a Content Security Policy from the application's actual resources.
Verify that the policy does not break the approved interface or Motion behavior.
Prefer local assets and explicit directives over broad unsafe allowances.
Check actual response headers under the deployment-compatible local server.
Keep Hostinger-specific header verification listed for the later authorized deployment check.
Check configuration, SQL, backups, logs, repository metadata, and error pages for disclosure.
Search source, tests, logs, and reports for valid test passwords and credential files.
Confirm the credentials file stays outside Git and public_html.
Check public and private cache separation.
Review external fetches only where they exist.
Restrict translation destinations and redirect behavior.
Do not introduce arbitrary remote image fetching.
Record JWT confusion as not applicable when JWT is absent.
Do not add JWT merely to complete a security checklist.

## Dependencies and tests

Review locked runtime and build dependencies for relevant known vulnerabilities.
Check installed skill origins and repository changes for unexpected scripts.
Apply the smallest compatible fix for a confirmed issue.
Do not perform a broad framework or build-tool migration to clear an audit summary.
Preserve comments that explain security decisions or license obligations.
Add focused regression tests for high-impact fixes.
Re-run affected integration checks after each logical fix group.
Do not disable a test or scanner rule without a documented false-positive explanation.

## Release decision

Write docs/reviews/10-security.md.
Record finding ID, evidence, impact, fix, verification, and remaining exposure.
Record dependency changes and their concrete reason.
Avoid claims of complete security or guaranteed attack prevention.
Block release for unresolved critical or high-impact exploitable findings.
Update PROJECT.md and hand off to Gemini for task 11.
