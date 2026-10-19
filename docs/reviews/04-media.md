# Task 04 Media Review

Date: 2026-10-16.
Branch: `feature/chef-redesign`.
Tested HEAD: `8fd4a8762eecbd3e1776219e11f427af5b24550e` with the listed working-tree changes.
Client: Codex CLI.
Model: unavailable to the client session.

## Runtime

- PHP CLI: 8.3.33.
- PHP web SAPI: `apache2handler`, PHP 8.3.33.
- Apache: 2.4.68 with OpenSSL 3.0.21.
- PHP memory limit: 512 MiB.
- PHP upload and post limits: 2 GiB.
- GD: bundled 2.1.0 compatible.
- GD JPEG, PNG, and WebP support: available.
- Semgrep CE: 1.180.0.
- MySQL: 8.4.3.
- MariaDB: 11.8.9.
- Schema: `2026101501`.

MySQL image digest: `sha256:106d5197fd8e4892980469ad42eb20f7a336bd81509aae4ee175d852f5cc4565`.
MariaDB image digest: `sha256:6422478cb8e159f080fb1d8ccf65101e26fe51385787fde7d16c3b165a331f15`.

## Prerequisite

| Check | Result | Evidence |
|---|---|---|
| Task 02 evidence | PASS | Dual-engine migration tests pass through schema `2026101501` |
| Task 03 implementation | PASS | Current HTTP suite covers 12 protected routes and 6 includes |
| Task 03 documentation | PASS | `docs/reviews/03-auth.md` matches the current inventory |

## Validation and access

| Check | Result | Evidence |
|---|---|---|
| JPEG, PNG, and WebP | PASS | Real HTTP multipart uploads produced verified WebP files |
| Uploaded-file provenance | PASS | Tests used PHP HTTP uploads and `is_uploaded_file` |
| Double extension | PASS | `menu.php.jpg` became a random `.webp` file |
| Forged MIME | PASS | Plain text with `.jpg` was rejected |
| Malformed image | PASS | Invalid PNG data was rejected |
| Actual byte limit | PASS | A valid JPEG above 4 MiB was rejected before staging |
| Multipart request limit | PASS | A request above 5 MiB received a controlled response |
| Huge dimensions | PASS | A 4000 by 4000 image was rejected before decode |
| Animated images | PASS | APNG and WebP animation markers were rejected |
| Decode and WebP encode | PASS | GD decoded three inputs and verified each output |
| Dimensions | PASS | Main stayed 640 by 360. Thumbnail became 320 by 180 |
| Private authorization | PASS | Anonymous access redirected. Authorized access returned the image |
| Private headers | PASS | Response sent WebP, `nosniff`, and `no-store` |
| CLI HTTP access | PASS | `/database/media-cli.php` returned `404` |

## Lifecycle

| Check | Result | Evidence |
|---|---|---|
| Database failure after upload | PASS | Invalid feedback foreign key rolled back and cleaned files |
| Storage failure before commit | PASS | Unsafe final target stopped activation |
| Interruption after main promotion | PASS | Rollback preserved owner and cleanup removed promoted files |
| Interruption after thumbnail promotion | PASS | Rollback preserved owner and cleanup removed both files |
| Interruption before commit | PASS | Durable paths recovered the complete uncommitted promotion |
| Replacement preservation | PASS | Failed replacements kept the previous owner reference |
| Concurrent upload cleanup | PASS | Cleaner did not change an unexpired encoded operation |
| Concurrent replacement cleanup | PASS | Cleaner did not damage the open replacement transaction |
| Main and thumbnail unlink failures | PASS | Both remained `cleanup_pending` |
| Cleanup retry | PASS | The next run removed every unreferenced file |
| Repeated cleanup | PASS | A repeated run made no unsafe change |
| Shared reference | PASS | Deletion failed while another owner used the same reference |
| Traversal | PASS | A parent path was rejected |
| Windows link escape | PASS | A junction escaped the temporary root. Current path validation rejected it with exit code `0` |
| Orphan temporary file | PASS | The cleaner removed an expired untracked file |
| Direct disk proof | PASS | Tests checked file absence after deletion |
| Direct database proof | PASS | Tests checked owner references and upload states |

## Legacy and server

| Check | Result | Evidence |
|---|---|---|
| Legacy inventory | PASS | Missing, ambiguous, and unknown classes are separate |
| Idempotent ownership migration | PASS | The second run added no upload rows |
| Ambiguous ownership | PASS | Shared legacy file remained for review |
| Unknown files | PASS | Unknown file remained unchanged |
| Feedback private move | PASS | Database uses `managed:<id>` and old file is absent |
| Apache directory listing | PASS | Apache 2.4.68 returned `403` |
| Apache PHP execution | PASS | A temporary PHP file under media returned `403` |
| Hostinger LiteSpeed | NOT RUN | No authorized deployment environment is available |
| Production cleanup scheduler | NOT RUN | Deployment cron access is unavailable |

## Security scan

Semgrep ran 23 rules on nine changed PHP targets.
It reported six `echoed-request` findings in `products.php`.
No finding showed an exploitable output path.

| Line | Finding review |
|---|---|
| 310 | `sanitize($searchQuery)` calls `htmlspecialchars` with `ENT_QUOTES` and UTF-8 |
| 350 | `chef_canonical_id()` converts the category value to a canonical positive integer or zero |
| 360 | The category and product identifiers are canonical integers before URL construction |
| 366 | `chef_canonical_id()` converts the hidden category value to an integer or zero |
| 385 | `chef_canonical_id()` converts the form category value to an integer or zero |
| 451 | The cancel URL includes only the canonical integer category value |

## Delivery verification

- The unchanged media implementation hashes match the recorded values.
- `database/tests/verify-auth-static.php` completed with exit code `0`.
- `database/tests/auth-http.mjs` completed with exit code `0`.
- The HTTP test found 12 protected routes and 6 includes.
- `database/tests/media-http.mjs` completed with exit code `0`.
- The media HTTP test accepted four valid uploads and rejected seven invalid uploads.
- The current HTTP tests use exact `302`, `303`, and `403` route expectations.
- `database/tests/run-http-tests.mjs` was not run because it now includes Task 05 catalog checks.
- The unchanged `database/tests/media-lifecycle.php` result remains valid.
- Windows denied native file symlink creation because the process lacks the required privilege.
- A Windows junction inside temporary storage pointed to the fixture directory outside that root.
- `chef_media_absolute_path()` rejected the junction path with exit code `0`.
- The focused junction test verifies the current Windows reparse-path escape control.
- The repeated Semgrep scan completed with exit code `0`.
- The scan has zero errors and six reviewed findings.
- Client: Codex CLI. Model label: unavailable.

## File evidence

- `hvk/admin/media-service.php`: `43e94c440598397539eaa200336d1003521046608c962962c1d2d4bdf4e384fc`.
- `database/migrations/2026101501_media_lifecycle.php`: `2819346217e1e51d9fdbbeee9943ef46968b7bf2da85b2cf7442cf3b9ba2ae37`.
- `database/tests/auth-http.mjs`: `b398ab528a073481e5f0dd8d1ad9c10c776613adae39e5edf6a0854d38fad481`.
- `database/tests/media-http.mjs`: `133f48c76d522cc0e712ea9a011b13c023b50cb0fd47a3b90ffac65f258c5e0a`.
- `database/tests/media-lifecycle.php`: `17ade7ee3250505dab117391942cefa1086e08f080fdbf61c98a43cd81327673`.
- `database/tests/run-http-tests.mjs`: `894e120be5d9450f3a2843b1f0c8714047bd1d3580a1a2007849b1c7b805628c`.
- `database/tests/verify-auth-static.php`: `0d995c728e9dbd8f38b08300791e5a623f05a70780e9d3437b1568e0027c71b5`.
- Semgrep report: `eabf4dd1f10aa94618663ad78afd20f0dd40afa7a837d3f68f65deb5dcf55175`.

## Result

Task 04 is `PASS` for the local environment.
Hostinger LiteSpeed and cron checks remain deployment prerequisites.
The next task is `05_ADMIN_CATALOG.md`.
