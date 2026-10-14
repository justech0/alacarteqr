# Historical source review

Scope: Original archives before the approved preview implementation.
The findings below describe that source snapshot, not every current preview file.
A separate SQL upload was inspected later. See RESEARCH.md.


Review date: 2026-10-13.
Scope: Supplied archives, nine screenshots, Chef source artwork, and the live reference interface.
Method: File inventory, source review, visual inspection, and JavaScript syntax checks.
This review does not prove that the deployed sites have the same code.

## Archive roles

| Archive | Files | Expanded bytes | Finding |
|---|---:|---:|---|
| Alacart(2).zip | 60 | 13,449,602 | Editable static frontend with Git metadata and brand sources |
| hvk(4).zip | 129 | 5,453,807 | 18 PHP files, compiled frontend assets, and partial React source |
| qrmenu(2).zip | 46 | 1,999,263 | HTTrack snapshot with remote dependencies and unrelated XFN pages |

Neither application archive contains a SQL export.
HVK lacks a complete reproducible frontend source project.
Its component files refer to absent context and type modules.
Alacart contains no PHP backend or Node application manifest.
Use Alacart for frontend editing and HVK for function recovery.
Use reference files to inspect layout and interaction.
Do not deploy the downloaded reference directory.

## Current Alacart

| Area | Evidence | Result |
|---|---|---|
| Data | menu-data.js | 24 products: 9 meat, 7 pasta, 6 salad, 2 children |
| Product photos | Every item.image | All 24 values are null |
| Calories | Every item.calories | All 24 values are null |
| Prices | Three menu sheets | Product prices match the visible source sheets |
| Ingredients | menu-data.js | Some ingredient details exceed the source evidence |
| Feedback | app.js, submit listener | Clears the message and closes the window without a network request |
| WiFi | index.html footer | Wrong position for the new requirement |
| Product cards | createItemElement | Always inserts a placeholder, even when an image exists |
| Dialogs | openDialog | Uses CSS display without native showModal() behavior |
| Closing | closeDialog | Timeout and transition handlers can affect a quickly reopened window |
| Preferences | init, setTheme, setLang | Direct localStorage access can stop initialization when storage fails |
| Search | filterItems | Uses generic lowercase conversion for Turkish text |
| Language | setLang and index.html | Product text changes. Several interface labels remain Turkish. |
| Contrast | #8A8783 on #FDFAF5 | About 3.43:1, below the normal-text AA threshold |
| Motion | style.css | Reduced-motion rules leave smooth scrolling enabled |
| Logo | measureLogoPositions | Measures the large logo before the compact size change |

The old status report claims 25 products and a complete contrast pass.
Those claims do not match this source review.
Its visual test claims lack attached evidence in this archive.

The price date and VAT statement appear on the source menu sheets.
They still require owner confirmation before release.
Calories cannot be derived from the available sheets.
Examples of unsupported recipe detail include croutons, Parmesan, and cream sauce.
Do not publish those details as verified ingredients.

## HVK functions to retain

| Function | Source | Migration requirement |
|---|---|---|
| Product create, edit, delete, and category move | admin/products.php | Preserve behavior with stronger validation |
| Category create, edit, and delete | admin/categories.php | Check dependent products and image cleanup |
| Product order | admin/product-order.php | Preserve category membership and complete-order validation |
| Category order | admin/category-order.php | Add duplicate and complete-order checks |
| Bulk prices and CSV prices | admin/bulk-prices.php | Preserve import, export, and reset actions |
| CSV/JSON product import | admin/import-products.php | Bound file size and row count |
| WiFi editing | admin/branches.php | Connect top-header WiFi to saved settings |
| Campaign image and enabled state | admin/campaign.php | Keep image replacement and visibility controls |
| Menu version | admin/config.php and admin/index.php | Invalidate cached menu data after edits |
| Translations | admin/functions.php | Preserve editable translations and Turkish fallback |
| Feedback inbox | admin/feedbacks.php | Show answers, contact, image, and deletion result |
| Anonymous feedback | admin/api/feedback.php | Validate and persist the complete feedback flow |

The product importer reads image_url but stores a null image path.
Document this existing limitation before changing the import format.
The menu API currently uses products, categoryId, and localized text objects.
Alacart uses items, category, and localized text objects.
Use one explicit adapter or one documented API contract.
Add ingredient and calorie fields through a migration with nullable values.

## HVK feedback questions

The component uses three steps.
It first requests one topic from taste, service, staff, hygiene, or other.
It then requests a rating from one to five.
It finally requests a message, with optional contact and image fields.
Ratings one and two display a recovery message.

Use these short Turkish questions in the new interface:

1. "Hangi konuda görüş bildirmek istersiniz?"
2. "Bu konudaki deneyiminizi nasıl değerlendirirsiniz?"
3. "Neyi beğendiniz veya neyi geliştirmeliyiz?"

Use "Size dönmemizi ister misiniz?" for the optional contact field.
Use "Fotoğraf eklemek ister misiniz?" for the optional image field.
Allow guests to return to an earlier step without losing answers.
Do not invent five separate scored questions and call them existing HVK behavior.

## Security findings

| ID | Evidence | Risk or missing control |
|---|---|---|
| H1 | feedbacks.php POST handler | Deletes the row without deleting its image |
| H2 | functions.php handle_image_upload | Fallback extension comes from the original filename |
| H3 | functions.php handle_image_upload | No pixel-count limit before image decoding |
| H4 | feedback.php upload call | Ignores upload errors and can accept feedback without its image |
| H5 | feedback.php save path | A failed database insert can leave an orphan image |
| H6 | login.php | No application login throttling or session ID renewal found |
| H7 | login.php and config.php | Reset uses a reusable shared secret |
| H8 | config.php and both APIs | Error responses can contain internal exception details |
| H9 | feedback.php | No application rate limit, strict method gate, or complete field validation found |
| H10 | feedback.php image_url | Feedback images use public upload paths |
| H11 | delete_image_file | Path checks lack canonical containment and explicit symlink handling |
| H12 | categories.php and campaign.php | Some file deletion occurs before database changes succeed |
| H13 | bulk-prices.php CSV export | Product names reach spreadsheet cells without formula neutralization |
| H14 | config.php | Credentials and a reset secret exist in source |
| H15 | APIs and schema helpers | Public requests can trigger schema checks and changes |
| H16 | login.php and header.php | Admin styling depends on a runtime Tailwind CDN script |

H2 does not prove remote code execution on the current server.
Server handlers and the failed-WebP path determine exploitability.
Replace the filename fallback regardless of those conditions.
Protect private feedback images through authenticated access.
Rotate source credentials if they are active or were shared outside authorized access.
Never print their values in reports or commits.

Prepared statements and admin CSRF checks already exist in several routes.
Retain them during migration.
No JWT authentication appears in the reviewed PHP implementation.
Do not add JWT to satisfy a checklist.
The translation request uses a fixed service host.
This review did not establish a user-controlled SSRF path.
Check new network features before accepting arbitrary URLs.
The archives cannot establish the live server's security headers or private-file rules.

## Visual reference evidence

| Screenshot | Observation |
|---|---|
| download.png | Header, category rail, campaign banner, two-column product grid |
| download(1).png | Coffee cards and sticky category navigation |
| download(2).png | Compact horizontal category strip |
| download(3).png | Circular category images and multi-line labels |
| download(4).png | Product overlay with image, title, price, and close control |
| download(5).png | Square photos with short descriptions and price outlines |
| download(6).png | Exact duplicate of download(3).png |
| download(7).png | Exact duplicate of download(5).png |
| download(8).png | Category section transition and two-column drink cards |

Live inspection confirmed the header, category navigation, and product grid.
Image selection started an overlay. Its completed content was not verified in the live browser.
The supplied fourth numbered screenshot shows the completed product window.
The downloaded menu HTML occupies about 1.51 MB before compression.
It contains 34 script elements and 670 image tags, including fallback duplicates.
It references remote scripts, remote images, and third-party tracking.
These counts are source inventory, not measured transfer sizes or performance scores.

## Verification limits

Both Alacart JavaScript files passed node --check.
The logo PDF contains one page and no document JavaScript.
The SVG contains seven path elements and no embedded image element.
PHP was unavailable in the review runtime.
No database integration, login attack simulation, or deletion test ran here.
No production endpoint received test submissions.
No application source was changed during this review.

