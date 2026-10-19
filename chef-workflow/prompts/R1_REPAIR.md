# Conditional repair prompt

Owner by defect type:
- Guest or admin UI defect: Claude in Antigravity, newest Sonnet. Use Opus for difficult layout causes.
- Backend, data, or security defect: Codex, GPT-6.1 Sol, High effort.
Never change approved color values or design tokens unless the defect report names them.

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
Return the task to Gemini only when the defect affects browser-visible behavior.
Otherwise return it to the task that reported the defect.
Do not restart unrelated successful work.
