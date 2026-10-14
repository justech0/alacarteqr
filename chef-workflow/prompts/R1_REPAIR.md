# Conditional repair prompt

Owner: Codex. Model: GPT-6.1 Sol.
Use default effort for bounded UI fixes and High for security or data consistency fixes.

Read AGENTS.md, PROJECT.md, and chef-workflow/CONTRACT.md.
Read only the defect report named in PROJECT.md and its relevant source files.
Use Ponytail and the skill required by the defect.
Use Frontend Design for approved-style corrections.
Use code-security for authentication, uploads, authorization, and data consistency defects.

Reproduce the reported failure before editing.
Check whether the tested source still matches the current source.
Fix the smallest complete cause.
Preserve the approved design and unrelated behavior.
Do not replace the application architecture for a local defect.
Add a meaningful regression check when the failure affects security, persistence, or repeatable interaction logic.
Run affected checks and record their actual results.
Do not remove failing assertions merely to obtain a pass.
Update the original defect status without deleting its evidence.
Update PROJECT.md with changed files, model, checks, and next reviewer.
Return the task to Gemini for the affected browser checks.
Do not restart unrelated successful work.
