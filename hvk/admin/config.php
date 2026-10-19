<?php
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/security.php';
[$chefConfig, $pdo] = chef_bootstrap();

$applicationConfig = $chefConfig['application'] ?? [];
$LOGO_URL = (string)($applicationConfig['logo_url'] ?? 'assets/chef-logo.svg');

function get_menu_version(PDO $pdo): int
{
    $stmt = $pdo->prepare('SELECT meta_value FROM meta WHERE meta_key = :key LIMIT 1');
    $stmt->execute([':key' => 'menu_version']);
    $value = $stmt->fetchColumn();
    if ($value === false || !ctype_digit((string)$value)) {
        throw new RuntimeException('The menu version is missing or invalid.');
    }
    return max(1, (int)$value);
}

function bump_menu_version(PDO $pdo): int
{
    $stmt = $pdo->prepare('UPDATE meta SET meta_value = CAST(meta_value AS UNSIGNED) + 1 WHERE meta_key = :key');
    $stmt->execute([':key' => 'menu_version']);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('The menu version row is missing.');
    }
    return get_menu_version($pdo);
}
