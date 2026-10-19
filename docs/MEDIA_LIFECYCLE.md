# Media Lifecycle

Status: Task 04 local implementation is complete.

## Storage

Private configuration defines four canonical roots.

- `public_root` stores product, category, and campaign WebP files.
- `private_root` stores feedback WebP files.
- `temporary_root` stores upload and encoding work files.
- `legacy_public_root` contains files awaiting ownership migration.

The public root must stay inside the document root.
Private and temporary roots must stay outside the document root.
The service rejects traversal, symlinks, and paths outside configured roots.

Run `php database/media-cli.php prepare` before deployment.
This command creates controlled directories and the Apache hardening file.
The hardening file disables listing and denies executable extensions.

## Validation

The service accepts JPEG, PNG, and WebP raster uploads.
It validates the PHP upload status and `is_uploaded_file` result.
It checks actual bytes before application staging.
The file limit is 4 MiB.
The multipart allowance is 1 MiB.

The service detects MIME and dimensions on the server.
It rejects images above 12 megapixels.
It rejects malformed and animated images.
It checks available PHP memory before decoding.

The decoder must succeed before activation.
The encoder must provide WebP support.
The service never keeps an original upload as fallback output.
It removes metadata through full re-encoding.
It applies supported JPEG EXIF rotation values.

Main images fit inside 1200 by 1200 pixels.
Thumbnails fit inside 320 by 320 pixels.
The service keeps aspect ratio and does not upscale.
Final names use random 128-bit values and `.webp` extensions.

## States and commit point

Operations use these states:

- `upload_staged`: The original upload has durable tracking.
- `encoded`: The intended paths and WebP outputs exist.
- `activation_pending`: A new owner waits for activation.
- `replacement_pending`: An existing owner waits for replacement.
- `active`: The committed owner reference uses the final file.
- `cleanup_pending`: Physical deletion needs work or retry.
- `deleted`: Database tracking confirms no owned file remains.
- `review_required`: Legacy ownership needs a person to decide.

The service persists the operation ID before final promotion.
It also persists temporary, pending, main, and thumbnail paths.
These records use autocommit before the owner transaction starts.

Activation uses this order:

1. Lock the owner row.
2. Lock operation rows in ID order.
3. Set the pending state.
4. Copy temporary files to pending paths.
5. Verify each pending file hash.
6. Rename pending files to final paths.
7. Update the owner reference.
8. Commit the owner transaction.
9. Remove source and replaced files.

Step 8 is the database activation point.
The previous image remains active until that commit succeeds.
A rollback keeps the previous database reference.
Durable operation rows retain promoted files for recovery.

## Recovery and deletion

Activation, deletion, and cleanup lock owners before upload rows.
Cleanup uses a database advisory lock for one active runner.
It ignores unexpired upload operations.
It rechecks state and live references before each deletion.

Deletion requests use server-side owner records.
Browser requests never supply filesystem paths.
The service refuses files with active or shared references.
Deletion covers source, temporary, pending, main, and thumbnail files.

Failed `unlink` operations remain `cleanup_pending`.
Retries are idempotent.
The service checks the filesystem after each successful `unlink`.
It does not report complete deletion while tracked files remain.

Expired operation rows recover interrupted uploads.
The cleaner also removes old untracked temporary files.
Unknown legacy files remain unchanged.

## Private access

Feedback images use `managed:<id>` database references.
`private-image.php` validates the administrator session for each request.
It resolves the path from a server-side upload record.
It sends `image/webp`, `nosniff`, and `Cache-Control: no-store`.

`database/media-cli.php` runs only under CLI SAPI.
HTTP requests receive `404`.

## Legacy migration

Run `inventory` before `migrate-legacy`.
The inventory reports missing, ambiguous, and unknown files.
The migration is idempotent.
It never removes ambiguous or unknown files.

Public catalog images keep controlled legacy ownership records.
Feedback images move to private WebP storage.
The cleaner removes the previous feedback public file.
Deployment verification must confirm that each old URL fails.

## Automatic cleanup

Run this command every five minutes in production:

```text
php /home/<account>/private/chef/app/database/media-cli.php cleanup
```

Use the Hostinger cron service or an equivalent scheduler.
The advisory lock prevents overlapping cleanup work.
Alert on command failures and persistent `cleanup_pending` rows.

Hostinger LiteSpeed behavior needs deployment verification.
The production scheduler also needs deployment verification.
