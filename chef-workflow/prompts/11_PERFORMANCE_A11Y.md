# Task 11: Verify performance and accessibility

Owner: Gemini in Antigravity. Preferred model: Gemini 3.8 Flash.
Escalation: Gemini 3.1 Pro for unclear visual or interaction defects.
Prerequisite: Task 10 security fixes are complete and the local build runs.

Read AGENTS.md, PROJECT.md, and chef-workflow/CONTRACT.md.
Read the design lock, BROWSER_TESTING.md, and the latest security report.
Use `browser_subagent` for interaction and DOM checks.
Use Chrome DevTools MCP for measured performance and network evidence.
Use Impeccable and web-design-guidelines for focused visual and accessibility review.
Do not change application source during this review.

## Performance

Measure the actual PHP-backed guest menu.
Record browser version, URL, device profile, network profile, CPU settings, and cache state.
Run three comparable mobile measurements and report median lab values.
Check LCP, CLS, blocking work, transferred bytes, and failed requests.
Target LCP at most 2.5 seconds and CLS at most 0.1.
Use 200 milliseconds as an INP field target when real field data exists.
Do not label Lighthouse TBT or one scripted interaction as field INP.
Use initial budgets of 80 KiB compressed guest JavaScript and 500 KiB first-viewport transfer.
Report the measured build separately from the earlier 12,550-byte preview baseline.
Inspect image dimensions, loading priority, lazy loading, fonts, and cache headers.
Check whether server queries or translation calls delay first content.
Check layout stability during image loading and logo movement.
Record specific optimization candidates with measured impact or a clear hypothesis.
Avoid decorative optimization that does not address a measured problem.

## Accessibility and visual consistency

Check 320, 390, 768, and 1440 pixel widths.
Check 200% zoom, keyboard operation, visible focus, and Escape behavior.
Check native dialog focus containment and return focus.
Check screen-reader names and dynamic status announcements through the accessibility tree.
Check Turkish, English, Arabic, light mode, and dark mode.
Check reduced motion and consistent close buttons.
Check text contrast, missing-image behavior, and control spacing.
Compare the result against docs/evidence/ref-rev1/.
Do not compare dark mode with docs/evidence/01/04-390px-dark.png. Revision 1 replaced it.
Report functional changes that unintentionally changed the visual design.

## Handoff

Save concise metrics and representative screenshots under docs/evidence/11/.
Write docs/reviews/11-performance-a11y.md.
Separate measured failures, unmeasured risks, and optional polish.
Route required fixes through R1_REPAIR.md.
Repeat affected measurements with the same conditions after fixes.
Update PROJECT.md and permit task 12 only when required checks pass.
