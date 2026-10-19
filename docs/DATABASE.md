# Chef Database Foundation

## Runtime baseline

- PHP CLI: 8.3.33 with `pdo_mysql`.
- Local installed server: MySQL 8.4.3. Task 02 did not write to it.
- Test server A: MySQL 8.4.3 in isolated Docker.
- Test server B: MariaDB 11.8.9 in isolated Docker.
- Current schema version: `2026101501`.
- Storage engine: InnoDB.
- Default character set: `utf8mb4`.
- Default collation: `utf8mb4_unicode_ci`.
- Turkish text comparison: `utf8mb4_turkish_ci`.
- Public string IDs: ASCII with binary comparison.

Hostinger Web and Cloud plans use MariaDB. Hostinger supports InnoDB. The exact account server version needs deployment access.

- https://www.hostinger.com/support/1583226-which-database-management-system-is-used-at-hostinger/
- https://support.hostinger.com/en/articles/1583742-what-mysql-storage-engines-are-supported-on-your-servers

## Private dump review

The supplied dump reports MariaDB 11.8.9 and `utf8mb4_uca1400_ai_ci`.
Its SHA-256 is `6f0a7b6f17f6ccc303fdefd7e7bfc0da5a294b6b62074e8297326495f56799e2`.
The review did not execute dump statements or copy private rows.

The dump contains these application tables:

- `admins`
- `branches`
- `campaigns`
- `categories`
- `feedbacks`
- `meta`
- `products`

The dump contains no database creation, grant, user, definer, trigger, event, routine, external file, or global setting statement.
The dump uses InnoDB. Existing product and feedback foreign keys use `ON DELETE SET NULL`.

Hostinger import rules reject database administration and some definer statements. The Chef migrations do not use those statements.

- https://support.hostinger.com/en/articles/1864324-how-to-upload-and-set-up-your-database-at-hostinger

## Configuration

Set `CHEF_DB_CONFIG` to an absolute private file path. Do not place the file in `public_html`.
Start from `database/config.example.php`. Replace every placeholder in the private copy.

The configuration supports two users:

- `database`: application queries only.
- `migration_database`: migration and schema changes.

The CLI uses `migration_database` when it exists. The web runtime uses `database`.

## Commands

Run schema changes only from CLI:

```text
php database/cli.php status
php database/cli.php migrate
php database/cli.php seed
php database/cli.php verify
```

The migration runner uses a database advisory lock. It records each file checksum in `schema_migrations`.
It records a migration only after its verification callback passes.
If DDL stops, correct the stated prerequisite and run `migrate` again.

## Schema changes

Migration `2026101401` preserves the seven legacy tables. It adds stable public IDs and nullable nutrition fields.
It also makes `feedbacks.customer_name` nullable. The API does not invent a customer name.

Migration `2026101402` adds:

- `admins.auth_version` for future session invalidation.
- `admin_login_throttles` for account and full-address signals.
- `managed_uploads` for upload ownership and cleanup state.
- Feedback idempotency hash and request fingerprint fields.

Migration `2026101501` adds:

- Durable media operation identifiers and states.
- Temporary, pending, final, and legacy paths.
- Main and thumbnail dimensions, MIME, and byte counts.
- Cleanup expiry, lock, retry, and error fields.

Application requests do not create or alter tables. They reject a missing or old schema.

## Money and seed rules

- Currency: TRY.
- Storage: `DECIMAL(10,2)`.
- Input limit: two decimal places.
- Import normalization: round half up to two places.
- Existing staff values are not changed during repeat seed runs.

The seed contains four categories and 24 approved products.
It uses the approved IDs, names, serving descriptions, prices, and order.
Ingredients, calories, images, and verification times remain `NULL`.
The seed does not add admins, hashes, branches, WiFi values, campaigns, feedback, or customer data.

`categories.public_id` and `products.public_id` map frontend string IDs to database IDs.
The seed inserts only missing public IDs. It does not replace staff edits.
