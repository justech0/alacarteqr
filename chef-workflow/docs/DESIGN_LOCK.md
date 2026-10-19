# Approved design

Approval: Explicit user approval on 2026-10-13.
Authority: The supplied chef-onizleme.html and its editable source package.
The older Alacart design is not the approved visual baseline.

## Preserve

- White guest surface and Chef green accents.
- Original green and black Chef logo.
- Left logo position at page start.
- Centered logo after scrolling.
- WiFi access in the top header.
- Circular category navigation.
- Two product columns at normal phone widths.
- Compact names, serving descriptions, and outlined prices.
- Product details in an accessible modal.
- Restrained Motion transitions and reduced-motion support.
- Turkish, English, Arabic, and dark mode.
- Search, categories, campaigns, and feedback access.

## Allowed changes

Correct clipping, overlap, contrast, keyboard behavior, and verified interaction defects.
Replace preview data access with the documented database API.
Connect WiFi, campaign settings, feedback, ingredients, and calories to admin data.
Replace low-resolution photos only with matching, approved originals.
Keep unknown nutrition and unverified ingredient details hidden.
Use functional admin layouts with the same brand colors and type hierarchy.
Keep admin forms compact, readable, and keyboard accessible.

## Prevent drift

Do not add a full-screen opening scene, decorative gradients, glass panels, or unrelated gold styling.
Do not replace the logo lettering or add invented restaurant claims.
Do not add cart controls or purchase actions.
Do not regenerate food photos to imply unavailable ingredients.
Keep square close icons inside touch targets of at least 44 pixels.
Record the reason and screenshot for every visible correction.
Keep the original reference set in docs/evidence/01/ unchanged. Task 06 step 0 adds docs/evidence/ref-rev1/ for revision 1.

## Baseline limits

The preview has 24 products and four categories.
Twenty-three photos use crops from the supplied menu sheets.
Ekmek Köfte uses a placeholder.
The preview bundle measured 12,550 gzip bytes before backend integration.
That byte count is not a speed or Core Web Vitals result.
Seven Node checks passed before user approval.
Browser verification and PHP integration remained pending.

## Approved revisions

Revision 1 is active. The user requested and reviewed it between 2026-10-14 and 2026-10-16.
It replaces the matching parts of the original preview. Later tasks must keep it.
Hashes are in APPROVED_BASELINE.json under current_revision.

- Dark mode uses neutral tokens: page #0F1210, card #171B18, raised #1E231F, dialog #1B201C, line #2A312B.
- Dark text uses #F1F3EE and muted #9BA59C. Accent text uses #5BC46C. Button fill uses #1F7A2E.
- The Chef logo stays inside a white circular badge in dark mode.
- Search is one bordered box. The input has no second border, fill, or outline ring.
- The search placeholder reads "Menüde lezzet ara". English and Arabic translations exist.
- The language control is a custom popover with inline SVG flags for TR, EN, and AR. It uses no emoji.
- Card body padding and price badge spacing keep the price off the card edge.
- The active category button shows the hover effect while the guest scrolls.
- Admin dark and light themes reuse the same tokens. Use #176023 for fills only, never for text on dark.
