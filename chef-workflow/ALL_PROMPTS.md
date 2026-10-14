# Chef implementation prompts

Use one selected prompt at a time.
Do not load this complete document into every agent session.
The shared rules remain in chef-workflow/CONTRACT.md.


---

File: 00_SETUP.md

# Task 00: Prepare the shared project

Owner: Codex. Preferred model: GPT-6.1 Sol. Effort: default or Medium.
Fallback: Available GPT-6 Sol, then the available coding model recorded by the client.

Read chef-workflow/CONTRACT.md and chef-workflow/docs/SKILL_ROUTING.md.
Inspect existing AGENTS.md and PROJECT.md before merging instructions.
The user approved chef-onizleme.html. Preserve that design.
Complete setup now. Do not stop after proposing a plan.

## Locate and protect

Find the approved preview package and its editable source.
Find the HVK PHP source, original Alacart source, reference screenshots, and supplied SQL export.
Use current files when their names differ from the documented upload names.
Record exact local paths in an ignored local source map.
Record safe relative references in PROJECT.md.
Do not copy source archives, private SQL, or credentials into public_html.
Check the repository remote against justech0/alacarteqr.
Preserve uncommitted work and existing branches.
Create a feature branch when the current checkout permits a safe branch change.
Record a useful file backup when Git cannot provide a safe checkpoint.
Do not push or rewrite repository history during setup.

## Verify tools

Check PHP, PDO MySQL, fileinfo, mbstring, GD, and the active database server.
Check Git, Node, the project package manager, and Laragon's configured document root.
Check actual client model labels and the current authentication method where visible.
Do not infer an API entitlement from a ChatGPT or Google subscription.
Do not print authentication configuration or tokens.
Use existing compatible runtimes. Avoid unrelated system upgrades.
List only missing requirements that block this sequence.

## Configure skills

Inspect current skill paths, links, and discovery in the running client.
Install only missing requested skills from SKILL_ROUTING.md.
Read upstream installation instructions before running installation commands.
Preserve working shared skill links.
Keep required licenses and reference files.
Record exact repository commits and installation destinations.
Run one harmless activation check for each newly installed skill.
Mark the other client's discovery as pending until that client verifies it.
Do not claim access to a tool based only on an old report.

## Establish shared instructions

Merge templates/AGENTS.merge.md into the existing root AGENTS.md.
Use the selected client's documented entry mechanism for short pointers to AGENTS.md and PROJECT.md.
For Gemini, verify the project rule is actually loaded in Antigravity.
Do not assume that a random root GEMINI.md automatically activates IDE rules.
Preserve existing instructions that do not conflict with this approved workflow.
Keep one active PROJECT.md. Archive older status documents with a pointer to it.
Record design approval, task 00 ownership, current branch, and next task 01.
Create docs/FUNCTION_PARITY.md from the reviewed HVK function table.
Give every function a status and evidence path.
Keep implementation, integration, and browser checks as separate statuses.

## Prepare the approved preview

Integrate the approved editable frontend without replacing its design with the old Alacart interface.
Preserve existing backend files for later migration.
Keep a frozen design reference outside public_html.
Verify package scripts before dependency installation.
Start the preview through the existing local environment.
Run its current build and tests.
Do not import the legacy database yet.

## Complete

Write docs/ENVIRONMENT.md and docs/SKILLS_INSTALLED.md.
Record the preview URL and exact launch command.
Update PROJECT.md with actual checks and blocked requirements.
Return the local preview URL, model label, setup result, and next prompt filename.
Stop after task 00. Task 01 belongs to Gemini.


---

File: 01_BROWSER_BASELINE.md

# Task 01: Verify the approved preview

Owner: Gemini in Antigravity. Preferred model: Gemini 3.8 Flash.
Escalation: Gemini 3.1 Pro for unresolved visual or interaction defects.
Prerequisite: Task 00 provides a working local preview URL.

Read AGENTS.md, PROJECT.md, and chef-workflow/CONTRACT.md.
Read chef-workflow/docs/DESIGN_LOCK.md.
Use Impeccable and web-design-guidelines for this review.
Use Antigravity Chrome as the primary browser tool.
Verify skill discovery in this client and update its pending status.
Do not change application files during this baseline review.

## Browser evidence

Open the local preview URL recorded in PROJECT.md.
Check widths of 320, 390, 768, and 1440 pixels.
Check the initial header, scrolled header, category navigation, product details, WiFi, and feedback form.
Check screenshots and DOM state together.
Do not infer visual quality from DOM text alone.
Verify that the logo starts left and reaches the header's actual center.
Check header controls for collisions at every tested width.
Check that WiFi remains visible after scrolling.
Check square close icons and touch target dimensions.
Verify all 24 products and four categories.
Test Turkish dotted and dotless search terms.
Check Turkish, English, Arabic, dark mode, keyboard navigation, and reduced motion.
Open and close each dialog twice, including a rapid repeat.
Verify Escape, focus containment, and return focus in the real browser.
Check blocked browser storage without clearing unrelated user data.

## Expected preview limits

Feedback submission is not connected yet.
WiFi values are awaiting owner confirmation.
Ingredient details and calories remain hidden until verified.
Do not classify these documented preview limits as broken production features.
Record them as pending integration requirements.

## Handoff

Save a small screenshot set under docs/evidence/01/.
Record viewport, theme, language, URL, and tested commit or file hashes.
Write docs/reviews/01-browser-baseline.md.
Give each confirmed defect an ID, reproduction steps, expected behavior, and actual behavior.
Separate release blockers from small visual defects.
Keep the approved design direction unchanged.
Update PROJECT.md and list the evidence paths.
If blockers exist, route them to R1_REPAIR.md before task 02.
Otherwise set task 02 as the next action.


---

File: 02_DATABASE.md

# Task 02: Build the database foundation

Owner: Codex. Preferred model: GPT-6.1 Sol. Effort: High for schema decisions, then default.
Prerequisite: Task 01 has no unresolved blocking defects.

Read AGENTS.md, PROJECT.md, and chef-workflow/CONTRACT.md.
Read the database findings in chef-workflow/docs/SOURCE_REVIEW.md.
Use Ponytail. Use Context7 only for unresolved driver or version details.
Build a working local database foundation and documented API contract.

## Inspect the actual schema

Inspect the supplied SQL schema privately before executing any statement.
Identify destructive statements, user grants, triggers, events, definers, and external file operations.
Do not execute unrelated SQL administration statements.
The supplied dump reports MariaDB 11.8.9 and utf8mb4_uca1400_ai_ci.
Verify compatibility with the installed server and the intended Hostinger server.
Choose a supported utf8mb4 collation. Do not apply blind global replacements.
Check index lengths, foreign keys, and Turkish text comparisons after conversion.
The reviewed tables include admins, branches, campaigns, categories, feedbacks, meta, and products.
Compare those tables with HVK's current PHP queries.

## Separate data sources

Create a dedicated Chef development database.
Keep HVK customer records, password hashes, and venue settings out of Chef seed data.
Use a separate disposable database for legacy upgrade tests when required.
Use synthetic records for those tests.
Seed Chef's 24 approved products and four categories from the approved frontend data.
Preserve approved prices and serving descriptions.
Keep seed execution repeatable without duplicating products or replacing staff edits.
Record mappings between frontend string IDs and database IDs.

## Schema and runtime

Preserve suitable legacy tables and columns instead of replacing the whole schema.
Add versioned migrations with explicit prerequisites and failure recovery.
Keep schema creation and updates outside public requests.
Use InnoDB transactions and prepared PDO queries.
Keep prices in DECIMAL fields and document currency and rounding rules.
Add nullable ingredients, calories per portion, and verification fields.
Support Turkish, English, and Arabic without guessing missing recipe content.
Resolve the legacy required customer_name field without inventing a customer name.
Prefer a compatible nullable migration when the new feedback form does not request names.
Plan persistent throttling, upload ownership, and recoverable cleanup with the smallest sufficient schema.
Keep application credentials separate from migration credentials where hosting permits it.

## API contract

Define one public menu shape matching the approved frontend.
Document products/items, category/categoryId, localized strings, image URLs, prices, and nullable nutrition fields.
Define admin authorization, validation errors, and menu version updates.
Define feedback topics, rating, message, contact, language, image, and idempotency behavior.
Keep server and client limits identical.
Use 2000 message characters and 150 contact characters unless source compatibility requires a documented change.
Update the preview's contact limit to match the contract during its integration task.

## Verify and finish

Test installation on an empty Chef database.
Test migration from the sanitized legacy schema.
Test repeated migration execution and failed migration recovery.
Verify row counts, price precision, foreign keys, and Turkish and Arabic round trips.
Write docs/DATABASE.md, docs/API_CONTRACT.md, and a safe configuration example.
Update PROJECT.md with server version, migration version, checks, and next task 03.


---

File: 03_ADMIN_AUTH.md

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


---

File: 04_MEDIA_LIFECYCLE.md

# Task 04: Build safe upload and deletion helpers

Owner: Codex. Preferred model: GPT-6.1 Sol. Effort: High.
Escalation: GPT-6 Astra only for unresolved consistency or security decisions, when available.
Prerequisite: Tasks 02 and 03 pass.

Read AGENTS.md, PROJECT.md, and chef-workflow/CONTRACT.md.
Read docs/DATABASE.md and docs/API_CONTRACT.md.
Inspect HVK's handle_image_upload and delete_image_file helpers.
Use Ponytail, code-security, and focused Semgrep rules.
Implement shared helpers before wiring product, campaign, and feedback uploads.

## Validation and storage

Accept JPEG, PNG, and WebP raster uploads only.
Keep the trusted logo SVG separate from user upload handling.
Check upload status, byte size, detected MIME, dimensions, and pixel count before full decoding.
Use initial limits of 4 MiB and 12 megapixels.
Account for the verified PHP memory limit and image decoder requirements.
Re-encode accepted images to a supported fixed output format.
Use random server filenames and fixed safe extensions.
Fail safely if encoding fails. Never fall back to the original filename extension.
Keep original filenames as optional escaped metadata only.
Keep feedback images outside public_html.
Serve private images through an authenticated authorization check.
Store product and campaign images in controlled public media directories.
Disable directory listing and executable file handling for public uploads.
Verify actual server behavior instead of assuming a filename rule prevents execution.
Bound thumbnail dimensions and strip unnecessary metadata.

## Consistent file operations

Write new uploads into a controlled temporary location.
Record ownership and final storage only after validation succeeds.
Coordinate database writes with file placement and explicit failure cleanup.
Do not pretend that database transactions make filesystem changes atomic.
Use a small pending-cleanup record or equivalent recoverable state.
Keep failed cleanup visible and retryable.
Keep the previous product image until its replacement is safely committed.
Remove abandoned new files after failed database inserts.
Reject traversal paths, symlinks, and paths outside the canonical storage root.
Verify ownership and shared references before deleting a file.
Do not accept a deletion path directly from a browser request.
Use a stored record identifier to resolve the permitted file.
Make repeated deletion safe.
Report complete deletion only when the record and its exclusively owned files are gone.
Include thumbnails and derived copies in the ownership and deletion rules.

## Failure tests

Test valid files, forged MIME, double extensions, oversized files, huge dimensions, and malformed images.
Test database failure after upload and storage failure before commit.
Test image replacement failure without losing the previous image.
Test repeated deletion, unrelated file references, traversal, and symlinks where the platform permits them.
Test simulated unlink failure and successful retry.
Confirm unauthenticated private image requests fail.
Record filesystem and database evidence for each lifecycle test.
Write docs/MEDIA_LIFECYCLE.md and docs/reviews/04-media.md.
Update PROJECT.md and set task 05 as the next action.


---

File: 05_ADMIN_CATALOG.md

# Task 05: Adapt the admin catalog

Owner: Codex. Preferred model: GPT-6.1 Sol. Effort: default or Medium.
Prerequisite: Authentication, database, and media helpers pass their checks.

Read AGENTS.md, PROJECT.md, and chef-workflow/CONTRACT.md.
Read docs/API_CONTRACT.md and docs/MEDIA_LIFECYCLE.md.
Read chef-workflow/docs/DESIGN_LOCK.md.
Use Ponytail and Frontend Design for the matching admin interface.
Use Humanizer only when editing visible interface copy.
Adapt the existing HVK admin. Preserve its required functions.

## Admin interface

Use the Chef logo, approved colors, and restrained typography.
Keep navigation clear for products, categories, prices, venue settings, campaigns, and feedback.
Avoid decorative charts without actual operational data.
Replace the runtime Tailwind CDN with a reproducible local stylesheet or existing build.
Keep styling changes separate from authentication and validation logic.
Make tables usable on phones without clipping action controls.
Use visible field labels, useful validation messages, and clear save states.
Preserve entered values after validation errors.
Prevent duplicate submissions and warn before losing unsaved form changes.
Confirm destructive actions with the affected record name and consequence.

## Catalog functions

Preserve product creation, editing, deletion, category moves, and image replacement.
Preserve category creation, editing, deletion, descriptions, and images.
Preserve product order and category order.
Validate complete reorder requests, unique IDs, and correct category membership.
Provide keyboard buttons when drag sorting is available.
Handle dependent products explicitly before category deletion.
Preserve working bulk price and product import routes for task 07.
Use the shared media service for every image change.
Apply authorization, CSRF, prepared queries, and output escaping consistently.

## New product fields

Add editable ingredients for the supported languages.
Add nullable calories per portion with a clear unit label.
Add explicit verification controls for ingredients and calories.
Keep missing values empty and unverified by default.
Never convert an empty calorie field to zero.
Reject negative, nonnumeric, or structurally invalid nutrition values.
Keep serving descriptions separate from verified ingredient lists.
Preserve existing visibility controls when present.
Do not introduce warehouse stock, orders, or payment logic.

## Verify

Test complete CRUD operations against the local Chef database.
Test duplicate submissions, invalid prices, missing categories, malformed IDs, and failed image replacements.
Test category deletion without orphaned records or images.
Test verified and unverified ingredient and calorie states.
Test ordering after category moves and deletions.
Add an explicit local test administrator without publishing credentials.
Write docs/reviews/05-catalog.md and update the function parity table.
Update PROJECT.md and hand off to Gemini for task 06.


---

File: 06_ADMIN_BROWSER.md

# Task 06: Review the admin in Chrome

Owner: Gemini in Antigravity. Preferred model: Gemini 3.8 Flash.
Escalation: Gemini 3.1 Pro for difficult layout or accessibility diagnosis.
Prerequisite: Task 05 provides working local admin access.

Read AGENTS.md, PROJECT.md, and chef-workflow/CONTRACT.md.
Read docs/reviews/05-catalog.md and the current function parity table.
Use Impeccable, web-design-guidelines, and Antigravity Chrome.
Use the local test administrator without revealing credentials in chat or screenshots.
Test the local database only.

## Exercise actual workflows

Create one disposable category and two disposable products.
Edit names, translations, prices, serving details, ingredients, and calories.
Check empty calorie fields and verification controls.
Upload and replace a test product photo.
Change product order, category order, and product category.
Test invalid prices, failed saves, repeated clicks, and browser refresh.
Verify that saved values persist after logout and login.
Check category deletion with dependent products.
Remove the disposable records and confirm the recorded cleanup result.
Do not delete the Chef seed records.

## Visual and access review

Check 390, 768, and 1440 pixel widths.
Check navigation, labels, focus, error placement, contrast, and button hit areas.
Verify that the admin fits the approved Chef brand.
Check that validation errors preserve the user's input.
Check logged-out access to representative direct admin URLs.
Inspect request status codes for failed saves.
Do not count a success toast as proof of a database write.
Use a read-only database check when the saved state remains uncertain.

## Evidence and routing

Do not change application source during this review.
Write docs/reviews/06-admin-browser.md with defect IDs and exact reproduction steps.
Save representative screenshots under docs/evidence/06/.
Record the current commit or file hashes.
Classify blockers separately from visual polish.
Route confirmed defects to R1_REPAIR.md.
Repeat only affected checks after repairs.
Set task 07 as next only when blocking catalog defects are resolved.
Update PROJECT.md and return a compact handoff.


---

File: 07_MENU_INTEGRATION.md

# Task 07: Connect the database menu and venue controls

Owner: Codex. Preferred model: GPT-6.1 Sol. Effort: default or Medium.
Prerequisite: Task 06 has no unresolved catalog blockers.

Read AGENTS.md, PROJECT.md, and chef-workflow/CONTRACT.md.
Read docs/API_CONTRACT.md and the current function parity table.
Use Ponytail. Use Context7 or Motion documentation only for unresolved implementation details.
Preserve the approved guest design while connecting real data.

## Guest menu

Implement the documented public menu endpoint with prepared database queries.
Keep schema migration logic outside the endpoint.
Exclude admin, private feedback, credentials, and internal paths from public responses.
Replace the production frontend's bundled menu data with the real API response.
Keep demonstration fixtures separate from production data loading.
Do not silently show demo products after a failed production request.
Show accessible loading, empty, failed, and retry states.
Validate response shape before rendering.
Render untrusted text without HTML interpretation.
Preserve Turkish search, translations, category filters, dark mode, and dialog behavior.
Show verified ingredients and calories compactly.
Keep null and unverified nutrition hidden.
Preserve all 24 initial Chef products unless an authorized admin changes them.

## Settings and caching

Connect the top WiFi control to saved guest network settings.
Expose only the intended guest network details.
Keep WiFi accessible before and after header movement.
Connect campaign image, enabled state, and existing text behavior.
Use the shared media helpers for campaign replacement and deletion.
Preserve menu version invalidation after every relevant admin mutation.
Document cache keys, ETags, and response headers.
Test that admin changes appear without forcing guests to clear browser storage.
Keep admin, feedback, private images, and personalized responses outside shared caches.
Keep personalized CSRF tokens outside publicly cached menu responses.

## Imports, prices, and translations

Preserve bulk price changes, reset actions, price CSV import, and price CSV export.
Preserve product CSV and JSON import with documented field mapping.
Bound input size, row count, nesting, and supported fields.
Provide validation results before committing invalid or ambiguous imports.
Define atomic import behavior and report exact applied and rejected counts.
Handle Turkish decimal separators without ambiguous price conversion.
Neutralize spreadsheet formulas in exported text fields.
Do not enable arbitrary image URL fetching to repair the legacy import limitation.
Report unsupported remote images clearly or require explicit local uploads.
Keep editable translations and Turkish fallback.
Move external translation requests outside database transactions.
Bound external timeouts and allowed destinations when that feature remains enabled.

## Check and finish

Test actual API reads and admin-to-menu updates.
Test WiFi changes, campaign replacement, category moves, imports, prices, and translation fallback.
Test database outage without false menu data or internal error details.
Verify public responses and cache invalidation after each mutation class.
Update docs/API_CONTRACT.md and docs/reviews/07-integration.md.
Update PROJECT.md and set task 08 as the next action.


---

File: 08_FEEDBACK.md

# Task 08: Connect feedback and prove image deletion

Owner: Codex. Preferred model: GPT-6.1 Sol. Effort: High.
Prerequisite: Tasks 03, 04, and 07 pass.

Read AGENTS.md, PROJECT.md, and chef-workflow/CONTRACT.md.
Read docs/API_CONTRACT.md and docs/MEDIA_LIFECYCLE.md.
Inspect HVK's FeedbackModal, feedback endpoint, and admin feedback page.
Use Ponytail and code-security. Use Humanizer only for short interface messages.

## Guest flow

Preserve the approved feedback window and its compact layout.
Ask for topic, rating, and message.
Keep contact and photo optional.
Use the HVK topics: taste, service, staff, hygiene, and other.
Accept integer ratings from one through five.
Show the recovery message for ratings one and two.
Preserve answers when users move between sections or when submission fails.
Use contract limits in both frontend and backend.
Keep Turkish, English, and Arabic messages complete.
Replace the preview-only form action with a real multipart request.
Remove the preview notice only when persistence works.
Report success only after the server confirms the database write.
Keep failure messages visible and actionable.

## Server controls

Accept the documented method and content type only.
Validate scalar types, allowed topics, rating range, lengths, and image limits.
Reject array values where scalar fields are required.
Use persistent anonymous submission limits and an appropriate CSRF or origin control.
Keep abuse controls independent from CSRF controls.
Do not trust arbitrary forwarded IP headers.
Prevent duplicate records from retries or repeated button clicks.
Use a bounded idempotency mechanism tied to the validated request.
Do not reuse an idempotency key for changed request content.
Return meaningful status codes without internal exception details.
Use the shared private upload service.
Preserve image upload failures instead of silently accepting incomplete feedback.

## Admin inbox

Display topic, rating, message, optional contact, language, time, and authorized image access.
Escape every displayed user value.
Provide useful filtering and bounded pagination.
Protect reads, deletes, exports, and image access with server authorization.
Use the shared deletion lifecycle for feedback and owned image files.
Expose incomplete cleanup and a safe retry action.
Keep deleted images inaccessible during pending cleanup.
Do not copy private feedback into public API responses or Git.

## Required lifecycle proof

Submit disposable feedback with a valid image.
Record its database ID and controlled file identifier without private content.
Confirm that one record and its image exist.
Delete it through the admin panel.
Confirm that its row, original image, and derived files are gone.
Confirm that the old image URL cannot return the image.
Repeat deletion and verify the documented idempotent result.
Simulate database failure and file deletion failure.
Verify orphan cleanup, visible pending state, and successful retry.
Write docs/reviews/08-feedback.md with database and filesystem evidence.
Update PROJECT.md and hand off to Gemini for task 09.


---

File: 09_END_TO_END.md

# Task 09: Verify the complete menu workflow

Owner: Gemini in Antigravity. Preferred model: Gemini 3.8 Flash.
Escalation: Gemini 3.1 Pro for unresolved cross-page behavior.
Prerequisite: Task 08 reports successful persistence and cleanup checks.

Read AGENTS.md, PROJECT.md, and chef-workflow/CONTRACT.md.
Read docs/API_CONTRACT.md and docs/reviews/08-feedback.md.
Use Antigravity Chrome and web-design-guidelines.
Use disposable test records in the local Chef database.
Do not change application code during this review.

## Verify connected behavior

Edit a disposable product in admin and find the change in the guest menu.
Verify name, price, category, image, translations, and order.
Set verified ingredients and calories, then verify their compact public display.
Clear calories and remove verification, then confirm those details disappear.
Change the guest WiFi settings and inspect the top-header dialog.
Enable, replace, and disable a test campaign.
Verify each change in a fresh browser context and the existing guest session.
Check that caching does not retain obsolete menu values.
Test imports and bulk prices on disposable records with known expected totals.

## Feedback proof

Submit feedback without contact or image.
Submit feedback with an image and optional synthetic contact details.
Check the request status and the actual admin inbox record.
Inspect the saved topic, rating, language, and image association.
Confirm database state through a bounded read-only query when needed.
Verify unauthenticated access cannot retrieve the private image.
Delete the test feedback in admin.
Use a bounded filesystem check to confirm its owned image files disappeared.
Verify the database row disappeared and the former image request fails.
Do not infer physical deletion from the DOM or a success notification.

## Failure paths

Test a rejected image, oversized message, missing topic, and invalid rating.
Test repeated clicks and a controlled request retry.
Test a temporary failed save without losing the user's message.
Test admin logout, expired sessions, and direct protected URLs.
Check the browser console and relevant network responses.
Do not run brute-force floods or uncontrolled load tests.

## Handoff

Restore changed test settings and remove disposable records.
Save representative screenshots under docs/evidence/09/.
Write docs/reviews/09-end-to-end.md with reproduction steps and evidence.
List each defect by severity and affected task.
Update PROJECT.md.
Route blockers through R1_REPAIR.md, then repeat their failed checks.
Set task 10 as next when core workflows pass.


---

File: 10_SECURITY_REVIEW.md

# Task 10: Review and fix release security risks

Owner: Codex. Preferred model: GPT-6.1 Sol. Effort: High.
Escalation: Available GPT-6 Astra for a specific unresolved high-impact finding.
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
Build a Content Security Policy from the application's actual resources.
Verify that the policy does not break the approved interface or Motion behavior.
Prefer local assets and explicit directives over broad unsafe allowances.
Check actual response headers under the deployment-compatible local server.
Keep Hostinger-specific header verification listed for the later authorized deployment check.
Check configuration, SQL, backups, logs, repository metadata, and error pages for disclosure.
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


---

File: 11_PERFORMANCE_A11Y.md

# Task 11: Verify performance and accessibility

Owner: Gemini in Antigravity. Preferred model: Gemini 3.8 Flash.
Escalation: Gemini 3.1 Pro for unclear visual or interaction defects.
Prerequisite: Task 10 security fixes are complete and the local build runs.

Read AGENTS.md, PROJECT.md, and chef-workflow/CONTRACT.md.
Read the design lock and the latest security report.
Use Antigravity Chrome for interaction checks.
Use Chrome DevTools MCP for measured performance and network evidence.
Use Impeccable and web-design-guidelines for focused visual and accessibility review.
Do not change application source during this review.

## Performance

Measure the actual PHP-backed guest menu.
Record browser version, URL, device profile, network profile, CPU settings, and cache state.
Run three comparable mobile measurements and report median lab values.
Check LCP, CLS, blocking work, transferred bytes, and failed requests.
Target LCP at most 2.5 seconds and CLS at most 0.1.
Use 200 milliseconds as an INP field target when real field data exists.
Do not label Lighthouse TBT or one scripted interaction as field INP.
Use initial budgets of 80 KiB compressed guest JavaScript and 500 KiB first-viewport transfer.
Report the measured build separately from the earlier 12,550-byte preview baseline.
Inspect image dimensions, loading priority, lazy loading, fonts, and cache headers.
Check whether server queries or translation calls delay first content.
Check layout stability during image loading and logo movement.
Record specific optimization candidates with measured impact or a clear hypothesis.
Avoid decorative optimization that does not address a measured problem.

## Accessibility and visual consistency

Check 320, 390, 768, and 1440 pixel widths.
Check 200% zoom, keyboard operation, visible focus, and Escape behavior.
Check native dialog focus containment and return focus.
Check screen-reader names and dynamic status announcements through the accessibility tree.
Check Turkish, English, Arabic, light mode, and dark mode.
Check reduced motion and consistent close buttons.
Check text contrast, missing-image behavior, and control spacing.
Compare the result against the approved reference screenshots.
Report functional changes that unintentionally changed the visual design.

## Handoff

Save concise metrics and representative screenshots under docs/evidence/11/.
Write docs/reviews/11-performance-a11y.md.
Separate measured failures, unmeasured risks, and optional polish.
Route required fixes through R1_REPAIR.md.
Repeat affected measurements with the same conditions after fixes.
Update PROJECT.md and permit task 12 only when required checks pass.


---

File: 12_RELEASE.md

# Task 12: Prepare the Hostinger release package

Owner: Codex. Preferred model: GPT-6.1 Sol. Effort: default or Medium.
Prerequisite: Required browser, functional, security, and performance checks pass.

Read AGENTS.md, PROJECT.md, and chef-workflow/CONTRACT.md.
Read the latest review reports and function parity table.
Use Ponytail and focused code-security checks.
Prepare the release without uploading it to production.

## Complete parity

Confirm product and category CRUD, ordering, images, and category moves.
Confirm bulk prices, price reset, CSV export, and CSV or JSON imports.
Confirm guest WiFi, campaigns, translations, search, and menu cache invalidation.
Confirm ingredient and calorie editing with explicit verification.
Confirm login, logout, recovery, feedback inbox, and private image handling.
Confirm physical feedback image deletion with recorded evidence.
Resolve missing required functions before packaging.

## Reproducible build

Use the existing lockfile and verified local runtime versions.
Build assets locally. Do not require Node on Hostinger for normal requests.
Package only required runtime files under public_html.
Include required dependency license notices.
Exclude source archives, private data, tests, reports, node_modules, credentials, and repository metadata.
Keep private configuration, private uploads, logs, and migrations outside the public document root.
Provide placeholders instead of real production secrets.
Document the required directory layout and ownership assumptions.
Verify filename case and path behavior for the Linux server.
Make the intended deployment base path configurable.
Keep admin and private endpoints outside CDN caching rules.

## Installation and recovery

Create versioned schema installation files and non-destructive upgrade migrations.
Document the verified PHP version, database compatibility, and required extensions.
Document the MariaDB collation compatibility decision.
Provide phpMyAdmin instructions when command-line migration access is unavailable.
Do not leave an unauthenticated installer or diagnostic page in public_html.
Document private configuration placement and owner credential creation.
Document database, file, and configuration backup steps.
Document rollback limits when a migration changes stored data.
Document pending cleanup retries and any required scheduled task.
Do not assume a Hostinger MCP tool can upload files or deploy this project.
Keep manual File Manager deployment instructions complete.

## Fresh installation test

Extract the generated package into a new local directory.
Use a new disposable database and the documented installation steps.
Run application smoke tests against the extracted files.
Check guest menu reads, admin login, one catalog edit, and feedback with image deletion.
Verify denied access to private files using HTTP requests.
Verify no real credentials or customer records appear in the release or Git diff.
Record package hashes and the tested source commit or file hashes.

## Deliver

Produce public_html.zip, chef-private-setup.zip, and INSTALL_TR.md.
Provide a short backup and rollback guide.
Create RELEASE_MANIFEST.md with contents, hashes, runtime requirements, and test evidence.
Mark unresolved hosting checks explicitly.
Update PROJECT.md with release status and the exact remaining deployment action.
Do not call the live deployment complete.
Stop after delivering the local release artifacts.


---

File: R1_REPAIR.md

# Conditional repair prompt

Owner: Codex. Model: GPT-6.1 Sol.
Use default effort for bounded UI fixes and High for security or data consistency fixes.

Read AGENTS.md, PROJECT.md, and chef-workflow/CONTRACT.md.
Read only the defect report named in PROJECT.md and its relevant source files.
Use Ponytail and the skill required by the defect.
Use Frontend Design for approved-style corrections.
Use code-security for authentication, uploads, authorization, and data consistency defects.

Reproduce the reported failure before editing.
Check whether the tested source still matches the current source.
Fix the smallest complete cause.
Preserve the approved design and unrelated behavior.
Do not replace the application architecture for a local defect.
Add a meaningful regression check when the failure affects security, persistence, or repeatable interaction logic.
Run affected checks and record their actual results.
Do not remove failing assertions merely to obtain a pass.
Update the original defect status without deleting its evidence.
Update PROJECT.md with changed files, model, checks, and next reviewer.
Return the task to Gemini for the affected browser checks.
Do not restart unrelated successful work.


---

File: R2_RESUME.md

# Resume after a model or quota change

Read AGENTS.md and PROJECT.md.
Read chef-workflow/CONTRACT.md if this session has not loaded it.
Verify the current repository, branch, commit, and uncommitted changes.
Check that the previous agent has stopped before claiming task ownership.
Read only the active prompt and its named evidence.
Use the current task's skill routing.
Preserve completed changes and recorded decisions.
Recheck results only when their inputs changed or evidence is missing.
Record your actual client and model label.
Continue the first incomplete acceptance check of the active task.
Do not restart the project review or redesign the approved interface.
Update PROJECT.md before ending this session.
