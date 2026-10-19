<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/hvk/admin/security.php';
require_once dirname(__DIR__, 2) . '/hvk/admin/media-service.php';

if ((string)getenv('CHEF_TEST_ALLOW_RESET') !== 'task02-isolated-docker') {
    throw new RuntimeException('The isolated Docker reset guard is missing.');
}

$host = (string)(getenv('CHEF_TEST_HOST') ?: '127.0.0.1');
$port = (int)(getenv('CHEF_TEST_PORT') ?: 33306);
$user = (string)(getenv('CHEF_TEST_USER') ?: 'root');
$password = (string)getenv('CHEF_TEST_PASSWORD');
$privateRoot = (string)getenv('CHEF_TEST_PRIVATE_ROOT');
if ($privateRoot === '' || chef_path_inside($privateRoot, chef_document_root())) {
    throw new RuntimeException('The private test root is invalid.');
}

$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$server->exec('DROP DATABASE IF EXISTS chef_alacarte_test');
$server->exec('CREATE DATABASE chef_alacarte_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

if (!is_dir($privateRoot . '/logs') && !mkdir($privateRoot . '/logs', 0700, true)) {
    throw new RuntimeException('The private test directory could not be created.');
}
$publicDocumentRoot = $privateRoot . '/task04-public-document';
$legacyPublicRoot = $privateRoot . '/task04-legacy-public';
foreach ([$publicDocumentRoot, $legacyPublicRoot, $privateRoot . '/task04-private', $privateRoot . '/task04-temporary'] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
        throw new RuntimeException('A Task 04 test storage directory could not be created.');
    }
}

$dsn = "mysql:host={$host};port={$port};dbname=chef_alacarte_test;charset=utf8mb4";
$hmacKey = bin2hex(random_bytes(32));
$adminPassword = bin2hex(random_bytes(18));
$changedAdminPassword = bin2hex(random_bytes(18));
$recoveredAdminPassword = bin2hex(random_bytes(18));
$readonlyPassword = bin2hex(random_bytes(18));
$dummyHash = password_hash(bin2hex(random_bytes(18)), PASSWORD_DEFAULT);
$base = [
    'database' => ['dsn' => $dsn, 'username' => $user, 'password' => $password],
    'migration_database' => ['dsn' => $dsn, 'username' => $user, 'password' => $password],
    'application' => ['logo_url' => 'assets/chef-logo.svg'],
    'media' => [
        'public_document_root' => $publicDocumentRoot,
        'public_root' => $publicDocumentRoot . '/media',
        'private_root' => $privateRoot . '/task04-private',
        'temporary_root' => $privateRoot . '/task04-temporary',
        'legacy_public_root' => $legacyPublicRoot,
        'public_base_url' => '/media',
        'max_bytes' => 4194304,
        'request_overhead_bytes' => 1048576,
        'max_pixels' => 12000000,
        'main_max_dimension' => 1200,
        'thumbnail_max_dimension' => 320,
        'webp_quality' => 80,
        'operation_ttl_seconds' => 60,
    ],
    'security' => [
        'trusted_proxies' => [],
        'session' => [
            'name' => 'CHEFADMIN', 'path' => '/hvk/admin', 'domain' => '', 'secure' => false,
            'idle_seconds' => 1800, 'absolute_seconds' => 28800,
        ],
        'throttle' => [
            'hmac_key' => $hmacKey, 'dummy_password_hash' => $dummyHash,
            'window_seconds' => 900, 'account_limit' => 5, 'address_limit' => 10,
            'block_seconds' => 900, 'stale_seconds' => 172800,
        ],
    ],
];

$configs = [
    'config.php' => $base + ['logging' => ['file' => $privateRoot . '/logs/app.log']],
    'config.production-test.php' => array_replace_recursive($base, [
        'security' => [
            'environment' => 'production',
            'trusted_proxies' => ['127.0.0.1/32', '::1/128'],
            'session' => ['secure' => true, 'idle_seconds' => 1800, 'absolute_seconds' => 28800],
        ],
        'logging' => ['file' => $privateRoot . '/logs/app-production.log'],
    ]),
];
$configs['config.php']['security']['environment'] = 'development';

foreach ($configs as $name => $config) {
    $path = $privateRoot . '/' . $name;
    file_put_contents($path, "<?php\ndeclare(strict_types=1);\nreturn " . var_export($config, true) . ";\n");
    $secured = PHP_OS_FAMILY === 'Windows' ? chef_apply_windows_acl($path) : chmod($path, 0600);
    if (!$secured || !chef_private_file_is_safe($path)) {
        throw new RuntimeException('The private test configuration ACL is unsafe.');
    }
}

$config = $configs['config.php'];
$chefConfig = $config;
$pdo = chef_connect($config, true);
chef_migrate($pdo);
chef_seed($pdo, require dirname(__DIR__) . '/seed-data.php');
chef_media_prepare_storage();
$stmt = $pdo->prepare('INSERT INTO admins (username, password_hash, auth_version) VALUES (:username, :hash, 1)');
$stmt->execute([':username' => 'owner', ':hash' => password_hash($adminPassword, PASSWORD_DEFAULT)]);

$readonlyUser = 'chef_catalog_readonly';
$quotedReadonlyPassword = $server->quote($readonlyPassword);
$server->exec("DROP USER IF EXISTS '{$readonlyUser}'@'127.0.0.1'");
$server->exec("DROP USER IF EXISTS '{$readonlyUser}'@'%'");
$server->exec("CREATE USER '{$readonlyUser}'@'%' IDENTIFIED BY {$quotedReadonlyPassword}");
$server->exec("REVOKE ALL PRIVILEGES, GRANT OPTION FROM '{$readonlyUser}'@'%'");
$server->exec("GRANT SELECT ON chef_alacarte_test.* TO '{$readonlyUser}'@'%'");

$credentialsPath = $privateRoot . '/admin.credentials';
$credentials = [
    'admin_username' => 'owner',
    'admin_password' => $adminPassword,
    'changed_admin_password' => $changedAdminPassword,
    'recovered_admin_password' => $recoveredAdminPassword,
    'readonly' => ['dsn' => $dsn, 'username' => $readonlyUser, 'password' => $readonlyPassword],
];
file_put_contents($credentialsPath, json_encode($credentials, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$credentialsSecured = PHP_OS_FAMILY === 'Windows' ? chef_apply_windows_acl($credentialsPath) : chmod($credentialsPath, 0600);
if (!$credentialsSecured || !chef_private_file_is_safe($credentialsPath)) {
    throw new RuntimeException('The private test credentials ACL is unsafe.');
}

echo json_encode([
    'status' => 'PASS',
    'database' => 'chef_alacarte_test',
    'config' => $privateRoot . '/config.php',
    'production_config' => $privateRoot . '/config.production-test.php',
    'credentials' => $credentialsPath,
], JSON_UNESCAPED_SLASHES) . PHP_EOL;
