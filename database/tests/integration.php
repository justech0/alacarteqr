<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib.php';

if ((string)getenv('CHEF_TEST_ALLOW_RESET') !== 'task02-isolated-docker') {
    throw new RuntimeException('Set CHEF_TEST_ALLOW_RESET=task02-isolated-docker for the disposable test server.');
}

$host = (string)(getenv('CHEF_TEST_HOST') ?: '127.0.0.1');
$port = (int)(getenv('CHEF_TEST_PORT') ?: 3306);
$user = (string)(getenv('CHEF_TEST_USER') ?: 'root');
$password = (string)getenv('CHEF_TEST_PASSWORD');
$engine = (string)(getenv('CHEF_TEST_ENGINE') ?: 'unknown');

$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$serverVersion = (string)$server->query('SELECT VERSION()')->fetchColumn();
$serverHost = (string)$server->query('SELECT @@hostname')->fetchColumn();
if ($serverHost === '') {
    throw new RuntimeException('The isolated server hostname is unavailable.');
}

$allowedDatabases = ['chef_alacarte_test', 'chef_alacarte_legacy_test'];

function test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function test_reset_database(PDO $server, string $database, array $allowedDatabases): void
{
    if (!in_array($database, $allowedDatabases, true)) {
        throw new RuntimeException('Refused to reset an unapproved database.');
    }
    $server->exec("DROP DATABASE IF EXISTS `{$database}`");
    $server->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

function test_database(string $host, int $port, string $database, string $user, string $password): PDO
{
    return new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
}

function test_seed_source(array $seed): void
{
    test_assert(count($seed['categories']) === 4, 'The seed must contain four categories.');
    test_assert(count($seed['items']) === 24, 'The seed must contain 24 products.');

    $ids = [];
    $categoryIds = array_column($seed['categories'], 'id');
    foreach ($seed['items'] as $item) {
        test_assert(!isset($ids[$item['id']]), 'Product public IDs must be unique.');
        test_assert(in_array($item['category'], $categoryIds, true), 'Each product must use an approved category.');
        test_assert(preg_match('/^\d+\.\d{2}$/', $item['price']) === 1, 'Each seed price must have two decimal places.');
        $ids[$item['id']] = true;
    }

    $normalized = [
        'categories' => array_map(
            static fn(array $category): array => ['id' => $category['id'], 'name' => $category['name']],
            $seed['categories']
        ),
        'items' => array_map(
            static fn(array $item): array => [
                'id' => $item['id'],
                'category' => $item['category'],
                'name' => $item['name'],
                'price' => $item['price'],
                'description' => $item['description'],
            ],
            $seed['items']
        ),
    ];
    $canonical = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    test_assert(hash('sha256', (string)$canonical) === 'c7d8fa9d1ecf56cc7b28adca2aba179b93fa06365f2f87317f12dc455640d591', 'The seed differs from approved menu-data.js fields.');
}

function test_create_legacy_fixture(PDO $pdo, bool $withOrphan): void
{
    $pdo->exec("CREATE TABLE admins (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(64) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE INDEX uq_admins_username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE branches (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        wifi_name VARCHAR(150) DEFAULT NULL,
        wifi_password VARCHAR(150) DEFAULT NULL,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE campaigns (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        is_active TINYINT(1) NOT NULL DEFAULT 0,
        image_path VARCHAR(255) DEFAULT NULL,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE categories (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        name_en VARCHAR(200) DEFAULT NULL,
        name_ar VARCHAR(200) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        description_en TEXT DEFAULT NULL,
        description_ar TEXT DEFAULT NULL,
        image_path VARCHAR(255) DEFAULT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE products (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        category_id INT DEFAULT NULL,
        name VARCHAR(200) NOT NULL,
        name_en VARCHAR(200) DEFAULT NULL,
        name_ar VARCHAR(200) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        description_en TEXT DEFAULT NULL,
        description_ar TEXT DEFAULT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        sort_order INT NOT NULL DEFAULT 0,
        image_path VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE feedbacks (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        customer_name VARCHAR(120) NOT NULL,
        rating INT NOT NULL,
        comment TEXT DEFAULT NULL,
        branch_id INT DEFAULT NULL,
        topic VARCHAR(50) DEFAULT NULL,
        contact VARCHAR(150) DEFAULT NULL,
        image_path VARCHAR(255) DEFAULT NULL,
        language VARCHAR(5) DEFAULT 'tr',
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE meta (
        meta_key VARCHAR(64) NOT NULL PRIMARY KEY,
        meta_value TEXT DEFAULT NULL,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (:username, :password_hash)')
        ->execute([':username' => 'synthetic-admin', ':password_hash' => 'synthetic-hash']);
    $pdo->prepare('INSERT INTO branches (id, name) VALUES (:id, :name)')
        ->execute([':id' => 1, ':name' => 'Sentetik Şube']);
    $pdo->prepare('INSERT INTO categories (id, name) VALUES (:id, :name)')
        ->execute([':id' => 1, ':name' => 'Sentetik Kategori']);
    $categoryId = $withOrphan ? 999 : 1;
    $pdo->prepare('INSERT INTO products (category_id, name, price) VALUES (:category_id, :name, :price)')
        ->execute([':category_id' => $categoryId, ':name' => 'Sentetik Ürün', ':price' => '12.34']);
    $pdo->prepare('INSERT INTO feedbacks (customer_name, rating, comment, branch_id) VALUES (:name, :rating, :comment, :branch_id)')
        ->execute([':name' => 'Sentetik Kişi', ':rating' => 5, ':comment' => 'Sentetik yorum', ':branch_id' => 1]);
}

$checks = [];
$seed = require dirname(__DIR__) . '/seed-data.php';
test_seed_source($seed);
$checks[] = 'approved seed source';

test_reset_database($server, 'chef_alacarte_test', $allowedDatabases);
$pdo = test_database($host, $port, 'chef_alacarte_test', $user, $password);
$applied = chef_migrate($pdo);
test_assert($applied === ['2026101401', '2026101402', '2026101501'], 'Empty installation did not apply all migrations.');
chef_verify_schema($pdo);
$checks[] = 'empty install';

$secondRun = chef_migrate($pdo);
test_assert($secondRun === [], 'Repeated migration must be a no-op.');
$checks[] = 'repeated migration';

$counts = chef_seed($pdo, $seed);
test_assert($counts['categories'] === 4 && $counts['products'] === 24, 'Seed row counts are invalid.');
test_assert($counts['admins'] === 0 && $counts['branches'] === 0 && $counts['campaigns'] === 0 && $counts['feedbacks'] === 0, 'Seed inserted private or venue data.');
test_assert((int)$pdo->query('SELECT COUNT(*) FROM products WHERE ingredients IS NOT NULL OR ingredients_en IS NOT NULL OR ingredients_ar IS NOT NULL OR calories IS NOT NULL OR ingredients_verified_at IS NOT NULL OR calories_verified_at IS NOT NULL')->fetchColumn() === 0, 'Unverified nutrition data must be NULL.');
test_assert((string)$pdo->query("SELECT price FROM products WHERE public_id = 'izgara-kofte'")->fetchColumn() === '415.00', 'Price precision changed.');
test_assert((string)$pdo->query("SELECT description_ar FROM products WHERE public_id = 'izgara-kofte'")->fetchColumn() === '١٦٠غ كفتة + بطاطس مقلية + سلطة', 'Arabic text did not round trip.');
test_assert((int)$pdo->query('SELECT COUNT(*) FROM categories WHERE public_id IS NOT NULL')->fetchColumn() === 4, 'Category public ID mapping is incomplete.');
test_assert((int)$pdo->query('SELECT COUNT(*) FROM products WHERE public_id IS NOT NULL')->fetchColumn() === 24, 'Product public ID mapping is incomplete.');
$checks[] = 'seed counts, mappings, precision, and Unicode';

$pdo->prepare('UPDATE products SET name = :name, price = :price WHERE public_id = :public_id')
    ->execute([':name' => 'Staff Edit', ':price' => '999.99', ':public_id' => 'izgara-kofte']);
$repeatCounts = chef_seed($pdo, $seed);
test_assert($repeatCounts['categories'] === 4 && $repeatCounts['products'] === 24, 'Repeated seed duplicated rows.');
$edited = $pdo->query("SELECT name, price FROM products WHERE public_id = 'izgara-kofte'")->fetch();
test_assert($edited['name'] === 'Staff Edit' && $edited['price'] === '999.99', 'Repeated seed replaced a staff edit.');
$checks[] = 'repeatable seed preserves staff edits';

$foreignKeyRejected = false;
try {
    $pdo->prepare('INSERT INTO products (category_id, name, price) VALUES (:category_id, :name, :price)')
        ->execute([':category_id' => 999999, ':name' => 'Invalid', ':price' => '1.00']);
} catch (PDOException) {
    $foreignKeyRejected = true;
}
test_assert($foreignKeyRejected, 'The product foreign key did not reject an invalid category.');
$turkish = $pdo->query("SELECT _utf8mb4'I' COLLATE utf8mb4_turkish_ci = _utf8mb4'ı' AS dotless_match, _utf8mb4'İ' COLLATE utf8mb4_turkish_ci = _utf8mb4'i' AS dotted_match")->fetch();
test_assert((int)$turkish['dotless_match'] === 1 && (int)$turkish['dotted_match'] === 1, 'Turkish comparison rules failed.');
$publicIdColumn = $pdo->query("SELECT CHARACTER_SET_NAME, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'public_id'")->fetch();
test_assert($publicIdColumn['CHARACTER_SET_NAME'] === 'ascii' && (int)$publicIdColumn['CHARACTER_MAXIMUM_LENGTH'] === 64, 'The public ID index width is unsafe.');
$checks[] = 'foreign keys, Turkish comparison, and index length';

test_reset_database($server, 'chef_alacarte_legacy_test', $allowedDatabases);
$legacy = test_database($host, $port, 'chef_alacarte_legacy_test', $user, $password);
test_create_legacy_fixture($legacy, false);
chef_migrate($legacy);
test_assert((string)$legacy->query("SELECT name FROM products WHERE id = 1")->fetchColumn() === 'Sentetik Ürün', 'Legacy product data was not preserved.');
$nullability = $legacy->query("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'feedbacks' AND COLUMN_NAME = 'customer_name'")->fetchColumn();
test_assert($nullability === 'YES', 'Legacy customer_name is still required.');
$checks[] = 'sanitized legacy migration';

test_reset_database($server, 'chef_alacarte_legacy_test', $allowedDatabases);
$legacy = test_database($host, $port, 'chef_alacarte_legacy_test', $user, $password);
test_create_legacy_fixture($legacy, true);
$failed = false;
try {
    chef_migrate($legacy);
} catch (RuntimeException $e) {
    $failed = str_contains($e->getMessage(), 'orphan rows');
}
test_assert($failed, 'The malformed legacy fixture did not fail its prerequisite.');
test_assert((int)$legacy->query("SELECT COUNT(*) FROM schema_migrations WHERE version = '2026101401'")->fetchColumn() === 0, 'A failed migration was recorded.');
$legacy->prepare('INSERT INTO categories (id, name) VALUES (:id, :name)')->execute([':id' => 999, ':name' => 'Recovery Category']);
$recovered = chef_migrate($legacy);
test_assert($recovered === ['2026101401', '2026101402', '2026101501'], 'The migration did not recover after fixture repair.');
$checks[] = 'failed migration recovery';

echo json_encode([
    'status' => 'PASS',
    'engine' => $engine,
    'server_version' => $serverVersion,
    'schema_version' => CHEF_SCHEMA_VERSION,
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
