<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/hvk/admin/security.php';
require_once dirname(__DIR__, 2) . '/hvk/admin/media-service.php';

$fixtureRoot = (string)getenv('CHEF_MEDIA_FIXTURES');
$chefConfig = chef_load_config((string)getenv('CHEF_CONFIG_FILE'));
$pdo = chef_connect($chefConfig);
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'chef_alacarte_test') {
    throw new RuntimeException('Media tests require chef_alacarte_test.');
}
chef_media_validate_roots();

function media_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function media_test_stage(PDO $pdo, string $fixture, string $ownerType = 'campaign'): array
{
    $owner = chef_media_owner($ownerType);
    $operationId = bin2hex(random_bytes(16));
    $basename = bin2hex(random_bytes(16));
    $source = $operationId . '-source.upload';
    $mainTemp = $operationId . '-main.webp';
    $thumbTemp = $operationId . '-thumbnail.webp';
    copy($fixture, chef_media_absolute_path('temporary', $source));
    copy($fixture, chef_media_absolute_path('temporary', $mainTemp));
    copy($fixture, chef_media_absolute_path('temporary', $thumbTemp));
    $info = getimagesize($fixture);
    $expires = gmdate('Y-m-d H:i:s', time() + 3600);
    $sourceId = chef_media_insert_record($pdo, [
        'operation_id' => $operationId, 'owner_type' => $ownerType, 'owner_id' => null,
        'purpose' => 'image', 'file_role' => 'source', 'storage_scope' => 'temporary',
        'storage_path' => $source, 'temporary_path' => $source, 'pending_path' => null,
        'state' => 'encoded', 'mime_type' => 'image/webp', 'byte_size' => filesize($fixture),
        'width' => $info[0], 'height' => $info[1], 'parent_upload_id' => null, 'expires_at' => $expires,
    ]);
    $mainId = chef_media_insert_record($pdo, [
        'operation_id' => $operationId, 'owner_type' => $ownerType, 'owner_id' => null,
        'purpose' => 'image', 'file_role' => 'main', 'storage_scope' => $owner['scope'],
        'storage_path' => $owner['directory'] . '/' . $basename . '.webp', 'temporary_path' => $mainTemp,
        'pending_path' => '.pending/' . $basename . '.webp.pending', 'state' => 'encoded',
        'mime_type' => 'image/webp', 'byte_size' => filesize($fixture), 'width' => $info[0], 'height' => $info[1],
        'parent_upload_id' => $sourceId, 'expires_at' => $expires,
    ]);
    $thumbnailId = chef_media_insert_record($pdo, [
        'operation_id' => $operationId, 'owner_type' => $ownerType, 'owner_id' => null,
        'purpose' => 'image', 'file_role' => 'thumbnail', 'storage_scope' => $owner['scope'],
        'storage_path' => $owner['directory'] . '/' . $basename . '-thumb.webp', 'temporary_path' => $thumbTemp,
        'pending_path' => '.pending/' . $basename . '-thumb.webp.pending', 'state' => 'encoded',
        'mime_type' => 'image/webp', 'byte_size' => filesize($fixture), 'width' => $info[0], 'height' => $info[1],
        'parent_upload_id' => $mainId, 'expires_at' => $expires,
    ]);
    return ['operation_id' => $operationId, 'main_id' => $mainId, 'thumbnail_id' => $thumbnailId];
}

function media_operation_rows(PDO $pdo, string $operationId): array
{
    $stmt = $pdo->prepare('SELECT * FROM managed_uploads WHERE operation_id = :operation_id ORDER BY id');
    $stmt->execute([':operation_id' => $operationId]);
    return $stmt->fetchAll();
}

function media_activate_campaign(PDO $pdo, array $stage, ?callable $checkpoint = null): string
{
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO campaigns (id, is_active, image_path) VALUES (1, 0, NULL) ON DUPLICATE KEY UPDATE id = VALUES(id)')->execute();
    $reference = chef_media_activate($pdo, $stage, 'campaign', 1, $checkpoint);
    $pdo->prepare('UPDATE campaigns SET image_path = :image, is_active = 1 WHERE id = 1')->execute([':image' => $reference]);
    return $reference;
}

function media_cleanup_worker(): string
{
    $environment = getenv();
    $environment['CHEF_CONFIG_FILE'] = (string)getenv('CHEF_CONFIG_FILE');
    $worker = proc_open([PHP_BINARY, __DIR__ . '/media-db-control.php', 'cleanup'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2), $environment);
    media_assert(is_resource($worker), 'The concurrent cleanup worker did not start.');
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_get_status($worker);
    $exit = $status['exitcode'];
    $closedExit = proc_close($worker);
    if ($exit === -1) $exit = $closedExit;
    media_assert($exit === 0, 'Concurrent cleanup failed: exit=' . $exit . '; output=' . $output . '; error=' . $error);
    return $output;
}

$fixture = $fixtureRoot . '/valid.webp';
media_assert(is_file($fixture), 'The WebP fixture is missing.');
$pdo->prepare('INSERT INTO campaigns (id, is_active, image_path) VALUES (1, 0, NULL) ON DUPLICATE KEY UPDATE image_path = NULL, is_active = 0')->execute();

$crashChecks = [];
foreach (['after_main_promotion', 'after_thumbnail_promotion', 'before_owner_commit'] as $checkpointName) {
    $before = $pdo->query('SELECT image_path FROM campaigns WHERE id = 1')->fetchColumn();
    $stage = media_test_stage($pdo, $fixture);
    try {
        media_activate_campaign($pdo, $stage, static function (string $checkpoint) use ($checkpointName): void {
            if ($checkpoint === $checkpointName) throw new RuntimeException('simulated-process-stop');
        });
        throw new RuntimeException('The interruption checkpoint did not run.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        media_assert($exception->getMessage() === 'simulated-process-stop', 'An unexpected interruption error occurred.');
    }
    media_assert($pdo->query('SELECT image_path FROM campaigns WHERE id = 1')->fetchColumn() === $before, 'An interrupted replacement changed the previous image.');
    chef_media_abandon_operation($pdo, $stage['operation_id'], 'interrupted');
    foreach (media_operation_rows($pdo, $stage['operation_id']) as $row) {
        media_assert($row['state'] === 'deleted', 'Interrupted operation cleanup is incomplete.');
        foreach ([['temporary', $row['temporary_path']], [$row['storage_scope'], $row['pending_path']], [$row['storage_scope'], $row['storage_path']]] as [$scope, $path]) {
            if ($path !== null) media_assert(!file_exists(chef_media_absolute_path((string)$scope, (string)$path)), 'Interrupted operation left a file.');
        }
    }
    $crashChecks[] = $checkpointName;
}

$stage = media_test_stage($pdo, $fixture);
$rows = media_operation_rows($pdo, $stage['operation_id']);
$main = array_values(array_filter($rows, static fn(array $row): bool => $row['file_role'] === 'main'))[0];
$blockedPath = chef_media_absolute_path('public', $main['storage_path']);
mkdir($blockedPath, 0700);
try {
    media_activate_campaign($pdo, $stage);
    throw new RuntimeException('The storage failure was not detected.');
} catch (Throwable) {
    if ($pdo->inTransaction()) $pdo->rollBack();
}
rmdir($blockedPath);
chef_media_abandon_operation($pdo, $stage['operation_id'], 'storage_failed');

$active = media_test_stage($pdo, $fixture);
$reference = media_activate_campaign($pdo, $active);
$pdo->commit();
chef_media_finalize_operation($pdo, $active['operation_id']);

$uploadInProgress = media_test_stage($pdo, $fixture);
media_cleanup_worker();
$uploadRows = media_operation_rows($pdo, $uploadInProgress['operation_id']);
media_assert(count($uploadRows) === 3 && count(array_filter($uploadRows, static fn(array $row): bool => $row['state'] === 'encoded')) === 3, 'Cleanup changed an active upload operation.');
media_assert(is_file(chef_media_absolute_path('temporary', (string)$uploadRows[1]['temporary_path'])), 'Cleanup removed an active upload file.');
chef_media_abandon_operation($pdo, $uploadInProgress['operation_id'], 'test_complete');

$replacement = media_test_stage($pdo, $fixture);
$pdo->beginTransaction();
$replacementReference = chef_media_activate($pdo, $replacement, 'campaign', 1);
$workerOutput = media_cleanup_worker();
$pdo->prepare('UPDATE campaigns SET image_path = :image WHERE id = 1')->execute([':image' => $replacementReference]);
$pdo->commit();
chef_media_finalize_operation($pdo, $replacement['operation_id']);
media_assert($pdo->query('SELECT image_path FROM campaigns WHERE id = 1')->fetchColumn() === $replacementReference, 'Concurrent cleanup damaged replacement activation.');
media_assert(json_decode($workerOutput, true) !== null, 'Concurrent cleanup returned invalid evidence.');

$pdo->beginTransaction();
chef_media_mark_owner_deletion($pdo, 'campaign', 1);
$pdo->prepare('UPDATE campaigns SET image_path = NULL, is_active = 0 WHERE id = 1')->execute();
$pdo->commit();
$failedCleanup = chef_media_cleanup($pdo, 100, $replacement['operation_id'], static fn(string $path): bool => false);
media_assert($failedCleanup['pending'] >= 2, 'Main and thumbnail unlink failures were not retained.');
$retry = chef_media_cleanup($pdo, 100, $replacement['operation_id']);
media_assert($retry['deleted'] >= 2, 'Cleanup retry did not remove unreferenced files.');
$repeat = chef_media_cleanup($pdo, 100, $replacement['operation_id']);
media_assert($repeat['pending'] === 0, 'Repeated cleanup was not safe.');

$shared = media_test_stage($pdo, $fixture);
$sharedReference = media_activate_campaign($pdo, $shared);
$pdo->commit();
chef_media_finalize_operation($pdo, $shared['operation_id']);
$productId = (int)$pdo->query('SELECT id FROM products ORDER BY id LIMIT 1')->fetchColumn();
$pdo->prepare('UPDATE products SET image_path = :image WHERE id = :id')->execute([':image' => $sharedReference, ':id' => $productId]);
$pdo->beginTransaction();
try {
    chef_media_mark_owner_deletion($pdo, 'campaign', 1);
    throw new RuntimeException('Shared active media was accepted for deletion.');
} catch (ChefMediaStorageException) {
    $pdo->rollBack();
}
$pdo->prepare('UPDATE products SET image_path = NULL WHERE id = :id')->execute([':id' => $productId]);
$pdo->beginTransaction();
chef_media_mark_owner_deletion($pdo, 'campaign', 1);
$pdo->prepare('UPDATE campaigns SET image_path = NULL WHERE id = 1')->execute();
$pdo->commit();
chef_media_cleanup($pdo, 100, $shared['operation_id']);

try {
    chef_media_absolute_path('public', '../outside.webp');
    throw new RuntimeException('Traversal was accepted.');
} catch (ChefMediaStorageException) {
}

$orphan = chef_media_absolute_path('temporary', 'orphan.webp');
copy($fixture, $orphan);
touch($orphan, time() - 7200);
$orphanResult = chef_media_cleanup($pdo);
media_assert(($orphanResult['orphans'] ?? 0) >= 1 && !file_exists($orphan), 'Orphan temporary recovery failed.');

$symlinkResult = 'NOT RUN';
$link = chef_media_scope_root('temporary') . DIRECTORY_SEPARATOR . 'unsafe-link.webp';
if (@symlink($fixture, $link)) {
    try {
        chef_media_absolute_path('temporary', 'unsafe-link.webp');
        throw new RuntimeException('A symlink target was accepted.');
    } catch (ChefMediaStorageException) {
        $symlinkResult = 'PASS';
    } finally {
        unlink($link);
    }
}

$legacyRoot = chef_media_scope_root('legacy');
foreach (['products', 'feedback'] as $directory) {
    if (!is_dir($legacyRoot . '/' . $directory)) mkdir($legacyRoot . '/' . $directory, 0700, true);
}
copy($fixture, $legacyRoot . '/products/legacy.webp');
copy($fixture, $legacyRoot . '/feedback/legacy.webp');
copy($fixture, $legacyRoot . '/unknown.webp');
copy($fixture, $legacyRoot . '/shared.webp');
$ids = $pdo->query('SELECT id FROM products ORDER BY id LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
$categoryId = (int)$pdo->query('SELECT id FROM categories ORDER BY id LIMIT 1')->fetchColumn();
$pdo->prepare('UPDATE products SET image_path = :path WHERE id = :id')->execute([':path' => 'uploads/products/legacy.webp', ':id' => $ids[0]]);
$pdo->prepare('UPDATE products SET image_path = :path WHERE id = :id')->execute([':path' => 'uploads/shared.webp', ':id' => $ids[1]]);
$pdo->prepare('UPDATE categories SET image_path = :path WHERE id = :id')->execute([':path' => 'uploads/shared.webp', ':id' => $categoryId]);
$pdo->prepare("INSERT INTO feedbacks (customer_name, rating, comment, topic, image_path, language) VALUES (NULL, 5, 'legacy', 'other', 'uploads/feedback/legacy.webp', 'tr')")->execute();
$feedbackId = (int)$pdo->lastInsertId();
$inventory = chef_media_inventory_legacy($pdo);
media_assert(in_array('uploads/shared.webp', $inventory['ambiguous'], true), 'Ambiguous legacy ownership was not reported.');
media_assert(in_array('unknown.webp', $inventory['unknown'], true), 'Unknown legacy media was not reported.');
$migration = chef_media_migrate_legacy($pdo);
media_assert($migration['migrated'] === 2, 'Legacy ownership migration count is invalid.');
$feedbackReference = $pdo->query('SELECT image_path FROM feedbacks WHERE id = ' . $feedbackId)->fetchColumn();
media_assert(str_starts_with((string)$feedbackReference, 'managed:'), 'Legacy feedback media did not move to private storage.');
media_assert(!file_exists($legacyRoot . '/feedback/legacy.webp'), 'The previous feedback public file remains exposed.');
media_assert(file_exists($legacyRoot . '/shared.webp') && file_exists($legacyRoot . '/unknown.webp'), 'Ambiguous or unknown legacy media was deleted.');
$countBeforeRepeat = (int)$pdo->query('SELECT COUNT(*) FROM managed_uploads')->fetchColumn();
chef_media_migrate_legacy($pdo);
media_assert((int)$pdo->query('SELECT COUNT(*) FROM managed_uploads')->fetchColumn() === $countBeforeRepeat, 'Legacy ownership migration was not idempotent.');

echo json_encode([
    'status' => 'PASS',
    'crash_checkpoints' => $crashChecks,
    'unlink_retry' => 'PASS',
    'shared_reference' => 'PASS',
    'orphan_recovery' => 'PASS',
    'symlink' => $symlinkResult,
    'legacy_migration' => 'PASS',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
