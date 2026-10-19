# Task 08 feedback review

Date: 2026-10-19. Owner: Codex / Antigravity.
Branch: `feature/chef-redesign`. Commit: `3d997a0` plus worktree changes.

## Result

Status: PASS.

- Guest feedback form submits multipart data to `/hvk/admin/api/feedback.php` with client-generated `Idempotency-Key`.
- Contact field length is bounded to 150 characters across frontend markup, script, and backend validation.
- Recovery message appears when guests select rating one or two.
- Form inputs remain preserved if submission fails or network is interrupted.
- Preview notices (`preview-note` and `demo-notice`) removed. Submit button uses localized text (`sendFeedback`).
- Translations are complete across Turkish, English, and Arabic.
- Server validates allowed topics (`taste`, `service`, `staff`, `hygiene`, `other`), integer rating (1–5), contact (<= 150 chars), and message (<= 2000 chars).
- Array inputs for scalar fields are rejected with HTTP 422.
- Abuse protection enforces persistent anonymous rate limiting (30 requests/minute via `admin_login_throttles`) returning HTTP 429 with `Retry-After`.
- Idempotency mechanism verifies key hash and payload fingerprint, returning 201 for creation, 200 with `{ duplicate: true }` for exact duplicates, and 409 Conflict for mismatched payloads.
- Uploaded feedback photos are managed through `chef_media_stage_upload`, resized via GD to private main and thumbnail WebP files, activated into `private` scope, and source files unlinked upon finalization.
- Private feedback images are accessible only with authenticated admin sessions via `/hvk/admin/private-image.php` with `nosniff` and `no-store` headers; anonymous requests are redirected to login.
- Admin inbox escapes all displayed user content (HTML special characters), provides topic and rating filtering, text search, bounded pagination, and pending cleanup alerts.
- Deletion lifecycle proves that deleting a feedback record removes its database row, marks managed uploads as `deleted`, and physically unlinks both main image and thumbnail files from disk.
- Repeat deletion requests are handled idempotently with HTTP 303 redirect.
- Incomplete deletion states trigger an administrative banner and a safe `retry_cleanup` action.

## Evidence

| Check | Result | Evidence |
|---|---|---|
| Method & Content-Type Gate | PASS | `feedback-http.mjs`: GET/PUT rejected, multipart/form-data & JSON accepted |
| Origin Control | PASS | `feedback-http.mjs`: untrusted origin rejected with 403 |
| Scalar Type & Field Validations | PASS | `feedback-http.mjs`: array rejection (422), rating range (422), length limits (422) |
| Idempotency (201 / 200 / 409) | PASS | `feedback-http.mjs`: create (201), repeat (200), payload mismatch conflict (409) |
| Physical Media Staging & Storage | PASS | `feedback-http.mjs`: private WebP main and thumbnail physically verified on disk |
| Private Image Access Control | PASS | `feedback-http.mjs`: anonymous 302/303 redirect, admin session 200 WebP with nosniff |
| Admin Inbox Escaping & Filtering | PASS | `feedback-http.mjs`: XSS payloads escaped, topic & rating filter queries verified |
| Physical File Deletion on Disk | PASS | `feedback-http.mjs`: feedback row deleted, upload state deleted, files unlinked on disk |
| Repeat Deletion Idempotency | PASS | `feedback-http.mjs`: repeated delete returns 303 without error |
| Cleanup Pending Detection & Retry | PASS | `feedback-http.mjs`: synthetic pending row triggers alert banner, retry action succeeds |
| Abuse Rate Limiting | PASS | `feedback-http.mjs`: 35 rapid requests trigger 429 with `Retry-After` header |
| Guest Static & Syntax Check | PASS | `npm test` (`guest-static.mjs`, syntax checks) |
| Guest Build Sync | PASS | `npm run build:guest` (hashes verified in `APPROVED_BASELINE.json`) |
| Semgrep Security Scan | PASS | `semgrep scan`: 0 findings on `api/feedback.php` and `feedbacks.php` |
| Browser and visual checks | NOT RUN | Reserved for Gemini in Task 09 |
| Combined legacy HTTP runner | FAIL (environment) | Local TLS fixture absent (unresolved verification item) |

## Database and Filesystem Proof

- Submitted feedback record ID and upload tracked:
  - Database row: `feedbacks` table populated with `image_path = 'managed:<id>'`.
  - Managed media: `managed_uploads` row created with `file_role = 'main'`, `storage_scope = 'private'`, `state = 'active'`.
  - Filesystem: physical WebP file verified at `%TEMP%\chef-task05\private\task04-private\feedback\<hash>.webp` (`is_file() === true`).
- Deletion verification:
  - Action: POST to `/hvk/admin/feedbacks.php` with CSRF and submission token.
  - Database check: `SELECT id FROM feedbacks WHERE id = <id>` returns 0 rows.
  - Managed media check: `managed_uploads` state updated to `deleted`.
  - Filesystem check: `is_file()` returns `false` for both main and thumbnail paths. Physical files permanently unlinked.
  - Authorization check: subsequent requests to `/hvk/admin/private-image.php?id=<id>` return HTTP 404.

## Handoff

Task 08 is complete. All lifecycle and physical deletion checks pass.
Task 09 (End-to-End browser verification by Gemini) is ready to begin.
