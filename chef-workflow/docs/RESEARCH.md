# Research and implementation basis

Reviewed: 2026-10-13.
Use current client availability when a documented model is unavailable.
The task routing reflects this project's sources, risks, and browser tooling.
It is not a benchmark ranking or guaranteed token saving.

## Findings applied

The approved frontend uses plain JavaScript with a local Motion bundle.
HVK supplies PHP admin behavior and database access patterns.
A framework replacement would add migration work without meeting a new requirement.
The workflow therefore preserves those technologies.

The SQL schema identifies MariaDB 11.8.9 and utf8mb4_uca1400_ai_ci.
Local MySQL compatibility needs an explicit migration check.
Schema inspection found seven application tables.
The legacy feedback table requires customer_name.
The approved form does not require that field.
Task 02 resolves this mismatch through an explicit schema decision.
Private SQL rows were not copied into this pack.

The approved feedback preview allows 160 contact characters.
The legacy database permits 150.
Task 02 sets one shared limit. Task 08 verifies both sides.

HVK deletes feedback rows without deleting their files.
Task 04 provides shared file lifecycle controls.
Tasks 08 and 09 verify database deletion and physical file removal.

The prompts use bounded tasks, explicit evidence, and shared progress records.
Browser reviews remain separate from implementation writes.
Model changes occur at task boundaries or recorded checkpoints.
Skills load when relevant instead of entering every prompt in full.

## Primary references

| Topic | Source |
|---|---|
| Codex model selection | https://learn.chatgpt.com/docs/models |
| Current Sol model | https://developers.openai.com/api/docs/models/gpt-6.1-sol |
| Coding prompt guidance | https://developers.openai.com/cookbook/examples/gpt-5/codex_prompting_guide |
| Persistent project instructions | https://learn.chatgpt.com/docs/agent-configuration/agents-md |
| Antigravity models | https://antigravity.google/docs/models |
| Antigravity browser | https://antigravity.google/docs/ide/browser |
| Antigravity skills | https://antigravity.google/docs/skills |
| Upload safeguards | https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html |
| Authentication safeguards | https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html |

The Codex guide includes historical model examples.
This pack uses the current model catalog for model names.
The sources do not guarantee identical model access in every client.
No new software installation or backend implementation occurred while these prompts were written.
