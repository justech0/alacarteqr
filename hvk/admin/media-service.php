<?php
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(404);
    exit;
}

final class ChefMediaValidationException extends RuntimeException
{
}

final class ChefMediaStorageException extends RuntimeException
{
}

function chef_media_config(): array
{
    global $chefConfig;
    $config = $chefConfig['media'] ?? null;
    if (!is_array($config)) {
        throw new ChefMediaStorageException('Media configuration is unavailable.');
    }
    foreach (['public_document_root', 'public_root', 'private_root', 'temporary_root', 'legacy_public_root', 'public_base_url'] as $key) {
        if (!isset($config[$key]) || !is_string($config[$key]) || $config[$key] === '') {
            throw new ChefMediaStorageException("Media configuration is missing {$key}.");
        }
    }
    return $config;
}

function chef_media_owner_map(): array
{
    return [
        'product' => ['table' => 'products', 'directory' => 'products', 'scope' => 'public'],
        'category' => ['table' => 'categories', 'directory' => 'categories', 'scope' => 'public'],
        'campaign' => ['table' => 'campaigns', 'directory' => 'campaigns', 'scope' => 'public'],
        'feedback' => ['table' => 'feedbacks', 'directory' => 'feedback', 'scope' => 'private'],
    ];
}

function chef_media_owner(string $ownerType): array
{
    $owner = chef_media_owner_map()[$ownerType] ?? null;
    if (!is_array($owner)) {
        throw new ChefMediaStorageException('Media owner type is invalid.');
    }
    return $owner;
}

function chef_media_scope_root(string $scope): string
{
    $config = chef_media_config();
    $path = match ($scope) {
        'public' => $config['public_root'],
        'private' => $config['private_root'],
        'temporary' => $config['temporary_root'],
        'legacy' => $config['legacy_public_root'],
        default => throw new ChefMediaStorageException('Media storage scope is invalid.'),
    };
    $resolved = realpath($path);
    if ($resolved === false || !is_dir($resolved) || is_link($path)) {
        throw new ChefMediaStorageException('Media storage root is unavailable.');
    }
    return $resolved;
}

function chef_media_validate_roots(): void
{
    $config = chef_media_config();
    $documentRoot = realpath($config['public_document_root']);
    $publicRoot = realpath($config['public_root']);
    $privateRoot = realpath($config['private_root']);
    $temporaryRoot = realpath($config['temporary_root']);
    if ($documentRoot === false || $publicRoot === false || $privateRoot === false || $temporaryRoot === false) {
        throw new ChefMediaStorageException('Media storage roots are unavailable.');
    }
    if (!chef_path_inside($publicRoot, $documentRoot)) {
        throw new ChefMediaStorageException('Public media root is outside the public document root.');
    }
    if (chef_path_inside($privateRoot, $documentRoot) || chef_path_inside($temporaryRoot, $documentRoot)) {
        throw new ChefMediaStorageException('Private media storage is inside the public document root.');
    }
    foreach ([$config['public_document_root'], $config['public_root'], $config['private_root'], $config['temporary_root']] as $root) {
        if (chef_media_path_has_symlink($root)) {
            throw new ChefMediaStorageException('A media storage root is a symlink.');
        }
    }
}

function chef_media_path_has_symlink(string $path): bool
{
    $absolute = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    $prefix = preg_match('/^[A-Za-z]:\\\\/', $absolute) === 1 ? substr($absolute, 0, 3) : DIRECTORY_SEPARATOR;
    $remainder = ltrim(substr($absolute, strlen($prefix)), DIRECTORY_SEPARATOR);
    $current = rtrim($prefix, DIRECTORY_SEPARATOR);
    foreach (array_filter(explode(DIRECTORY_SEPARATOR, $remainder), 'strlen') as $part) {
        $current .= DIRECTORY_SEPARATOR . $part;
        if (is_link($current)) {
            return true;
        }
    }
    return false;
}

function chef_media_prepare_storage(): void
{
    $config = chef_media_config();
    foreach ([$config['public_document_root'], $config['legacy_public_root']] as $existing) {
        if (realpath($existing) === false || !is_dir($existing)) {
            throw new ChefMediaStorageException('An existing media parent directory is unavailable.');
        }
    }
    foreach ([$config['public_root'], $config['private_root'], $config['temporary_root']] as $root) {
        if (!is_dir($root) && !mkdir($root, 0700, true)) {
            throw new ChefMediaStorageException('A media storage root could not be created.');
        }
    }
    foreach (chef_media_owner_map() as $owner) {
        $root = $owner['scope'] === 'public' ? $config['public_root'] : $config['private_root'];
        $directory = $root . DIRECTORY_SEPARATOR . $owner['directory'];
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
            throw new ChefMediaStorageException('A media owner directory could not be created.');
        }
    }
    foreach ([$config['public_root'], $config['private_root']] as $root) {
        $pending = $root . DIRECTORY_SEPARATOR . '.pending';
        if (!is_dir($pending) && !mkdir($pending, 0700, true)) {
            throw new ChefMediaStorageException('A media pending directory could not be created.');
        }
    }
    $hardening = "Options -Indexes\n\n<FilesMatch \"\\.(?:php[0-9]?|phtml|phar|cgi|pl|py|sh|exe|dll)$\">\n    Require all denied\n</FilesMatch>\n\nRemoveHandler .php .phtml .phar\nRemoveType .php .phtml .phar\n";
    $hardeningPath = $config['public_root'] . DIRECTORY_SEPARATOR . '.htaccess';
    if (is_link($hardeningPath) || (file_exists($hardeningPath) && !is_file($hardeningPath))) {
        throw new ChefMediaStorageException('The public media hardening file is unsafe.');
    }
    if (!file_exists($hardeningPath)) {
        $handle = @fopen($hardeningPath, 'xb');
        if ($handle === false) {
            throw new ChefMediaStorageException('The public media hardening file could not be created.');
        }
        try {
            if (fwrite($handle, $hardening) !== strlen($hardening) || !fflush($handle)) {
                throw new ChefMediaStorageException('The public media hardening file could not be written.');
            }
        } finally {
            fclose($handle);
        }
        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($hardeningPath, 0644);
        }
    } elseif (!hash_equals(hash('sha256', $hardening), (string)hash_file('sha256', $hardeningPath))) {
        throw new ChefMediaStorageException('The public media hardening file does not match the required policy.');
    }
    chef_media_validate_roots();
}

function chef_media_relative_path(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    if ($path === '' || str_starts_with($path, '/') || str_contains($path, '..') || str_contains($path, "\0")) {
        throw new ChefMediaStorageException('Media path is invalid.');
    }
    if (preg_match('#^[a-z0-9._/-]+$#', $path) !== 1) {
        throw new ChefMediaStorageException('Media path contains invalid characters.');
    }
    return $path;
}

function chef_media_absolute_path(string $scope, string $relativePath): string
{
    $relativePath = chef_media_relative_path($relativePath);
    $root = chef_media_scope_root($scope);
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $parent = realpath(dirname($path));
    if ($parent === false || !chef_path_inside($parent, $root) || is_link(dirname($path))) {
        throw new ChefMediaStorageException('Media path parent is unsafe.');
    }
    if ((file_exists($path) || is_link($path)) && is_link($path)) {
        throw new ChefMediaStorageException('Media path is a symlink.');
    }
    return $path;
}

function chef_media_ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '' || $value === '-1') {
        return -1;
    }
    $number = (float)$value;
    return match (strtolower(substr($value, -1))) {
        'g' => (int)($number * 1024 * 1024 * 1024),
        'm' => (int)($number * 1024 * 1024),
        'k' => (int)($number * 1024),
        default => (int)$number,
    };
}

function chef_media_limits(): array
{
    $config = chef_media_config();
    $maxBytes = (int)($config['max_bytes'] ?? 4194304);
    $overhead = (int)($config['request_overhead_bytes'] ?? 1048576);
    $uploadLimit = chef_media_ini_bytes((string)ini_get('upload_max_filesize'));
    $postLimit = chef_media_ini_bytes((string)ini_get('post_max_size'));
    if (($uploadLimit !== -1 && $uploadLimit < $maxBytes) || ($postLimit !== -1 && $postLimit < $maxBytes + $overhead)) {
        throw new ChefMediaStorageException('PHP request limits are below the configured media limits.');
    }
    return [
        'max_bytes' => $maxBytes,
        'max_request_bytes' => $maxBytes + $overhead,
        'max_pixels' => (int)($config['max_pixels'] ?? 12000000),
        'main_dimension' => (int)($config['main_max_dimension'] ?? 1200),
        'thumbnail_dimension' => (int)($config['thumbnail_max_dimension'] ?? 320),
        'quality' => (int)($config['webp_quality'] ?? 80),
        'ttl' => (int)($config['operation_ttl_seconds'] ?? 3600),
    ];
}

function chef_media_assert_request_size(): void
{
    $limits = chef_media_limits();
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > 0 && $contentLength > $limits['max_request_bytes']) {
        throw new ChefMediaValidationException('The upload request is too large.');
    }
}

function chef_media_insert_record(PDO $pdo, array $row): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO managed_uploads (
            operation_id, owner_type, owner_id, purpose, file_role, storage_scope,
            storage_path, temporary_path, pending_path, state, mime_type, byte_size,
            width, height, parent_upload_id, legacy_reference, expires_at
        ) VALUES (
            :operation_id, :owner_type, :owner_id, :purpose, :file_role, :storage_scope,
            :storage_path, :temporary_path, :pending_path, :state, :mime_type, :byte_size,
            :width, :height, :parent_upload_id, :legacy_reference, :expires_at
        )'
    );
    $stmt->execute([
        ':operation_id' => $row['operation_id'],
        ':owner_type' => $row['owner_type'],
        ':owner_id' => $row['owner_id'],
        ':purpose' => $row['purpose'],
        ':file_role' => $row['file_role'],
        ':storage_scope' => $row['storage_scope'],
        ':storage_path' => $row['storage_path'],
        ':temporary_path' => $row['temporary_path'],
        ':pending_path' => $row['pending_path'],
        ':state' => $row['state'],
        ':mime_type' => $row['mime_type'],
        ':byte_size' => $row['byte_size'],
        ':width' => $row['width'],
        ':height' => $row['height'],
        ':parent_upload_id' => $row['parent_upload_id'],
        ':legacy_reference' => $row['legacy_reference'] ?? null,
        ':expires_at' => $row['expires_at'],
    ]);
    return (int)$pdo->lastInsertId();
}

function chef_media_is_animated(string $path, string $mime): bool
{
    if ($mime === 'image/jpeg') {
        return false;
    }
    $data = file_get_contents($path, false, null, 0, 4194304);
    if (!is_string($data)) {
        throw new ChefMediaValidationException('Image content could not be inspected.');
    }
    return $mime === 'image/webp'
        ? str_contains($data, 'ANIM') || str_contains($data, 'ANMF')
        : str_contains($data, 'acTL');
}

function chef_media_assert_memory(int $width, int $height, array $limits): void
{
    $memoryLimit = chef_media_ini_bytes((string)ini_get('memory_limit'));
    if ($memoryLimit === -1) {
        return;
    }
    $mainScale = min(1, $limits['main_dimension'] / max($width, $height));
    $thumbScale = min(1, $limits['thumbnail_dimension'] / max($width, $height));
    $mainPixels = (int)ceil($width * $mainScale) * (int)ceil($height * $mainScale);
    $thumbPixels = (int)ceil($width * $thumbScale) * (int)ceil($height * $thumbScale);
    $estimate = ($width * $height * 12) + ($mainPixels * 4) + ($thumbPixels * 4) + 16777216;
    if ($memoryLimit - memory_get_usage(true) < $estimate) {
        throw new ChefMediaValidationException('Available memory is insufficient for this image.');
    }
}

function chef_media_decode(string $path, string $mime): GdImage
{
    $image = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($path),
        'image/png' => @imagecreatefrompng($path),
        'image/webp' => @imagecreatefromwebp($path),
        default => false,
    };
    if (!$image instanceof GdImage) {
        throw new ChefMediaValidationException('Image decoding failed.');
    }
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $orientation = (int)((@exif_read_data($path)['Orientation'] ?? 1));
        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => false,
        };
        if ($rotated instanceof GdImage) {
            imagedestroy($image);
            $image = $rotated;
        }
    }
    return $image;
}

function chef_media_resize(GdImage $source, int $maxDimension): GdImage
{
    $width = imagesx($source);
    $height = imagesy($source);
    $scale = min(1, $maxDimension / max($width, $height));
    $targetWidth = max(1, (int)round($width * $scale));
    $targetHeight = max(1, (int)round($height * $scale));
    $target = imagecreatetruecolor($targetWidth, $targetHeight);
    imagealphablending($target, false);
    imagesavealpha($target, true);
    $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
    imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
    if (!imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height)) {
        imagedestroy($target);
        throw new ChefMediaValidationException('Image resizing failed.');
    }
    return $target;
}

function chef_media_write_webp(GdImage $image, string $relativePath, int $quality): array
{
    if (!function_exists('imagewebp')) {
        throw new ChefMediaStorageException('WebP encoding is unavailable.');
    }
    $path = chef_media_absolute_path('temporary', $relativePath);
    if (file_exists($path) || is_link($path) || !imagewebp($image, $path, $quality)) {
        throw new ChefMediaStorageException('WebP encoding failed.');
    }
    $info = @getimagesize($path);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!is_array($info) || $mime !== 'image/webp' || filesize($path) <= 0) {
        @unlink($path);
        throw new ChefMediaStorageException('Encoded WebP verification failed.');
    }
    return ['bytes' => filesize($path), 'width' => $info[0], 'height' => $info[1]];
}

function chef_media_stage_upload(PDO $pdo, string $fieldName, string $ownerType): ?array
{
    $owner = chef_media_owner($ownerType);
    $limits = chef_media_limits();
    $upload = $_FILES[$fieldName] ?? null;
    if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    chef_media_assert_request_size();
    $error = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new ChefMediaValidationException(in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'The uploaded image is too large.'
            : 'The uploaded image could not be received.');
    }
    $source = (string)($upload['tmp_name'] ?? '');
    if ($source === '' || !is_uploaded_file($source)) {
        throw new ChefMediaValidationException('The uploaded file provenance is invalid.');
    }
    $actualSize = filesize($source);
    if ($actualSize === false || $actualSize < 1 || $actualSize > $limits['max_bytes']) {
        throw new ChefMediaValidationException('The uploaded image size is invalid.');
    }

    $operationId = bin2hex(random_bytes(16));
    $sourceRelative = $operationId . '-source.upload';
    $sourcePath = chef_media_absolute_path('temporary', $sourceRelative);
    if (!move_uploaded_file($source, $sourcePath)) {
        throw new ChefMediaStorageException('The uploaded file could not enter staging.');
    }
    $expires = gmdate('Y-m-d H:i:s', time() + $limits['ttl']);
    try {
    $basename = bin2hex(random_bytes(16));
    $directory = $owner['directory'];
    $mainTemporary = $operationId . '-main.webp';
    $thumbnailTemporary = $operationId . '-thumbnail.webp';
    $mainFinal = $directory . '/' . $basename . '.webp';
    $thumbnailFinal = $directory . '/' . $basename . '-thumb.webp';
    $mainPending = '.pending/' . $basename . '.webp.pending';
    $thumbnailPending = '.pending/' . $basename . '-thumb.webp.pending';
    $sourceId = chef_media_insert_record($pdo, [
            'operation_id' => $operationId, 'owner_type' => $ownerType, 'owner_id' => null,
            'purpose' => 'image', 'file_role' => 'source', 'storage_scope' => 'temporary',
            'storage_path' => $sourceRelative, 'temporary_path' => $sourceRelative, 'pending_path' => null,
            'state' => 'upload_staged', 'mime_type' => null, 'byte_size' => $actualSize,
            'width' => null, 'height' => null, 'parent_upload_id' => null, 'expires_at' => $expires,
        ]);
    } catch (Throwable $exception) {
        @unlink($sourcePath);
        throw $exception;
    }

    $mainId = chef_media_insert_record($pdo, [
        'operation_id' => $operationId, 'owner_type' => $ownerType, 'owner_id' => null,
        'purpose' => 'image', 'file_role' => 'main', 'storage_scope' => $owner['scope'],
        'storage_path' => $mainFinal, 'temporary_path' => $mainTemporary, 'pending_path' => $mainPending,
        'state' => 'upload_staged', 'mime_type' => 'image/webp', 'byte_size' => null,
        'width' => null, 'height' => null, 'parent_upload_id' => $sourceId, 'expires_at' => $expires,
    ]);
    $thumbnailId = chef_media_insert_record($pdo, [
        'operation_id' => $operationId, 'owner_type' => $ownerType, 'owner_id' => null,
        'purpose' => 'image', 'file_role' => 'thumbnail', 'storage_scope' => $owner['scope'],
        'storage_path' => $thumbnailFinal, 'temporary_path' => $thumbnailTemporary, 'pending_path' => $thumbnailPending,
        'state' => 'upload_staged', 'mime_type' => 'image/webp', 'byte_size' => null,
        'width' => null, 'height' => null, 'parent_upload_id' => $mainId, 'expires_at' => $expires,
    ]);

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($sourcePath);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new ChefMediaValidationException('The uploaded image type is unsupported.');
    }
    $info = @getimagesize($sourcePath);
    if (!is_array($info) || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > $limits['max_pixels']) {
        throw new ChefMediaValidationException('The uploaded image dimensions are invalid.');
    }
    if (chef_media_is_animated($sourcePath, $mime)) {
        throw new ChefMediaValidationException('Animated images are not supported.');
    }
    chef_media_assert_memory($info[0], $info[1], $limits);
    $decoded = chef_media_decode($sourcePath, $mime);
    $main = chef_media_resize($decoded, $limits['main_dimension']);
    $thumbnail = chef_media_resize($decoded, $limits['thumbnail_dimension']);
    imagedestroy($decoded);

    try {
        $mainInfo = chef_media_write_webp($main, $mainTemporary, $limits['quality']);
        $thumbnailInfo = chef_media_write_webp($thumbnail, $thumbnailTemporary, $limits['quality']);
    } finally {
        imagedestroy($main);
        imagedestroy($thumbnail);
    }

    $updateEncoded = $pdo->prepare(
        "UPDATE managed_uploads SET state = 'encoded', byte_size = :bytes, width = :width, height = :height WHERE id = :id"
    );
    $updateEncoded->execute([':bytes' => $mainInfo['bytes'], ':width' => $mainInfo['width'], ':height' => $mainInfo['height'], ':id' => $mainId]);
    $updateEncoded->execute([':bytes' => $thumbnailInfo['bytes'], ':width' => $thumbnailInfo['width'], ':height' => $thumbnailInfo['height'], ':id' => $thumbnailId]);
    $pdo->prepare("UPDATE managed_uploads SET state = 'encoded', mime_type = :mime, width = :width, height = :height WHERE id = :id")
        ->execute([':mime' => $mime, ':width' => $info[0], ':height' => $info[1], ':id' => $sourceId]);
    return ['operation_id' => $operationId, 'main_id' => $mainId, 'thumbnail_id' => $thumbnailId];
}

function chef_media_promote_row(array $row, ?callable $checkpoint = null): void
{
    $temporary = chef_media_absolute_path('temporary', (string)$row['temporary_path']);
    $pending = chef_media_absolute_path((string)$row['storage_scope'], (string)$row['pending_path']);
    $final = chef_media_absolute_path((string)$row['storage_scope'], (string)$row['storage_path']);
    if (!is_file($temporary) || is_link($temporary) || file_exists($pending) || file_exists($final)) {
        throw new ChefMediaStorageException('Media promotion paths are unsafe.');
    }
    $input = fopen($temporary, 'rb');
    $output = fopen($pending, 'x+b');
    if ($input === false || $output === false) {
        if (is_resource($input)) fclose($input);
        if (is_resource($output)) fclose($output);
        throw new ChefMediaStorageException('Media pending file could not be created.');
    }
    try {
        if (stream_copy_to_stream($input, $output) === false || !fflush($output)) {
            throw new ChefMediaStorageException('Media pending copy failed.');
        }
    } finally {
        fclose($input);
        fclose($output);
    }
    if (!hash_equals((string)hash_file('sha256', $temporary), (string)hash_file('sha256', $pending)) || !rename($pending, $final)) {
        @unlink($pending);
        throw new ChefMediaStorageException('Media final promotion failed.');
    }
    if ($checkpoint !== null) {
        $checkpoint('after_' . $row['file_role'] . '_promotion');
    }
}

function chef_media_database_reference(array $row): string
{
    if (!empty($row['legacy_reference'])) {
        return (string)$row['legacy_reference'];
    }
    if ($row['storage_scope'] === 'private') {
        return 'managed:' . $row['id'];
    }
    $base = rtrim((string)chef_media_config()['public_base_url'], '/');
    return $base . '/' . ltrim((string)$row['storage_path'], '/');
}

function chef_media_lock_owner(PDO $pdo, string $ownerType, int $ownerId): array
{
    $table = chef_media_owner($ownerType)['table'];
    $stmt = $pdo->prepare("SELECT id, image_path FROM `{$table}` WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $ownerId]);
    $owner = $stmt->fetch();
    if (!$owner) {
        throw new ChefMediaStorageException('Media owner does not exist.');
    }
    return $owner;
}

function chef_media_activate(PDO $pdo, array $stage, string $ownerType, int $ownerId, ?callable $checkpoint = null): string
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Media activation requires an owner transaction.');
    }
    $owner = chef_media_lock_owner($pdo, $ownerType, $ownerId);
    $stmt = $pdo->prepare('SELECT * FROM managed_uploads WHERE operation_id = :operation_id ORDER BY id FOR UPDATE');
    $stmt->execute([':operation_id' => $stage['operation_id']]);
    $rows = $stmt->fetchAll();
    if (count($rows) !== 3) {
        throw new ChefMediaStorageException('Media operation is incomplete.');
    }
    $oldPath = (string)($owner['image_path'] ?? '');
    $state = $oldPath === '' ? 'activation_pending' : 'replacement_pending';
    $pdo->prepare('UPDATE managed_uploads SET owner_type = :owner_type, owner_id = :owner_id, state = :state WHERE operation_id = :operation_id')
        ->execute([':owner_type' => $ownerType, ':owner_id' => $ownerId, ':state' => $state, ':operation_id' => $stage['operation_id']]);
    $main = null;
    foreach ($rows as $row) {
        if (in_array($row['file_role'], ['main', 'thumbnail'], true)) {
            chef_media_promote_row($row, $checkpoint);
            if ($row['file_role'] === 'main') {
                $main = $row;
            }
        }
    }
    if (!is_array($main)) {
        throw new ChefMediaStorageException('Media main file is missing.');
    }
    $pdo->prepare("UPDATE managed_uploads SET state = 'active', expires_at = NULL WHERE operation_id = :operation_id AND file_role IN ('main', 'thumbnail')")
        ->execute([':operation_id' => $stage['operation_id']]);
    $pdo->prepare("UPDATE managed_uploads SET state = 'cleanup_pending', expires_at = NULL WHERE operation_id = :operation_id AND file_role = 'source'")
        ->execute([':operation_id' => $stage['operation_id']]);
    if ($oldPath !== '') {
        $pdo->prepare("UPDATE managed_uploads SET state = 'cleanup_pending' WHERE owner_type = :owner_type AND owner_id = :owner_id AND state = 'active' AND operation_id <> :operation_id")
            ->execute([':owner_type' => $ownerType, ':owner_id' => $ownerId, ':operation_id' => $stage['operation_id']]);
    }
    if ($checkpoint !== null) {
        $checkpoint('before_owner_commit');
    }
    return chef_media_database_reference($main);
}

function chef_media_abandon_operation(PDO $pdo, string $operationId, string $errorCode = 'operation_failed'): array
{
    if ($pdo->inTransaction()) {
        throw new LogicException('A media operation can be abandoned only after rollback.');
    }
    $stmt = $pdo->prepare(
        "UPDATE managed_uploads
         SET state = 'cleanup_pending', last_error_code = :error_code, last_error_at = UTC_TIMESTAMP(), expires_at = NULL
         WHERE operation_id = :operation_id AND state <> 'active' AND state <> 'deleted'"
    );
    $stmt->execute([':error_code' => substr($errorCode, 0, 32), ':operation_id' => $operationId]);
    return chef_media_cleanup($pdo, 100, $operationId);
}

function chef_media_reference_count(PDO $pdo, string $reference): int
{
    $count = 0;
    foreach (chef_media_owner_map() as $owner) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$owner['table']}` WHERE image_path = :path");
        $stmt->execute([':path' => $reference]);
        $count += (int)$stmt->fetchColumn();
    }
    return $count;
}

function chef_media_delete_path(string $scope, ?string $relativePath, ?callable $unlink = null): bool
{
    if ($relativePath === null || $relativePath === '') {
        return true;
    }
    $path = chef_media_absolute_path($scope, $relativePath);
    if (!file_exists($path) && !is_link($path)) {
        return true;
    }
    if (is_link($path) || !is_file($path)) {
        return false;
    }
    $deleted = $unlink ? $unlink($path) : @unlink($path);
    clearstatcache(true, $path);
    return $deleted && !file_exists($path) && !is_link($path);
}

function chef_media_finalize_operation(PDO $pdo, string $operationId): void
{
    $stmt = $pdo->prepare('SELECT * FROM managed_uploads WHERE operation_id = :operation_id ORDER BY id');
    $stmt->execute([':operation_id' => $operationId]);
    foreach ($stmt->fetchAll() as $row) {
        if ($row['state'] === 'active') {
            $temporaryDeleted = chef_media_delete_path('temporary', $row['temporary_path']);
            $pendingDeleted = chef_media_delete_path($row['storage_scope'], $row['pending_path']);
            if ($temporaryDeleted && $pendingDeleted) {
                $pdo->prepare('UPDATE managed_uploads SET temporary_path = NULL, pending_path = NULL WHERE id = :id')
                    ->execute([':id' => $row['id']]);
            }
        }
    }
    chef_media_cleanup($pdo, 100, $operationId);
}

function chef_media_mark_owner_deletion(PDO $pdo, string $ownerType, int $ownerId): void
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Media deletion requires an owner transaction.');
    }
    $owner = chef_media_lock_owner($pdo, $ownerType, $ownerId);
    $stmt = $pdo->prepare('SELECT * FROM managed_uploads WHERE owner_type = :owner_type AND owner_id = :owner_id ORDER BY id FOR UPDATE');
    $stmt->execute([':owner_type' => $ownerType, ':owner_id' => $ownerId]);
    $rows = $stmt->fetchAll();
    if (($owner['image_path'] ?? '') !== '' && !$rows) {
        throw new ChefMediaStorageException('Legacy media ownership requires migration.');
    }
    foreach ($rows as $row) {
        if ($row['state'] === 'active' && chef_media_reference_count($pdo, chef_media_database_reference($row)) > 1) {
            throw new ChefMediaStorageException('Active shared media cannot be deleted.');
        }
    }
    $pdo->prepare("UPDATE managed_uploads SET state = 'cleanup_pending' WHERE owner_type = :owner_type AND owner_id = :owner_id AND state <> 'deleted'")
        ->execute([':owner_type' => $ownerType, ':owner_id' => $ownerId]);
}

function chef_media_cleanup(PDO $pdo, int $limit = 100, ?string $operationId = null, ?callable $unlink = null): array
{
    $lock = $pdo->query("SELECT GET_LOCK('chef_media_cleanup', 0)")->fetchColumn();
    if ((int)$lock !== 1) {
        return ['processed' => 0, 'deleted' => 0, 'pending' => 0];
    }
    $result = ['processed' => 0, 'deleted' => 0, 'pending' => 0];
    try {
        $params = [];
        $where = "state = 'cleanup_pending' OR (state IN ('upload_staged','encoded','activation_pending','replacement_pending') AND expires_at < UTC_TIMESTAMP())";
        if ($operationId !== null) {
            $where = "operation_id = :operation_id AND ({$where})";
            $params[':operation_id'] = $operationId;
        }
        $stmt = $pdo->prepare("SELECT DISTINCT operation_id FROM managed_uploads WHERE {$where} ORDER BY operation_id LIMIT " . max(1, $limit));
        $stmt->execute($params);
        $operations = array_filter(array_column($stmt->fetchAll(), 'operation_id'));
        foreach ($operations as $currentOperation) {
            $pdo->beginTransaction();
            try {
                $ownerStmt = $pdo->prepare('SELECT owner_type, owner_id FROM managed_uploads WHERE operation_id = :operation_id AND owner_id IS NOT NULL ORDER BY id LIMIT 1');
                $ownerStmt->execute([':operation_id' => $currentOperation]);
                $owner = $ownerStmt->fetch();
                if ($owner) {
                    $table = chef_media_owner((string)$owner['owner_type'])['table'];
                    $lockOwner = $pdo->prepare("SELECT id FROM `{$table}` WHERE id = :id FOR UPDATE");
                    $lockOwner->execute([':id' => (int)$owner['owner_id']]);
                }
                $rowsStmt = $pdo->prepare('SELECT * FROM managed_uploads WHERE operation_id = :operation_id ORDER BY id FOR UPDATE');
                $rowsStmt->execute([':operation_id' => $currentOperation]);
                $rows = $rowsStmt->fetchAll();
                foreach ($rows as $row) {
                    $result['processed']++;
                    if ($row['state'] === 'active' || $row['state'] === 'deleted' || $row['state'] === 'review_required') {
                        continue;
                    }
                    $reference = $row['file_role'] === 'source' ? '' : chef_media_database_reference($row);
                    if ($reference !== '' && chef_media_reference_count($pdo, $reference) > 0) {
                        $pdo->prepare("UPDATE managed_uploads SET state = 'cleanup_pending', last_error_code = 'active_reference', last_error_at = UTC_TIMESTAMP() WHERE id = :id")
                            ->execute([':id' => $row['id']]);
                        $result['pending']++;
                        continue;
                    }
                    $paths = [
                        ['temporary', $row['temporary_path']],
                        [$row['storage_scope'], $row['pending_path']],
                        [$row['storage_scope'], $row['storage_path']],
                    ];
                    $ok = true;
                    $seen = [];
                    foreach ($paths as [$scope, $path]) {
                        $key = $scope . ':' . (string)$path;
                        if ($path === null || isset($seen[$key])) continue;
                        $seen[$key] = true;
                        $ok = chef_media_delete_path((string)$scope, (string)$path, $unlink) && $ok;
                    }
                    if ($ok) {
                        $pdo->prepare("UPDATE managed_uploads SET state = 'deleted', temporary_path = NULL, pending_path = NULL, last_error_code = NULL, last_error_at = NULL WHERE id = :id")
                            ->execute([':id' => $row['id']]);
                        $result['deleted']++;
                    } else {
                        $pdo->prepare("UPDATE managed_uploads SET state = 'cleanup_pending', delete_attempts = delete_attempts + 1, last_error_code = 'physical_delete', last_error_at = UTC_TIMESTAMP() WHERE id = :id")
                            ->execute([':id' => $row['id']]);
                        $result['pending']++;
                    }
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            }
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('chef_media_cleanup')");
    }
    if ($operationId === null) {
        $result['orphans'] = chef_media_cleanup_orphan_temporary_files($pdo, $unlink);
    }
    return $result;
}

function chef_media_cleanup_orphan_temporary_files(PDO $pdo, ?callable $unlink = null): int
{
    $ttl = chef_media_limits()['ttl'];
    $cutoff = time() - $ttl;
    $root = chef_media_scope_root('temporary');
    $stmt = $pdo->query('SELECT temporary_path FROM managed_uploads WHERE temporary_path IS NOT NULL');
    $tracked = array_fill_keys(array_filter(array_column($stmt->fetchAll(), 'temporary_path')), true);
    $deleted = 0;
    foreach (new DirectoryIterator($root) as $entry) {
        if ($entry->isDot() || !$entry->isFile() || $entry->isLink()) {
            continue;
        }
        $relative = $entry->getFilename();
        if (isset($tracked[$relative]) || $entry->getMTime() > $cutoff) {
            continue;
        }
        if (chef_media_delete_path('temporary', $relative, $unlink)) {
            $deleted++;
        }
    }
    return $deleted;
}

function chef_media_owner_files_remaining(PDO $pdo, string $ownerType, int $ownerId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM managed_uploads WHERE owner_type = :owner_type AND owner_id = :owner_id AND state <> 'deleted'");
    $stmt->execute([':owner_type' => $ownerType, ':owner_id' => $ownerId]);
    return (int)$stmt->fetchColumn();
}

function chef_media_image_url(?string $reference): ?string
{
    if ($reference === null || $reference === '') {
        return null;
    }
    if (str_starts_with($reference, 'managed:')) {
        return '/admin/private-image.php?id=' . (int)substr($reference, 8);
    }
    if (str_starts_with($reference, '/')) {
        return $reference;
    }
    return '/admin/' . ltrim($reference, '/');
}

function chef_media_private_row(PDO $pdo, int $uploadId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT mu.* FROM managed_uploads mu
         JOIN feedbacks f ON f.id = mu.owner_id AND f.image_path = CONCAT('managed:', mu.id)
         WHERE mu.id = :id AND mu.owner_type = 'feedback' AND mu.file_role = 'main'
           AND mu.storage_scope = 'private' AND mu.state = 'active' LIMIT 1"
    );
    $stmt->execute([':id' => $uploadId]);
    return $stmt->fetch() ?: null;
}

function chef_media_legacy_references(PDO $pdo): array
{
    $references = [];
    foreach (chef_media_owner_map() as $ownerType => $owner) {
        $stmt = $pdo->query("SELECT id, image_path FROM `{$owner['table']}` WHERE image_path IS NOT NULL AND image_path <> '' ORDER BY id");
        foreach ($stmt->fetchAll() as $row) {
            if (str_starts_with((string)$row['image_path'], 'managed:')) {
                continue;
            }
            $reference = str_replace('\\', '/', ltrim((string)$row['image_path'], '/'));
            $references[$reference][] = ['owner_type' => $ownerType, 'owner_id' => (int)$row['id']];
        }
    }
    return $references;
}

function chef_media_legacy_relative(string $reference): string
{
    $relative = preg_replace('#^(?:admin/)?uploads/#', '', ltrim(str_replace('\\', '/', $reference), '/'));
    return chef_media_relative_path((string)$relative);
}

function chef_media_inventory_legacy(PDO $pdo): array
{
    $references = chef_media_legacy_references($pdo);
    $ambiguous = [];
    $missing = [];
    foreach ($references as $reference => $owners) {
        if (count($owners) !== 1) {
            $ambiguous[] = $reference;
            continue;
        }
        try {
            $path = chef_media_absolute_path('legacy', chef_media_legacy_relative($reference));
            if (!is_file($path) || is_link($path)) {
                $missing[] = $reference;
            }
        } catch (Throwable) {
            $missing[] = $reference;
        }
    }
    $known = [];
    foreach (array_keys($references) as $reference) {
        try {
            $known[chef_media_legacy_relative($reference)] = true;
        } catch (Throwable) {
        }
    }
    $unknown = [];
    $root = chef_media_scope_root('legacy');
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $entry) {
        if (!$entry->isFile() || $entry->isLink()) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($entry->getPathname(), strlen($root) + 1));
        if (!isset($known[$relative])) {
            $unknown[] = $relative;
        }
    }
    sort($ambiguous);
    sort($missing);
    sort($unknown);
    return ['references' => count($references), 'ambiguous' => $ambiguous, 'missing' => $missing, 'unknown' => $unknown];
}

function chef_media_migrate_legacy(PDO $pdo): array
{
    $inventory = chef_media_inventory_legacy($pdo);
    $references = chef_media_legacy_references($pdo);
    $migrated = 0;
    foreach ($references as $reference => $owners) {
        if (count($owners) !== 1 || in_array($reference, $inventory['missing'], true)) {
            continue;
        }
        $ownerType = $owners[0]['owner_type'];
        $ownerId = $owners[0]['owner_id'];
        $existing = $pdo->prepare('SELECT id FROM managed_uploads WHERE owner_type = :owner_type AND owner_id = :owner_id AND legacy_reference = :reference LIMIT 1');
        $existing->execute([':owner_type' => $ownerType, ':owner_id' => $ownerId, ':reference' => $reference]);
        if ($existing->fetchColumn()) {
            continue;
        }
        $relative = chef_media_legacy_relative($reference);
        $sourcePath = chef_media_absolute_path('legacy', $relative);
        $info = @getimagesize($sourcePath);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($sourcePath);
        if (!is_array($info) || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $inventory['ambiguous'][] = $reference;
            continue;
        }
        if ($ownerType === 'feedback') {
            $operationId = bin2hex(random_bytes(16));
            $final = 'feedback/' . bin2hex(random_bytes(16)) . '.webp';
            $decoded = chef_media_decode($sourcePath, $mime);
            $converted = chef_media_resize($decoded, chef_media_limits()['main_dimension']);
            imagedestroy($decoded);
            $temporary = $operationId . '-legacy-main.webp';
            try {
                $output = chef_media_write_webp($converted, $temporary, chef_media_limits()['quality']);
            } finally {
                imagedestroy($converted);
            }
            $pending = '.pending/' . basename($final) . '.pending';
            $id = chef_media_insert_record($pdo, [
                'operation_id' => $operationId, 'owner_type' => $ownerType, 'owner_id' => $ownerId,
                'purpose' => 'image', 'file_role' => 'main', 'storage_scope' => 'private',
                'storage_path' => $final, 'temporary_path' => $temporary, 'pending_path' => $pending,
                'state' => 'encoded', 'mime_type' => 'image/webp', 'byte_size' => $output['bytes'],
                'width' => $output['width'], 'height' => $output['height'], 'parent_upload_id' => null,
                'legacy_reference' => null, 'expires_at' => gmdate('Y-m-d H:i:s', time() + chef_media_limits()['ttl']),
            ]);
            chef_media_insert_record($pdo, [
                'operation_id' => $operationId, 'owner_type' => $ownerType, 'owner_id' => $ownerId,
                'purpose' => 'legacy_source', 'file_role' => 'source', 'storage_scope' => 'legacy',
                'storage_path' => $relative, 'temporary_path' => null, 'pending_path' => null,
                'state' => 'cleanup_pending', 'mime_type' => $mime, 'byte_size' => filesize($sourcePath),
                'width' => $info[0], 'height' => $info[1], 'parent_upload_id' => null,
                'legacy_reference' => $reference, 'expires_at' => null,
            ]);
            $row = $pdo->query('SELECT * FROM managed_uploads WHERE id = ' . (int)$id)->fetch();
            chef_media_promote_row($row);
            $pdo->beginTransaction();
            try {
                chef_media_lock_owner($pdo, $ownerType, $ownerId);
                $pdo->prepare('SELECT id FROM managed_uploads WHERE id = :id FOR UPDATE')->execute([':id' => $id]);
                $pdo->prepare("UPDATE managed_uploads SET state = 'active', expires_at = NULL WHERE id = :id")->execute([':id' => $id]);
                $pdo->prepare('UPDATE feedbacks SET image_path = :reference WHERE id = :id')->execute([':reference' => 'managed:' . $id, ':id' => $ownerId]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                chef_media_abandon_operation($pdo, $operationId, 'legacy_migration');
                throw $exception;
            }
            chef_media_finalize_operation($pdo, $operationId);
        } else {
            chef_media_insert_record($pdo, [
                'operation_id' => bin2hex(random_bytes(16)), 'owner_type' => $ownerType, 'owner_id' => $ownerId,
                'purpose' => 'image', 'file_role' => 'main', 'storage_scope' => 'legacy',
                'storage_path' => $relative, 'temporary_path' => null, 'pending_path' => null,
                'state' => 'active', 'mime_type' => $mime, 'byte_size' => filesize($sourcePath),
                'width' => $info[0], 'height' => $info[1], 'parent_upload_id' => null,
                'legacy_reference' => $reference, 'expires_at' => null,
            ]);
        }
        $migrated++;
    }
    $inventory['migrated'] = $migrated;
    return $inventory;
}
