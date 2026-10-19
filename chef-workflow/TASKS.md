# Task sequence

Run one selected prompt at a time.
The user chooses the model through the client.
A prompt cannot change the active model by itself.
The agent updates the root PROJECT.md after each task.

| ID | Owner | Prompt | Completion evidence |
|---|---|---|---|
| 00 | Codex | 00_SETUP.md | Verified environment, skills, approved source, and local preview |
| 01 | Gemini | 01_BROWSER_BASELINE.md | Browser checks and frozen reference screenshots |
| 02 | Codex | 02_DATABASE.md | Schema, migration tests, seed, and API contract |
| 03 | Codex | 03_ADMIN_AUTH.md | Authentication, authorization, sessions, and configuration tests |
| 04 | Codex | 04_MEDIA_LIFECYCLE.md | Upload and deletion failure tests |
| 05 | Codex | 05_ADMIN_CATALOG.md | Admin CRUD, ordering, ingredients, and calories |
| 06 | Gemini | 06_ADMIN_BROWSER.md | Actual admin workflows and browser evidence |
| 07 | Codex | 07_MENU_INTEGRATION.md | Database menu, WiFi, campaigns, imports, and cache behavior |
| 08 | Codex | 08_FEEDBACK.md | Persistent feedback and proven physical image deletion |
| 09 | Gemini | 09_END_TO_END.md | Guest and admin workflows verified together |
| 10 | Claude (Fallback: Codex) | 10_SECURITY_REVIEW.md | Reviewed findings, fixes, and regression evidence |
| 11 | Gemini | 11_PERFORMANCE_A11Y.md | Measured performance and browser accessibility review |
| 12 | Codex | 12_RELEASE.md | Fresh installation test and clean release packages |

R1_REPAIR.md handles confirmed defects between stages.
R2_RESUME.md resumes interrupted work without repeating the full analysis.
These are conditional helpers, not two mandatory extra stages.

## Gate rules

Review prompts may use disposable local test records.
They must not edit application source.
Keep a failed check attached to the same task until its repair passes.
Do not proceed merely because the agent completed its written response.
Do not require another design approval for the already-approved visual direction.
Ask only when a missing business value or blocked operation changes the implementation.
Keep unknown business values unavailable until the owner supplies them.

## Handoff format

Use this short record in PROJECT.md.

```text
Task:
Status: PASS | FAIL | BLOCKED | IN_PROGRESS
Owner and actual model:
Branch and tested commit:
Changed files:
Checks and evidence:
Open defect IDs:
Local URLs and launch command:
Test credential file path (never its content):
Next prompt:
```

Exclude credentials, private feedback, and full logs.
Store detailed history under docs/HISTORY.md.
