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
