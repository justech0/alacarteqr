# Task 05: Adapt the admin catalog

Owner: Codex. Preferred model: GPT-6.1 Sol. Effort: High.
Prerequisite: Tasks 02, 03, and 04 pass.
Gate: Do not start Task 06 before this task passes.

Read AGENTS.md, PROJECT.md, and chef-workflow/CONTRACT.md.
Read docs/API_CONTRACT.md, docs/DATABASE.md, and docs/MEDIA_LIFECYCLE.md.
Read chef-workflow/docs/DESIGN_LOCK.md and chef-workflow/docs/BROWSER_TESTING.md.
Use Ponytail, Frontend Design, and code-security.
Use Humanizer only for new visible Turkish interface copy.
Adapt the existing HVK admin. Preserve its required functions.
Do not run browser or visual checks. Gemini performs them in Task 06.
Record IN_PROGRESS in PROJECT.md after each stage. A quota stop must not lose progress.

## Stage 0: Gate and scope

Verify Task 02, 03, and 04 evidence and their recorded hashes again.
Recompute hashes of index.html, chef-onizleme.html, and public_html/index.html.
Compare them with current_revision in APPROVED_BASELINE.json. Do not use the original hashes.
Record dirty guest files in PROJECT.md. Do not stage, revert, or edit them.
Do not change the guest interface. The three hashes must match at the end.
Do not add a schema migration. The product columns exist. Keep schema version 2026101501.
Do not start Task 07 work. Preserve the bulk price and product import contracts.

## Stage 1: Admin interface shell

Pin Tailwind 3.4.17 as a development dependency. Commit package-lock.json.
Build a local admin.css from the Tailwind classes. Commit the output.
Add one npm script that rebuilds admin.css. Compare its hash with the committed file.
Remove cdn.tailwindcss.com and Google Fonts from header.php, login.php, and every admin page.
Remove remote texture and gradient requests. Rendered admin pages must request only same-origin files.
Copy the approved Chef SVG logo into the admin asset directory.
Replace the legacy logo in header.php, login.php, and the application logo_url setting.
Reuse the guest design tokens from DESIGN_LOCK.md, Approved revisions. Do not invent new grays.
Use #176023 only for fills with white text. Use #5BC46C for dark-theme links, focus, and active states.
Keep text contrast at 4.5:1 or higher in both themes.
Use system fonts. Use a plain serif for headings and sans-serif for controls.
Group shared header navigation as Catalog, Venue, Campaign, and Feedback.
Make tables become cards on phones. Keep every action control visible.
Add shared admin.js. Disable the save button while a form submits.
Warn about unsaved changes on edit forms.
Replace every native confirm, alert, and prompt call with an in-page `<dialog>`.
Cover products.php, bulk-prices.php, and feedbacks.php. Leave their server logic unchanged.
Each delete dialog names the record and states the result, including dependent counts.
Add the stable test hooks from docs/BROWSER_TESTING.md to every admin form, row action, and dialog.
Show save results in one `role="status"` region. Show field errors in `role="alert"` beside the field.
Give every field a visible label. Keep entered values after a validation error.
Write new visible Turkish text with Humanizer.

## Stage 2: Catalog behavior

Add a single-use `submission_token` to every mutation form. Keep it separate from the CSRF token.
Store tokens in the session. Expire each token after 30 minutes. Keep at most 20 per session.
Reject a reused or expired token with a clear message. Keep the entered values.
After a failed validation, keep safe scalar values in session form state. Clear them after display.
Never store files or sensitive values in session form state.

### Validation

Accept product names of 1 to 200 characters.
Accept category names of 1 to 150 characters.
Accept at most 5000 characters in each description and each ingredient field. Count UTF-8 characters.
Accept prices from 0.00 to 99999999.99 with at most two decimals.
Accept IDs only as canonical positive decimal integers. Reject `01`, `+1`, `1.0`, spaces, and overflow.
Check that the category exists inside the mutation transaction before any change.

### Ingredients and calories

Show `ingredients`, `ingredients_en`, and `ingredients_ar` as separate editable fields.
Store an empty ingredient value as `NULL`.
Store an empty calories value as `NULL`. Never convert it to zero.
Accept calories only as whole numbers from 0 to 65535. Reject decimals, exponents, signs, and text.
Use separate verification controls for ingredients and calories.
Without the control selected, set the matching timestamp to `NULL`.
Keep the existing timestamp when the verified value did not change.
Refresh the timestamp when the value changed and the control is selected.
Reject ingredient verification when all three ingredient fields are empty.
Reject calorie verification when calories are empty.
Keep serving description fields separate from ingredient fields.
Do not add stock, orders, or payment logic. Keep existing visibility controls.

### Data consistency

Lock owner rows in product create, update, move, and delete. Lock category rows before product rows.
Keep the old image and owner reference when an image replacement fails.
Normalize sort order of every affected category after a move or delete.
Lock the category and its products before category deletion.
When products depend on a category, require `delete_dependents=1`.
Without that value, change no database row and no file. Show the dependent count.
With that value, delete product and category media through the shared media service.
Normalize category order after category deletion.
Validate reorder requests as a complete set with unique IDs and correct category membership.
Keep the `order[]` request format.
Add keyboard buttons that move an item up or down. Name each button with the item name.
Announce each reorder result in the status region.
Increase menu_version inside the same transaction as each visible mutation.
Use the shared media service for every image change.
Apply authorization, CSRF, prepared queries, and output escaping on every touched route.

### Form contract

Add nutrition fields, verification fields, and `submission_token` to product POST requests.
Add `delete_dependents` and `submission_token` to category delete POST requests.
Do not change the public menu API shape.

## Stage 3: Test access and checks

Replace every hardcoded test password in database/tests. This includes setup-auth-test.php, auth-http.mjs, and media-http.mjs.
Generate the administrator password at run time with at least 24 random characters.
Write it only to a credentials file in the private test root. Apply the same ACL as the private config.
Never print, log, or report the password. Print the file path only.
Add `*.credentials` to .gitignore. Update all tests to read that file.
Add database/tests/readonly-check.php with fixed named checks. Examples: product state, category order, media file presence, row counts.
Each check returns bounded JSON. Accept no raw SQL. Use a SELECT-only database user when setup can create one.
Document the admin URL, launch command, credentials file path, and rebuild command in docs/ENVIRONMENT.md under "Admin test access".
Verify the login page returns HTTP 200 at that URL.

Use a new isolated `chef_alacarte_test` database and disposable storage.
Test CRUD, duplicate submit, invalid price, missing category, and malformed IDs.
Test nonce reuse, nonce limit, and nonce expiry. Inject a clock parameter. Add no test switch that works in production.
Test empty, unverified, and verified nutrition states. Check each timestamp rule.
Test image replacement failure and category cleanup on disk and in the database.
Test move, delete, and reorder sequences. Check the final order values.
Run regression tests for the bulk price and product import routes.
Repeat the authentication and media HTTP tests with the new credential flow.
Fetch each rendered admin page over HTTP. Assert no external host, no native dialog call, labeled inputs, and the required hooks.
Run PHP lint on changed files, the admin.css rebuild comparison, and focused Semgrep.
Confirm the three guest file hashes still match current_revision.
Record unrun checks as NOT RUN. Never convert them to PASS.

## Handoff

Write docs/reviews/05-catalog.md. Store logs and hashes under docs/evidence/05/. Add no screenshots.
In docs/FUNCTION_PARITY.md, set Implementation and Integration to PASS only for verified Task 05 rows.
Leave Browser Check Status at PENDING. Task 06 owns it.
Update PROJECT.md. Set Task 05 to PASS and Task 06 to Pending.
Name the docs/ENVIRONMENT.md section "Admin test access" and the credentials file path. Never include the password.
Name Gemini as the next owner and 06_ADMIN_BROWSER.md as the next prompt.
