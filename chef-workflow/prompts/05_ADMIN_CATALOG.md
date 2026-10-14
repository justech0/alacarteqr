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
