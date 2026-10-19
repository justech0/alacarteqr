<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/admin/security.php';

$config = chef_load_web_config();
$chefConfig = $config;
$chefLogFile = chef_prepare_log_file($config);
$pdo = chef_connect($config);
$admin = $pdo->query("SELECT id, username, auth_version FROM admins WHERE username = 'owner'")->fetch();
if (!$admin) {
    throw new RuntimeException('Test owner is missing.');
}

function expiry_session(array $admin, int $createdAt, int $lastSeenAt): void
{
    session_id(bin2hex(random_bytes(16)));
    chef_start_session();
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_name'] = (string)$admin['username'];
    $_SESSION['auth_version'] = (int)$admin['auth_version'];
    $_SESSION['created_at'] = $createdAt;
    $_SESSION['last_seen_at'] = $lastSeenAt;
}

$chefConfig['security']['session']['idle_seconds'] = 2;
$chefConfig['security']['session']['absolute_seconds'] = 100;
expiry_session($admin, time() - 10, time() - 3);
if (chef_session_valid($pdo)) {
    throw new RuntimeException('Idle expiration failed.');
}

$chefConfig['security']['session']['idle_seconds'] = 100;
$chefConfig['security']['session']['absolute_seconds'] = 5;
expiry_session($admin, time() - 6, time());
if (chef_session_valid($pdo)) {
    throw new RuntimeException('Absolute expiration failed.');
}

echo "Session expiration: PASS\n";
