# Task 08: Connect feedback and prove image deletion

Owner: Codex. Preferred model: GPT-6.1 Sol. Effort: High.
Prerequisite: Tasks 03, 04, and 07 pass.

Read AGENTS.md, PROJECT.md, and chef-workflow/CONTRACT.md.
Read docs/API_CONTRACT.md and docs/MEDIA_LIFECYCLE.md.
Inspect HVK's FeedbackModal, feedback endpoint, and admin feedback page.
Use Ponytail and code-security. Use Humanizer only for short interface messages.

## Guest flow

Preserve the approved feedback window and its compact layout.
Ask for topic, rating, and message.
Keep contact and photo optional.
Use the HVK topics: taste, service, staff, hygiene, and other.
Accept integer ratings from one through five.
Show the recovery message for ratings one and two.
Preserve answers when users move between sections or when submission fails.
Use contract limits in both frontend and backend.
Keep Turkish, English, and Arabic messages complete.
Replace the preview-only form action with a real multipart request.
Remove the preview notice only when persistence works.
Report success only after the server confirms the database write.
Keep failure messages visible and actionable.

## Server controls

Accept the documented method and content type only.
Validate scalar types, allowed topics, rating range, lengths, and image limits.
Reject array values where scalar fields are required.
Use persistent anonymous submission limits and an appropriate CSRF or origin control.
Keep abuse controls independent from CSRF controls.
Do not trust arbitrary forwarded IP headers.
Prevent duplicate records from retries or repeated button clicks.
Use a bounded idempotency mechanism tied to the validated request.
Do not reuse an idempotency key for changed request content.
Return meaningful status codes without internal exception details.
Use the shared private upload service.
Preserve image upload failures instead of silently accepting incomplete feedback.

## Admin inbox

Display topic, rating, message, optional contact, language, time, and authorized image access.
Escape every displayed user value.
Provide useful filtering and bounded pagination.
Protect reads, deletes, exports, and image access with server authorization.
Use the shared deletion lifecycle for feedback and owned image files.
Expose incomplete cleanup and a safe retry action.
Keep deleted images inaccessible during pending cleanup.
Do not copy private feedback into public API responses or Git.

## Required lifecycle proof

Submit disposable feedback with a valid image.
Record its database ID and controlled file identifier without private content.
Confirm that one record and its image exist.
Delete it through the admin panel.
Confirm that its row, original image, and derived files are gone.
Confirm that the old image URL cannot return the image.
Repeat deletion and verify the documented idempotent result.
Simulate database failure and file deletion failure.
Verify orphan cleanup, visible pending state, and successful retry.
Write docs/reviews/08-feedback.md with database and filesystem evidence.
Update PROJECT.md and hand off to Gemini for task 09.
