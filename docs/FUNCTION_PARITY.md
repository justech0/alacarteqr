# HVK Function Parity Matrix

Review baseline: 2026-10-13.
Source: HVK PHP admin implementation reviewed in chef-workflow/docs/SOURCE_REVIEW.md.
Requirement: Track implementation, backend integration, and browser verification as distinct statuses.

| Function | HVK Source | Migration Requirement | Implementation Status | Integration Status | Browser Check Status | Evidence Path |
|---|---|---|---|---|---|---|
| Product CRUD & Category Move | admin/products.php | Preserve behavior with stronger input validation | PASS | PASS | PASS | docs/reviews/07-integration.md |
| Category CRUD & Cleanup | admin/categories.php | Check dependent products and physical image cleanup | PASS | PASS | PASS | docs/reviews/06-admin-browser.md, database/tests/catalog-http.mjs (Task 10, Task 12 rerun) |
| Product Order | admin/product-order.php | Preserve category membership and complete-order validation | PASS | PASS | PASS | docs/reviews/06-admin-browser.md, database/tests/catalog-http.mjs (Task 10, Task 12 rerun) |
| Category Order | admin/category-order.php | Add duplicate and complete-order checks | PASS | PASS | PASS | docs/reviews/07-integration.md |
| Bulk Prices & CSV Prices | admin/bulk-prices.php | Preserve import, export (formula neutral), and reset actions | PASS | PASS | PASS | docs/reviews/07-integration.md |
| CSV/JSON Product Import | admin/import-products.php | Bound file size and row count | PASS | PASS | PASS | docs/reviews/07-integration.md |
| WiFi Editing | admin/branches.php | Connect top-header WiFi to saved settings | PASS | PASS | PASS | database/tests/menu-http.mjs, docs/reviews/09-end-to-end.md |
| Campaign Image & Toggle | admin/campaign.php | Keep image replacement and visibility controls | PASS | PASS | PASS | database/tests/menu-http.mjs, docs/reviews/09-end-to-end.md, docs/reviews/12-release.md |
| Menu Cache Versioning | admin/config.php, admin/index.php | Invalidate cached menu data after edits | PASS | PASS | PASS | database/tests/menu-http.mjs, docs/reviews/09-end-to-end.md |
| Multi-language Translations | admin/products.php, admin/categories.php | Preserve editable translations and Turkish fallback | PASS | PASS | PARTIAL (guest TR/EN/AR PASS; admin translation form not browser-checked) | docs/reviews/07-integration.md, docs/reviews/09-end-to-end.md |
| Feedback Inbox & Cleanup | admin/feedbacks.php | Show answers, contact, image, and proven physical image deletion | PASS | PASS | PASS | docs/reviews/08-feedback.md, docs/reviews/09-end-to-end.md, docs/reviews/12-release.md |
| Anonymous Feedback API | admin/api/feedback.php | Validate and persist 3-step feedback flow | PASS | PASS | PASS | docs/reviews/08-feedback.md, docs/reviews/09-end-to-end.md |
| Ingredient & Calorie Verification | admin/products.php | Show values only after explicit verification | PASS | PASS | PASS | docs/reviews/07-integration.md, docs/reviews/09-end-to-end.md |
| Login, Logout & Recovery | admin/login.php, admin/logout.php | Throttled login, session revocation, CLI recovery | PASS | PASS | PASS | docs/reviews/03-auth.md, docs/reviews/09-end-to-end.md, docs/reviews/12-release.md |
| First Owner Creation | none (new) | Create the first owner on a fresh install without test tools | PASS | PASS | N/A (CLI) | database/recover-owner.php --create, docs/reviews/12-release.md |
| Private Image Handling | admin/private-image.php | Serve feedback images only to an authenticated owner | PASS | PASS | PASS | docs/reviews/08-feedback.md, docs/reviews/12-release.md |
