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
