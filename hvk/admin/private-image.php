<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login();
chef_require_method(['GET']);

$uploadId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!is_int($uploadId)) {
    http_response_code(404);
    exit;
}

try {
    $row = chef_media_private_row($pdo, $uploadId);
    if (!$row) {
        http_response_code(404);
        exit;
    }
    $path = chef_media_absolute_path('private', (string)$row['storage_path']);
    if (!is_file($path) || is_link($path)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: image/webp');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    header('Content-Length: ' . (string)filesize($path));
    readfile($path);
} catch (Throwable $exception) {
    chef_log('error', 'private_media_read_failed', 500, $exception);
    chef_public_error(500, 'Request failed.');
}
