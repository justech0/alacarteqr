<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/hvk/admin/security.php';
require_once dirname(__DIR__, 2) . '/hvk/admin/media-service.php';

$chefConfig = chef_load_config((string)getenv('CHEF_CONFIG_FILE'));
$pdo = chef_connect($chefConfig);
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'chef_alacarte_test') {
    throw new RuntimeException('Media tests require chef_alacarte_test.');
}
$command = $argv[1] ?? '';

function media_rows(PDO $pdo, ?string $ownerType = null, ?int $ownerId = null): array
{
    $sql = 'SELECT id, operation_id, owner_type, owner_id, file_role, storage_scope, storage_path, temporary_path, pending_path, state, mime_type, byte_size, width, height, delete_attempts, last_error_code FROM managed_uploads';
    $params = [];
    if ($ownerType !== null && $ownerId !== null) {
        $sql .= ' WHERE owner_type = :owner_type AND owner_id = :owner_id';
        $params = [':owner_type' => $ownerType, ':owner_id' => $ownerId];
    }
    $sql .= ' ORDER BY id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['files'] = [];
        foreach ([['temporary', $row['temporary_path']], [$row['storage_scope'], $row['pending_path']], [$row['storage_scope'], $row['storage_path']]] as [$scope, $path]) {
            if ($path === null || $path === '') continue;
            try {
                $absolute = chef_media_absolute_path((string)$scope, (string)$path);
                $row['files'][$scope . ':' . $path] = is_file($absolute) && !is_link($absolute);
            } catch (Throwable) {
                $row['files'][$scope . ':' . $path] = false;
            }
        }
    }
    return $rows;
}

if ($command === 'campaign-json') {
    $owner = $pdo->query('SELECT id, is_active, image_path FROM campaigns WHERE id = 1')->fetch() ?: null;
    echo json_encode(['owner' => $owner, 'uploads' => media_rows($pdo, 'campaign', 1)], JSON_UNESCAPED_SLASHES) . PHP_EOL;
} elseif ($command === 'feedback-json') {
    $owner = $pdo->query('SELECT id, image_path FROM feedbacks ORDER BY id DESC LIMIT 1')->fetch() ?: null;
    echo json_encode(['owner' => $owner, 'uploads' => $owner ? media_rows($pdo, 'feedback', (int)$owner['id']) : []], JSON_UNESCAPED_SLASHES) . PHP_EOL;
} elseif ($command === 'all-json') {
    echo json_encode(media_rows($pdo), JSON_UNESCAPED_SLASHES) . PHP_EOL;
} elseif ($command === 'expire-incomplete') {
    $pdo->exec("UPDATE managed_uploads SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND) WHERE state IN ('upload_staged','encoded','activation_pending','replacement_pending')");
    echo "PASS\n";
} elseif ($command === 'cleanup') {
    echo json_encode(chef_media_cleanup($pdo), JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    throw new RuntimeException('Unknown media test control command.');
}
