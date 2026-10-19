# Task 06: Review the admin in the browser

Owner: Gemini in Antigravity. Preferred model: Gemini 3.8 Flash.
Escalation: Gemini 3.1 Pro for difficult layout or accessibility diagnosis.
Prerequisite: Task 05 provides working local admin access.

Read AGENTS.md, PROJECT.md, and chef-workflow/CONTRACT.md.
Read chef-workflow/docs/BROWSER_TESTING.md and chef-workflow/docs/DESIGN_LOCK.md.
Read docs/reviews/05-catalog.md, docs/FUNCTION_PARITY.md, and the "Admin test access" section of docs/ENVIRONMENT.md.
Use Impeccable, web-design-guidelines, and `browser_subagent`.
Read the rendered DOM and the accessibility tree. Pair each result with a screenshot.
Test the local database only. Do not change application source.

## Step 0: Guest reference set

Recompute the guest file hashes. Compare them with current_revision in APPROVED_BASELINE.json.
Stop and report if they differ. Task 05 must not change guest files.
Open the guest menu at 390 and 1440 pixels in light and dark mode.
Capture Turkish and Arabic views. Save them under docs/evidence/ref-rev1/.
Verify each item in DESIGN_LOCK.md, Approved revisions, with a DOM probe.
Later tasks compare against this set.

## Access

Read the credentials file named in docs/ENVIRONMENT.md.
Type the value into the login field through the DOM. Never echo it.
Take no screenshot while the password field holds a value.
Confirm saved state with `php database/tests/readonly-check.php`. Do not use raw SQL.

## Exercise actual workflows

Create one disposable category and two disposable products.
Edit names, translations, prices, serving details, ingredients, and calories.
Check empty calorie fields. Confirm the saved value is NULL, not zero.
Check ingredient and calorie verification controls. Confirm each timestamp rule.
Upload and replace a test product photo. Confirm the old file disappears.
Change product order and category order with the keyboard buttons.
Try drag sorting as a best-effort check. Mark a failed attempt UNVERIFIED.
Change a product category. Confirm the order of both categories.
Submit invalid prices, malformed values, and missing fields. Confirm the form keeps entered values.
Double-click save. Confirm exactly one record exists.
Open one edit form in two tabs. Submit both. Confirm the second submission is rejected and keeps its values.
Edit a field, then leave the page. Confirm the unsaved-change warning appears.
Open every delete dialog through the DOM hooks. Confirm it names the record and the result.
Verify that saved values persist after logout and login.
Check category deletion with dependent products. Confirm nothing changes without confirmation.
Confirm the recorded cleanup result after deletion.
Remove the disposable records. Do not delete the Chef seed records.

## Visual and access review

Check 390, 768, and 1440 pixel widths in light and dark mode.
Run every standard DOM probe from BROWSER_TESTING.md on each admin page.
Check navigation groups, labels, focus, error placement, contrast, and hit areas.
Check that tables become cards on phones and keep every action visible.
Check that the admin fits the approved Chef brand and the revision 1 tokens.
Check logged-out access to representative direct admin URLs.
Inspect request status codes for failed saves with DevTools MCP.
Do not count a success message as proof of a database write.
Use readonly-check.php when the saved state remains uncertain.

## Evidence and routing

Write docs/reviews/06-admin-browser.md with defect IDs and exact reproduction steps.
Include one row per check in the BROWSER_TESTING.md record format.
Save representative screenshots under docs/evidence/06/.
Record the current commit or file hashes.
Classify blockers separately from visual polish.
Route confirmed defects to R1_REPAIR.md.
Repeat only affected checks after repairs.
Rebuild the test database with the documented command to rotate the password.
Update docs/FUNCTION_PARITY.md Browser Check Status only for verified rows.
Set Task 07 as next only when blocking catalog defects are resolved.
Update PROJECT.md and return a compact handoff.
