<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/hvk/admin/security.php';

$payload = json_decode((string)stream_get_contents(STDIN), true);
if (!is_array($payload)) {
    exit(64);
}
$_SERVER['REMOTE_ADDR'] = (string)($payload['address'] ?? '198.51.100.8');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['SCRIPT_NAME'] = '/hvk/admin/login.php';
$config = chef_load_web_config();
$chefConfig = $config;
$chefLogFile = chef_prepare_log_file($config);
$pdo = chef_connect($config);
$result = chef_login_attempt($pdo, (string)($payload['username'] ?? ''), (string)($payload['password'] ?? ''));
echo json_encode($result) . PHP_EOL;
