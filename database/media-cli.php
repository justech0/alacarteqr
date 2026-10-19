<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/admin/security.php';
require_once dirname(__DIR__) . '/admin/media-service.php';

$command = $argv[1] ?? 'status';
$allowed = ['prepare', 'status', 'cleanup', 'inventory', 'migrate-legacy'];
if (!in_array($command, $allowed, true)) {
    fwrite(STDERR, "Usage: php database/media-cli.php prepare|status|cleanup|inventory|migrate-legacy\n");
    exit(2);
}

try {
    $chefConfig = chef_load_config();
    $pdo = chef_connect($chefConfig);
    chef_assert_schema_current($pdo);
    if ($command === 'prepare') {
        chef_media_prepare_storage();
        $result = ['status' => 'PASS', 'command' => $command];
    } elseif ($command === 'cleanup') {
        chef_media_validate_roots();
        $result = ['status' => 'PASS', 'command' => $command, 'result' => chef_media_cleanup($pdo)];
    } elseif ($command === 'inventory') {
        chef_media_validate_roots();
        $result = ['status' => 'PASS', 'command' => $command, 'result' => chef_media_inventory_legacy($pdo)];
    } elseif ($command === 'migrate-legacy') {
        chef_media_validate_roots();
        $result = ['status' => 'PASS', 'command' => $command, 'result' => chef_media_migrate_legacy($pdo)];
    } else {
        chef_media_validate_roots();
        $rows = $pdo->query('SELECT state, COUNT(*) AS count FROM managed_uploads GROUP BY state ORDER BY state')->fetchAll();
        $result = ['status' => 'PASS', 'command' => $command, 'schema' => CHEF_SCHEMA_VERSION, 'states' => $rows];
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Media command failed.' . PHP_EOL);
    exit(1);
}
