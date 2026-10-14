# Task 01: Verify the approved preview

Owner: Gemini in Antigravity. Preferred model: Gemini 3.8 Flash.
Escalation: Gemini 3.1 Pro for unresolved visual or interaction defects.
Prerequisite: Task 00 provides a working local preview URL.

Read AGENTS.md, PROJECT.md, and chef-workflow/CONTRACT.md.
Read chef-workflow/docs/DESIGN_LOCK.md.
Use Impeccable and web-design-guidelines for this review.
Use Antigravity Chrome as the primary browser tool.
Verify skill discovery in this client and update its pending status.
Do not change application files during this baseline review.

## Browser evidence

Open the local preview URL recorded in PROJECT.md.
Check widths of 320, 390, 768, and 1440 pixels.
Check the initial header, scrolled header, category navigation, product details, WiFi, and feedback form.
Check screenshots and DOM state together.
Do not infer visual quality from DOM text alone.
Verify that the logo starts left and reaches the header's actual center.
Check header controls for collisions at every tested width.
Check that WiFi remains visible after scrolling.
Check square close icons and touch target dimensions.
Verify all 24 products and four categories.
Test Turkish dotted and dotless search terms.
Check Turkish, English, Arabic, dark mode, keyboard navigation, and reduced motion.
Open and close each dialog twice, including a rapid repeat.
Verify Escape, focus containment, and return focus in the real browser.
Check blocked browser storage without clearing unrelated user data.

## Expected preview limits

Feedback submission is not connected yet.
WiFi values are awaiting owner confirmation.
Ingredient details and calories remain hidden until verified.
Do not classify these documented preview limits as broken production features.
Record them as pending integration requirements.

## Handoff

Save a small screenshot set under docs/evidence/01/.
Record viewport, theme, language, URL, and tested commit or file hashes.
Write docs/reviews/01-browser-baseline.md.
Give each confirmed defect an ID, reproduction steps, expected behavior, and actual behavior.
Separate release blockers from small visual defects.
Keep the approved design direction unchanged.
Update PROJECT.md and list the evidence paths.
If blockers exist, route them to R1_REPAIR.md before task 02.
Otherwise set task 02 as the next action.
