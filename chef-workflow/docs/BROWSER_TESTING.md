# Browser testing rules

Gemini in Antigravity runs every visual, DOM, and interaction check.
Codex runs code, HTTP, and database checks only.
Codex does not use Playwright or any browser for visual proof.

## Tool route

Use `browser_subagent` (the Antigravity built-in browser) for DOM work.
Read the rendered DOM, the accessibility tree, and computed styles.
Capture a screenshot with each DOM result. Do not trust either alone.
Use Chrome DevTools MCP only for network status and performance evidence.
Use Playwright CLI only for repeatable regression scripts that Codex wrote.
Do not paste full DOM snapshots into chat. Quote at most ten lines.

## Record format

Write one row per check: URL, viewport, theme, language, selector or accessible name, expected, actual.
Mark each row PASS, FAIL, BLOCKED, or UNVERIFIED.
Never mark an unrun check as PASS.

## Standard DOM probes

Run these probes on every page under review.

| Probe | Pass rule |
|---|---|
| Horizontal overflow | `document.documentElement.scrollWidth <= window.innerWidth` |
| Touch targets | Buttons, links, and inputs measure at least 44 by 44 CSS pixels |
| Accessible names | Every input, button, and dialog has a visible label or `aria-label` |
| Focus | Tab order follows reading order. Focus ring is visible on every control |
| Contrast | Text 4.5:1 or higher. Large text and control borders 3:1 or higher |
| External hosts | `performance.getEntriesByType('resource')` lists same-origin URLs only |
| Console | No errors or warnings from application code |
| Native dialogs | The page never calls `alert`, `confirm`, or `prompt` |

## Admin test hooks

Task 05 adds stable hooks. Use them instead of text or position selectors.

- Forms and controls use `data-testid="<area>-<name>"`. Example: `product-form`, `product-price`, `product-save`.
- Row actions append the public ID. Example: `product-delete-<publicId>`.
- Reorder buttons use `reorder-up-<id>` and `reorder-down-<id>`.
- Confirmation uses a native `<dialog>` with `confirm-dialog`, `confirm-dialog-name`, `confirm-dialog-accept`, and `confirm-dialog-cancel`.
- Save results appear in one `role="status"` region. Errors appear in `role="alert"` beside the field.

## Test access

The admin runs only against the disposable `chef_alacarte_test` database.
The admin URL and launch command are in docs/ENVIRONMENT.md under "Admin test access".
Task 05 writes a random administrator password to a private credentials file outside Git and public_html.
docs/ENVIRONMENT.md names the file path. It never contains the password.

- Read the file with a file tool. Type the value into the login field through the DOM.
- Do not print the password in chat, logs, reports, or screenshots.
- Do not capture a screenshot while the password field holds a value.
- Rebuild the test database with the documented setup command after the review. This rotates the password.

Gemini has no database credentials.
Use `php database/tests/readonly-check.php <check>` to confirm saved state.
Each named check returns bounded JSON. Do not write raw SQL.

## Drag sorting

Browser drag events are unreliable in automated DOM tests.
The keyboard reorder buttons are the required test path.
Try drag sorting as a best-effort check. Mark a failed attempt UNVERIFIED, not FAIL.

## Fresh guest context

Open a new private window or a new `browser_subagent` session.
Clear storage for the test origin only. Do not clear unrelated browser data.

## Reference screenshots

Revision 1 of the guest menu replaced the original dark mode and several controls.
The first reference set in docs/evidence/01/ remains as history.
Task 06 step 0 captures the current reference set in docs/evidence/ref-rev1/.
Later tasks compare against ref-rev1. Do not compare dark mode with docs/evidence/01/04-390px-dark.png.
