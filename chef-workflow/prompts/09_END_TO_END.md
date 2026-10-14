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
