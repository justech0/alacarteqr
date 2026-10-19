# HVK Function Parity Matrix

Review baseline: 2026-10-13.
Source: HVK PHP admin implementation reviewed in chef-workflow/docs/SOURCE_REVIEW.md.
Requirement: Track implementation, backend integration, and browser verification as distinct statuses.

| Function | HVK Source | Migration Requirement | Implementation Status | Integration Status | Browser Check Status | Evidence Path |
|---|---|---|---|---|---|---|
| Product CRUD & Category Move | admin/products.php | Preserve behavior with stronger input validation | PASS | NOT RUN | PENDING | docs/reviews/05-catalog.md |
| Category CRUD & Cleanup | admin/categories.php | Check dependent products and physical image cleanup | PASS | NOT RUN | PENDING | docs/reviews/05-catalog.md |
| Product Order | admin/product-order.php | Preserve category membership and complete-order validation | PASS | NOT RUN | PENDING | docs/reviews/05-catalog.md |
| Category Order | admin/category-order.php | Add duplicate and complete-order checks | PASS | NOT RUN | PENDING | docs/reviews/05-catalog.md |
| Bulk Prices & CSV Prices | admin/bulk-prices.php | Preserve import, export (formula neutral), and reset actions | PASS | NOT RUN | PENDING | docs/reviews/05-catalog.md |
| CSV/JSON Product Import | admin/import-products.php | Bound file size and row count | PASS | NOT RUN | PENDING | docs/reviews/05-catalog.md |
| WiFi Editing | admin/branches.php | Connect top-header WiFi to saved settings | PENDING | PENDING | PENDING | chef-workflow/docs/SOURCE_REVIEW.md#L70 |
| Campaign Image & Toggle | admin/campaign.php | Keep image replacement and visibility controls | PENDING | PENDING | PENDING | chef-workflow/docs/SOURCE_REVIEW.md#L71 |
| Menu Cache Versioning | admin/config.php, admin/index.php | Invalidate cached menu data after edits | PASS | NOT RUN | PENDING | docs/reviews/05-catalog.md |
| Multi-language Translations | admin/functions.php | Preserve editable translations and Turkish fallback | PASS | NOT RUN | PENDING | docs/reviews/05-catalog.md |
| Feedback Inbox & Cleanup | admin/feedbacks.php | Show answers, contact, image, and proven physical image deletion | PENDING | PENDING | PENDING | chef-workflow/docs/SOURCE_REVIEW.md#L74 |
| Anonymous Feedback API | admin/api/feedback.php | Validate and persist 3-step feedback flow | PENDING | PENDING | PENDING | chef-workflow/docs/SOURCE_REVIEW.md#L75 |
