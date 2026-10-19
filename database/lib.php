<?php
declare(strict_types=1);

const CHEF_SCHEMA_VERSION = '2026101501';
const CHEF_MIGRATION_LOCK = 'chef_alacarte_schema';

function chef_is_absolute_path(string $path): bool
{
    return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
}

function chef_load_config(?string $path = null): array
{
    $path ??= (string)getenv('CHEF_DB_CONFIG');
    if ($path === '' || !chef_is_absolute_path($path) || !is_file($path)) {
        throw new RuntimeException('CHEF_DB_CONFIG must name an existing absolute configuration file.');
    }

    $config = require $path;
    if (!is_array($config) || !isset($config['database']) || !is_array($config['database'])) {
        throw new RuntimeException('The database configuration is invalid.');
    }

    foreach (['dsn', 'username', 'password'] as $key) {
        if (!array_key_exists($key, $config['database']) || !is_string($config['database'][$key])) {
            throw new RuntimeException("The database configuration is missing {$key}.");
        }
    }

    return $config;
}

function chef_connect(array $config, bool $forMigration = false): PDO
{
    $database = $forMigration && isset($config['migration_database'])
        ? $config['migration_database']
        : $config['database'];

    foreach (['dsn', 'username', 'password'] as $key) {
        if (!isset($database[$key]) || !is_string($database[$key])) {
            throw new RuntimeException("The selected database configuration is missing {$key}.");
        }
    }

    $options = $database['options'] ?? [];
    if (!is_array($options)) {
        throw new RuntimeException('Database options must be an array.');
    }

    return new PDO($database['dsn'], $database['username'], $database['password'], $options + [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
}

function chef_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
    $stmt->execute([':table' => $table]);
    return (int)$stmt->fetchColumn() === 1;
}

function chef_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
    $stmt->execute([':table' => $table, ':column' => $column]);
    return (int)$stmt->fetchColumn() === 1;
}

function chef_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index');
    $stmt->execute([':table' => $table, ':index' => $index]);
    return (int)$stmt->fetchColumn() > 0;
}

function chef_foreign_key_exists(PDO $pdo, string $table, string $column, string $referencedTable, string $referencedColumn): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column
           AND REFERENCED_TABLE_NAME = :referenced_table AND REFERENCED_COLUMN_NAME = :referenced_column'
    );
    $stmt->execute([
        ':table' => $table,
        ':column' => $column,
        ':referenced_table' => $referencedTable,
        ':referenced_column' => $referencedColumn,
    ]);
    return (int)$stmt->fetchColumn() > 0;
}

function chef_add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!chef_column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

function chef_add_index(PDO $pdo, string $table, string $index, string $columns, bool $unique = false): void
{
    if (!chef_index_exists($pdo, $table, $index)) {
        $kind = $unique ? 'UNIQUE INDEX' : 'INDEX';
        $pdo->exec("ALTER TABLE `{$table}` ADD {$kind} `{$index}` ({$columns})");
    }
}

function chef_add_foreign_key(PDO $pdo, string $table, string $column, string $referencedTable, string $referencedColumn, string $constraint): void
{
    if (chef_foreign_key_exists($pdo, $table, $column, $referencedTable, $referencedColumn)) {
        return;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM `{$table}` child
         LEFT JOIN `{$referencedTable}` parent ON parent.`{$referencedColumn}` = child.`{$column}`
         WHERE child.`{$column}` IS NOT NULL AND parent.`{$referencedColumn}` IS NULL"
    );
    $stmt->execute();
    if ((int)$stmt->fetchColumn() > 0) {
        throw new RuntimeException("Cannot add {$constraint}: orphan rows exist in {$table}.{$column}.");
    }

    $pdo->exec(
        "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` FOREIGN KEY (`{$column}`)
         REFERENCES `{$referencedTable}` (`{$referencedColumn}`) ON DELETE SET NULL"
    );
}

function chef_ensure_innodb(PDO $pdo, string $table): void
{
    $stmt = $pdo->prepare('SELECT ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
    $stmt->execute([':table' => $table]);
    if (strtoupper((string)$stmt->fetchColumn()) !== 'INNODB') {
        $pdo->exec("ALTER TABLE `{$table}` ENGINE=InnoDB");
    }
    $pdo->exec("ALTER TABLE `{$table}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

function chef_migration_files(): array
{
    $files = glob(__DIR__ . '/migrations/*.php') ?: [];
    sort($files, SORT_STRING);
    return $files;
}

function chef_load_migration(string $file): array
{
    $migration = require $file;
    if (!is_array($migration) || !isset($migration['version'], $migration['description'], $migration['up'], $migration['verify'])) {
        throw new RuntimeException('Invalid migration file: ' . basename($file));
    }
    if (!is_callable($migration['up']) || !is_callable($migration['verify'])) {
        throw new RuntimeException('Migration callbacks are invalid: ' . basename($file));
    }
    return $migration + ['file' => $file, 'checksum' => hash_file('sha256', $file)];
}

function chef_ensure_migration_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
            description VARCHAR(255) NOT NULL,
            checksum CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function chef_applied_migrations(PDO $pdo): array
{
    chef_ensure_migration_table($pdo);
    $rows = $pdo->query('SELECT version, description, checksum, applied_at FROM schema_migrations ORDER BY version')->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $result[$row['version']] = $row;
    }
    return $result;
}

function chef_acquire_migration_lock(PDO $pdo): void
{
    $stmt = $pdo->prepare('SELECT GET_LOCK(:name, 15)');
    $stmt->execute([':name' => CHEF_MIGRATION_LOCK]);
    if ((int)$stmt->fetchColumn() !== 1) {
        throw new RuntimeException('Could not acquire the migration lock.');
    }
}

function chef_release_migration_lock(PDO $pdo): void
{
    $stmt = $pdo->prepare('SELECT RELEASE_LOCK(:name)');
    $stmt->execute([':name' => CHEF_MIGRATION_LOCK]);
}

function chef_migrate(PDO $pdo): array
{
    chef_acquire_migration_lock($pdo);
    try {
        chef_ensure_migration_table($pdo);
        $applied = chef_applied_migrations($pdo);
        $newVersions = [];

        foreach (chef_migration_files() as $file) {
            $migration = chef_load_migration($file);
            $version = (string)$migration['version'];
            if (isset($applied[$version])) {
                if (!hash_equals($applied[$version]['checksum'], $migration['checksum'])) {
                    throw new RuntimeException("Migration checksum mismatch: {$version}.");
                }
                continue;
            }

            $migration['up']($pdo);
            $migration['verify']($pdo);
            $stmt = $pdo->prepare('INSERT INTO schema_migrations (version, description, checksum) VALUES (:version, :description, :checksum)');
            $stmt->execute([
                ':version' => $version,
                ':description' => (string)$migration['description'],
                ':checksum' => (string)$migration['checksum'],
            ]);
            $newVersions[] = $version;
        }

        return $newVersions;
    } finally {
        chef_release_migration_lock($pdo);
    }
}

function chef_verify_schema(PDO $pdo): array
{
    $checks = [];
    foreach (chef_migration_files() as $file) {
        $migration = chef_load_migration($file);
        $migration['verify']($pdo);
        $checks[] = (string)$migration['version'];
    }

    $applied = chef_applied_migrations($pdo);
    foreach (chef_migration_files() as $file) {
        $migration = chef_load_migration($file);
        $version = (string)$migration['version'];
        if (!isset($applied[$version])) {
            throw new RuntimeException("Migration is not recorded: {$version}.");
        }
        if (!hash_equals($applied[$version]['checksum'], $migration['checksum'])) {
            throw new RuntimeException("Migration checksum mismatch: {$version}.");
        }
    }

    return $checks;
}

function chef_assert_schema_current(PDO $pdo): void
{
    if (!chef_table_exists($pdo, 'schema_migrations')) {
        throw new RuntimeException('The database schema is not installed.');
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE version = :version');
    $stmt->execute([':version' => CHEF_SCHEMA_VERSION]);
    if ((int)$stmt->fetchColumn() !== 1) {
        throw new RuntimeException('The database schema is not current.');
    }
}

function chef_seed(PDO $pdo, array $data): array
{
    chef_assert_schema_current($pdo);
    $pdo->beginTransaction();
    try {
        $insertCategory = $pdo->prepare(
            'INSERT INTO categories (public_id, name, name_en, name_ar, description, description_en, description_ar, sort_order)
             SELECT :public_id, :name, :name_en, :name_ar, NULL, NULL, NULL, :sort_order
             WHERE NOT EXISTS (SELECT 1 FROM categories WHERE public_id = :lookup_id)'
        );
        foreach ($data['categories'] as $position => $category) {
            $insertCategory->execute([
                ':public_id' => $category['id'],
                ':name' => $category['name']['tr'],
                ':name_en' => $category['name']['en'],
                ':name_ar' => $category['name']['ar'],
                ':sort_order' => $position + 1,
                ':lookup_id' => $category['id'],
            ]);
        }

        $categoryStmt = $pdo->prepare('SELECT id FROM categories WHERE public_id = :public_id LIMIT 1');
        $insertProduct = $pdo->prepare(
            'INSERT INTO products (
                public_id, category_id, name, name_en, name_ar, description, description_en, description_ar,
                price, sort_order, image_path, ingredients, ingredients_en, ingredients_ar, calories,
                ingredients_verified_at, calories_verified_at
             )
             SELECT :public_id, :category_id, :name, :name_en, :name_ar, :description, :description_en, :description_ar,
                    :price, :sort_order, NULL, NULL, NULL, NULL, NULL, NULL, NULL
             WHERE NOT EXISTS (SELECT 1 FROM products WHERE public_id = :lookup_id)'
        );

        $categoryPositions = [];
        foreach ($data['items'] as $item) {
            $categoryStmt->execute([':public_id' => $item['category']]);
            $categoryId = (int)$categoryStmt->fetchColumn();
            if ($categoryId <= 0) {
                throw new RuntimeException('Seed category is missing: ' . $item['category']);
            }
            $categoryPositions[$item['category']] = ($categoryPositions[$item['category']] ?? 0) + 1;
            $insertProduct->execute([
                ':public_id' => $item['id'],
                ':category_id' => $categoryId,
                ':name' => $item['name']['tr'],
                ':name_en' => $item['name']['en'],
                ':name_ar' => $item['name']['ar'],
                ':description' => $item['description']['tr'],
                ':description_en' => $item['description']['en'],
                ':description_ar' => $item['description']['ar'],
                ':price' => $item['price'],
                ':sort_order' => $categoryPositions[$item['category']],
                ':lookup_id' => $item['id'],
            ]);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO meta (meta_key, meta_value)
             SELECT :key_name, :value_text WHERE NOT EXISTS (SELECT 1 FROM meta WHERE meta_key = :lookup_key)'
        );
        $stmt->execute([':key_name' => 'menu_version', ':value_text' => '1', ':lookup_key' => 'menu_version']);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return chef_seed_counts($pdo);
}

function chef_seed_counts(PDO $pdo): array
{
    $counts = [];
    foreach (['categories', 'products', 'admins', 'branches', 'campaigns', 'feedbacks'] as $table) {
        $counts[$table] = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }
    return $counts;
}
