<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once dirname(__DIR__, 2) . '/admin/security.php';

$config = chef_load_web_config();
$chefConfig = $config;
$pdo = chef_connect($config, true);
$command = $argv[1] ?? '';
$credentialsPath = (string)getenv('CHEF_TEST_CREDENTIALS');
$credentials = $credentialsPath !== '' ? json_decode((string)file_get_contents($credentialsPath), true, 512, JSON_THROW_ON_ERROR) : [];

if ($command === 'reset-throttle') {
    $pdo->exec('TRUNCATE TABLE admin_login_throttles');
    echo "PASS\n";
    exit;
}
if ($command === 'throttle-json') {
    echo json_encode($pdo->query('SELECT signal_type, failure_count, blocked_until FROM admin_login_throttles ORDER BY signal_type')->fetchAll()) . PHP_EOL;
    exit;
}
if ($command === 'break-throttle') {
    $pdo->exec('RENAME TABLE admin_login_throttles TO admin_login_throttles_unavailable');
    echo "PASS\n";
    exit;
}
if ($command === 'restore-throttle') {
    $pdo->exec('RENAME TABLE admin_login_throttles_unavailable TO admin_login_throttles');
    echo "PASS\n";
    exit;
}
if ($command === 'break-admin-storage') {
    $pdo->exec('RENAME TABLE admins TO admins_unavailable');
    echo "PASS\n";
    exit;
}
if ($command === 'restore-admin-storage') {
    $pdo->exec('RENAME TABLE admins_unavailable TO admins');
    echo "PASS\n";
    exit;
}
if ($command === 'counts-json') {
    $counts = [];
    foreach (['admins', 'branches', 'campaigns', 'categories', 'feedbacks', 'meta', 'products', 'admin_login_throttles', 'managed_uploads'] as $table) {
        $counts[$table] = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }
    echo json_encode($counts) . PHP_EOL;
    exit;
}
if ($command === 'auth-version') {
    echo (string)$pdo->query("SELECT auth_version FROM admins WHERE username = 'owner'")->fetchColumn() . PHP_EOL;
    exit;
}
if ($command === 'change-owner-password') {
    $adminId = (int)$pdo->query("SELECT id FROM admins WHERE username = 'owner'")->fetchColumn();
    $pdo->beginTransaction();
    chef_update_admin_password($pdo, $adminId, (string)($credentials['changed_admin_password'] ?? ''));
    $pdo->commit();
    echo "PASS\n";
    exit;
}
if ($command === 'inventory-json') {
    echo json_encode(require __DIR__ . '/admin-route-inventory.php') . PHP_EOL;
    exit;
}
if ($command === 'make-recovery-file') {
    $path = $argv[2] ?? '';
    if (!chef_absolute_path($path) || chef_path_inside($path, chef_document_root())) {
        throw new RuntimeException('Recovery fixture path is invalid.');
    }
    file_put_contents($path, json_encode(['username' => 'owner', 'password' => (string)($credentials['recovered_admin_password'] ?? '')]));
    $secured = PHP_OS_FAMILY === 'Windows' ? chef_apply_windows_acl($path) : chmod($path, 0600);
    if (!$secured || !chef_private_file_is_safe($path)) {
        throw new RuntimeException('Recovery fixture permissions are unsafe.');
    }
    echo "PASS\n";
    exit;
}

fwrite(STDERR, "Unknown command.\n");
exit(64);
