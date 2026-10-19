# Task 09: Verify the complete menu workflow

Owner: Gemini in Antigravity. Preferred model: Gemini 3.8 Flash.
Escalation: Gemini 3.1 Pro for unresolved cross-page behavior.
Prerequisite: Task 08 reports successful persistence and cleanup checks.

Read AGENTS.md, PROJECT.md, and chef-workflow/CONTRACT.md.
Read chef-workflow/docs/BROWSER_TESTING.md and chef-workflow/docs/DESIGN_LOCK.md.
Read docs/API_CONTRACT.md and docs/reviews/08-feedback.md.
Use `browser_subagent` and web-design-guidelines. Follow BROWSER_TESTING.md for DOM probes and test access.
Use disposable test records in the local Chef database.
Do not change application code during this review.

## Verify connected behavior

Edit a disposable product in admin and find the change in the guest menu.
Verify name, price, category, image, translations, and order.
Set verified ingredients and calories, then verify their compact public display.
Clear calories and remove verification, then confirm those details disappear.
Change the guest WiFi settings and inspect the top-header dialog.
Enable, replace, and disable a test campaign.
Verify each change in a fresh guest context and the existing guest session, as BROWSER_TESTING.md defines.
Check that caching does not retain obsolete menu values.
Test imports and bulk prices on disposable records with known expected totals.

## Feedback proof

Submit feedback without contact or image.
Submit feedback with an image and optional synthetic contact details.
Check the request status and the actual admin inbox record.
Inspect the saved topic, rating, language, and image association.
Confirm database state with `php database/tests/readonly-check.php` when needed.
Verify unauthenticated access cannot retrieve the private image.
Delete the test feedback in admin.
Use a bounded filesystem check to confirm its owned image files disappeared.
Verify the database row disappeared and the former image request fails.
Do not infer physical deletion from the DOM or a success notification.

## Design revision 1 regression

Verify each item in DESIGN_LOCK.md, Approved revisions, on the PHP-backed guest menu.
Check dark tokens with computed styles. Check that the logo badge stays white.
Check that search is one bordered box with the translated placeholder.
Check that the language popover shows three SVG flags and changes the language.
Check that the price badge keeps clear space from the card edge.
Scroll through all categories. Check that one category button is active at a time and the rail follows it.
Compare screenshots with docs/evidence/ref-rev1/. Report any unintended visual change.

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
