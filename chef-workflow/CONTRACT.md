# Shared execution contract

## Read and act

Read the root AGENTS.md and PROJECT.md before each task.
Read this contract when its version changes or a new session starts.
Read only the selected task and its required references.
Execute the task through its checks. Do not return only a plan.
Use the supplied source files. Verify claims against the current checkout.
Preserve unrelated edits. Never reset the working tree to simplify a task.
Check the current branch, commit, and dirty files before editing.
Use one active writer and one shared project checkout.
Wait for the current agent to finish before transferring ownership.
Do not use simultaneous agents for this workflow.

## Approved product

The user approved chef-onizleme.html on 2026-10-13.
Preserve that preview's design and interaction direction.
Use its editable source as the frontend base.
Read docs/DESIGN_LOCK.md before visual changes.
Use HVK PHP files as the admin function source.
Keep PHP, PDO, and MySQL or MariaDB compatibility.
Use Node only for local builds and development tools.
Do not introduce React, Next.js, Laravel, Supabase, or a production Node server without a demonstrated requirement.
Keep orders, payments, delivery, and customer accounts outside this menu project.
Use the original Chef logo and verified Chef product data.
Keep unavailable nutrition values null.
Never generate or infer ingredients, allergens, or calories.
Keep HVK restaurant records separate from Chef records.

## Language

Reply to the user in Turkish.
Write prompts, technical documents, and diagnostic summaries in English.
Use ASD-STE100 principles for those English texts.
Treat 80% STE as a style target, not a certified score.
Keep each instruction below 20 words.
Keep each description below 25 words.
Use active voice and consistent verbs.
Preserve code, commands, identifiers, and raw logs exactly.
Use complete sentences. Avoid emojis and filler.

## Skills and context

Use docs/SKILL_ROUTING.md and the selected task's skill list.
Load a skill's full instructions only when its task requires them.
Record unavailable tools honestly and use the documented fallback.
Do not install or invoke Superpowers.
Keep compression proxies and automatic memory hooks disabled for this project.
Keep PROJECT.md as the only active progress record.
Keep its current summary below 350 words where practical.
Keep AGENTS.md below 600 words where practical.
Move old execution detail into docs/HISTORY.md without deleting evidence.
Use targeted file searches and bounded reads.
Exclude node_modules, compiled bundles, archives, and SQL rows from routine context loading.
Read private SQL only for a concrete schema or migration need.
Do not paste full files, complete DOM snapshots, or complete logs into chat.
Use one browser tool for routine checks.
Read docs/BROWSER_TESTING.md before any browser check.
Gemini runs visual and DOM checks. Codex runs code, HTTP, and database checks.
Claude runs the independent security review and UI repairs.
Use Context7 only for unresolved version-specific APIs.
Reuse a known result until its inputs change.
Repeat a check only for changed behavior, a failed check, or a required release gate.
Do not promise a token reduction percentage.

## Boundaries

Work locally unless the user explicitly names an authorized remote test environment.
Use a dedicated Chef test database and disposable test uploads.
Do not test writes against the live reference menu or HVK production site.
Keep credentials, private feedback, SQL dumps, logs, and source archives outside public_html.
Keep those files outside Git and shared screenshots.
Never print existing credentials, password hashes, recovery values, or private customer records.
Preserve license notices and useful security comments.
Remove code only after checking its callers, routes, templates, and build use.
Do not disable security checks to make a test pass.
Do not change production credentials during local development.
Do not push secrets or merge the default branch.
Generate test passwords at run time. Never store a valid test password in source, logs, or reports.
Store test credentials only in a private file outside Git and public_html.
Prepare deployment files without uploading them to production.

## Completion and handoff

Record PASS, FAIL, or BLOCKED for each required check.
Attach concise evidence and the tested commit or file hashes.
Compare guest files with current_revision in APPROVED_BASELINE.json. Do not use the original hashes.
Never convert an unrun check into PASS.
Do not advance a dependent task while its required check fails.
Use prompts/R1_REPAIR.md for a confirmed failure, then repeat the affected review.
Update PROJECT.md after each useful checkpoint.
Record task ID, actual client, exact model label, files, tests, blockers, and the next task.
Record unavailable model identity as unknown.
Record the local URLs, server command, database version, and migration version without secrets.
Record an unfinished task before a quota limit or model transfer.
Keep final replies below 180 words unless a blocking issue requires detail.
Return the result, changed paths, check results, and one next action.
Do not include hidden reasoning or lengthy internal deliberation.
