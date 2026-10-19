# Task 12: Prepare the Hostinger release package

Owner: Codex. Preferred model: GPT-6.1 Sol. Effort: default or Medium.
Prerequisite: Required browser, functional, security, and performance checks pass.

Read AGENTS.md, PROJECT.md, and chef-workflow/CONTRACT.md.
Read the latest review reports and function parity table.
Use Ponytail and focused code-security checks.
Prepare the release without uploading it to production.

## Complete parity

Confirm product and category CRUD, ordering, images, and category moves.
Confirm bulk prices, price reset, CSV export, and CSV or JSON imports.
Confirm guest WiFi, campaigns, translations, search, and menu cache invalidation.
Confirm ingredient and calorie editing with explicit verification.
Confirm login, logout, recovery, feedback inbox, and private image handling.
Confirm physical feedback image deletion with recorded evidence.
Resolve missing required functions before packaging.

## Reproducible build

Use package-lock.json from Task 05 and verified local runtime versions.
Build assets locally. Do not require Node on Hostinger for normal requests.
Package only required runtime files under public_html.
Include required dependency license notices.
Exclude source archives, private data, tests, reports, node_modules, credentials, and repository metadata.
Exclude workflow and agent files: chef-workflow, .agents, .impeccable, .vscode, docs/evidence, and test credentials.
Exclude legacy HVK files: hvk/assets, hvk/components, hvk/index.html, hvk/admin/uploads, hvk/logo.jpeg, and hvk/thk.png.
Exclude preview-era files: chef-onizleme.html, root app.js, style.css, menu-data.js, menuler, and cheflogo.pdf.
Keep a legacy file only when Task 04 evidence shows that Chef owns it.
List every excluded path group in RELEASE_MANIFEST.md.
Keep private configuration, private uploads, logs, and migrations outside the public document root.
Provide placeholders instead of real production secrets.
Document the required directory layout and ownership assumptions.
Verify filename case and path behavior for the Linux server.
Make the intended deployment base path configurable.
Keep admin and private endpoints outside CDN caching rules.

## Installation and recovery

Create versioned schema installation files and non-destructive upgrade migrations.
Document the verified PHP version, database compatibility, and required extensions.
Document the MariaDB collation compatibility decision.
Provide phpMyAdmin instructions when command-line migration access is unavailable.
Do not leave an unauthenticated installer or diagnostic page in public_html.
Document private configuration placement and owner credential creation.
Document database, file, and configuration backup steps.
Document rollback limits when a migration changes stored data.
Document pending cleanup retries and any required scheduled task.
Do not assume a Hostinger MCP tool can upload files or deploy this project.
Keep manual File Manager deployment instructions complete.

## Fresh installation test

Extract the generated package into a new local directory.
Use a new disposable database and the documented installation steps.
Run application smoke tests against the extracted files.
Check guest menu reads, admin login, one catalog edit, and feedback with image deletion.
Verify denied access to private files using HTTP requests.
Verify no real credentials or customer records appear in the release or Git diff.
Record package hashes and the tested source commit or file hashes.

## Deliver

Produce public_html.zip, chef-private-setup.zip, and INSTALL_TR.md.
Provide a short backup and rollback guide.
Create RELEASE_MANIFEST.md with contents, hashes, runtime requirements, and test evidence.
Mark unresolved hosting checks explicitly.
Update PROJECT.md with release status and the exact remaining deployment action.
Do not call the live deployment complete.
Stop after delivering the local release artifacts.
