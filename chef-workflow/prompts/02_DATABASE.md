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
Update PROJECT.md with server version, migration version, checks, and next ta   sk 03.
