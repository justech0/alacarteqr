# Task 04: Build safe upload and deletion helpers

Owner: Codex. Preferred model: GPT-6.1 Sol. Effort: High.
Escalation: GPT-6 Astra only for unresolved consistency or security decisions, when available.
Prerequisite: Tasks 02 and 03 pass.

Read AGENTS.md, PROJECT.md, and chef-workflow/CONTRACT.md.
Read docs/DATABASE.md and docs/API_CONTRACT.md.
Inspect HVK's handle_image_upload and delete_image_file helpers.
Use Ponytail, code-security, and focused Semgrep rules.
Implement shared helpers before wiring product, campaign, and feedback uploads.

## Validation and storage

Accept JPEG, PNG, and WebP raster uploads only.
Keep the trusted logo SVG separate from user upload handling.
Check upload status, byte size, detected MIME, dimensions, and pixel count before full decoding.
Use initial limits of 4 MiB and 12 megapixels.
Account for the verified PHP memory limit and image decoder requirements.
Re-encode accepted images to a supported fixed output format.
Use random server filenames and fixed safe extensions.
Fail safely if encoding fails. Never fall back to the original filename extension.
Keep original filenames as optional escaped metadata only.
Keep feedback images outside public_html.
Serve private images through an authenticated authorization check.
Store product and campaign images in controlled public media directories.
Disable directory listing and executable file handling for public uploads.
Verify actual server behavior instead of assuming a filename rule prevents execution.
Bound thumbnail dimensions and strip unnecessary metadata.

## Consistent file operations

Write new uploads into a controlled temporary location.
Record ownership and final storage only after validation succeeds.
Coordinate database writes with file placement and explicit failure cleanup.
Do not pretend that database transactions make filesystem changes atomic.
Use a small pending-cleanup record or equivalent recoverable state.
Keep failed cleanup visible and retryable.
Keep the previous product image until its replacement is safely committed.
Remove abandoned new files after failed database inserts.
Reject traversal paths, symlinks, and paths outside the canonical storage root.
Verify ownership and shared references before deleting a file.
Do not accept a deletion path directly from a browser request.
Use a stored record identifier to resolve the permitted file.
Make repeated deletion safe.
Report complete deletion only when the record and its exclusively owned files are gone.
Include thumbnails and derived copies in the ownership and deletion rules.

## Failure tests

Test valid files, forged MIME, double extensions, oversized files, huge dimensions, and malformed images.
Test database failure after upload and storage failure before commit.
Test image replacement failure without losing the previous image.
Test repeated deletion, unrelated file references, traversal, and symlinks where the platform permits them.
Test simulated unlink failure and successful retry.
Confirm unauthenticated private image requests fail.
Record filesystem and database evidence for each lifecycle test.
Write docs/MEDIA_LIFECYCLE.md and docs/reviews/04-media.md.
Update PROJECT.md and set task 05 as the next action.
