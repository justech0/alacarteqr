<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/hvk/admin/security.php';

function recovery_hidden_input(string $prompt): string
{
    fwrite(STDOUT, $prompt);
    if (PHP_OS_FAMILY === 'Windows') {
        $script = '$p=Read-Host -AsSecureString;' .
            '$b=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($p);' .
            'try{[Runtime.InteropServices.Marshal]::PtrToStringBSTR($b)}finally{[Runtime.InteropServices.Marshal]::ZeroFreeBSTR($b)}';
        $encoded = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
        $value = shell_exec('powershell.exe -NoProfile -NonInteractive -EncodedCommand ' . $encoded);
        fwrite(STDOUT, PHP_EOL);
        return rtrim((string)$value, "\r\n");
    }
    system('stty -echo');
    $value = rtrim((string)fgets(STDIN), "\r\n");
    system('stty echo');
    fwrite(STDOUT, PHP_EOL);
    return $value;
}

function recovery_read_file(string $path): array
{
    if (!chef_absolute_path($path) || is_link($path)) {
        throw new RuntimeException('Recovery file path is invalid.');
    }
    $resolved = realpath($path);
    if ($resolved === false || chef_path_inside($resolved, chef_document_root()) || !chef_private_file_is_safe($resolved)) {
        throw new RuntimeException('Recovery file is unsafe.');
    }
    $payload = json_decode((string)file_get_contents($resolved), true);
    if (!unlink($resolved)) {
        throw new RuntimeException('Consumed recovery file could not be removed.');
    }
    if (!is_array($payload) || !is_string($payload['username'] ?? null) || !is_string($payload['password'] ?? null)) {
        throw new RuntimeException('Recovery file content is invalid.');
    }
    return [$payload['username'], $payload['password']];
}

try {
    $file = null;
    foreach (array_slice($argv, 1) as $argument) {
        if (str_starts_with($argument, '--file=')) {
            $file = substr($argument, 7);
        } else {
            throw new RuntimeException('Only --file=<private-path> is supported.');
        }
    }

    if ($file !== null) {
        [$username, $password] = recovery_read_file($file);
    } else {
        fwrite(STDOUT, 'Owner username: ');
        $username = trim((string)fgets(STDIN));
        $password = recovery_hidden_input('New password: ');
        $confirmation = recovery_hidden_input('Confirm password: ');
        if (!hash_equals($password, $confirmation)) {
            throw new RuntimeException('Password confirmation does not match.');
        }
    }

    $config = chef_load_web_config();
    $chefConfig = $config;
    $chefLogFile = chef_prepare_log_file($config);
    $pdo = chef_connect($config, true);
    chef_assert_schema_current($pdo);
    $stmt = $pdo->prepare('SELECT id FROM admins WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => chef_normalize_username($username)]);
    $adminId = (int)$stmt->fetchColumn();
    if ($adminId <= 0) {
        throw new RuntimeException('Recovery target was not found.');
    }
    $pdo->beginTransaction();
    chef_update_admin_password($pdo, $adminId, $password);
    $pdo->commit();
    chef_log('info', 'owner_recovery_complete', 0);
    fwrite(STDOUT, "Owner recovery complete. Existing sessions are invalid.\n");
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (function_exists('chef_log')) {
        chef_log('error', 'owner_recovery_failed', 1, $exception);
    }
    fwrite(STDERR, 'Owner recovery failed.' . PHP_EOL);
    exit(1);
}
