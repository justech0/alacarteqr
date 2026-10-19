<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/admin/security.php';

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

// Messages of this class contain no secrets. The CLI prints them.
final class RecoveryUsageError extends RuntimeException
{
}

// --create adds the first owner only while the admins table is empty.
function recovery_create_first_owner(PDO $pdo, string $username, string $password): void
{
    $username = chef_normalize_username($username);
    if ($username === '' || mb_strlen($username, 'UTF-8') > 64 || preg_match('/[\x00-\x1F\x7F\s]/u', $username) === 1) {
        throw new RecoveryUsageError('Owner username is invalid.');
    }
    if (strlen($password) < 12) {
        throw new RecoveryUsageError('Password must contain at least 12 characters.');
    }
    $count = (int)$pdo->query('SELECT COUNT(*) FROM admins FOR UPDATE')->fetchColumn();
    if ($count !== 0) {
        throw new RecoveryUsageError('An administrator already exists. Use recovery without --create.');
    }
    $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash, auth_version) VALUES (:username, :hash, 1)');
    $stmt->execute([':username' => $username, ':hash' => password_hash($password, PASSWORD_DEFAULT)]);
}

try {
    $file = null;
    $create = false;
    $hashOnly = false;
    foreach (array_slice($argv, 1) as $argument) {
        if (str_starts_with($argument, '--file=')) {
            $file = substr($argument, 7);
        } elseif ($argument === '--create') {
            $create = true;
        } elseif ($argument === '--hash-only') {
            $hashOnly = true;
        } else {
            throw new RecoveryUsageError('Only --file=<private-path>, --create, and --hash-only are supported.');
        }
    }

    // --hash-only prints a password_hash value for a manual phpMyAdmin insert. It reads no configuration.
    if ($hashOnly) {
        if ($file !== null || $create) {
            throw new RecoveryUsageError('--hash-only accepts no other option.');
        }
        $password = recovery_hidden_input('New password: ');
        $confirmation = recovery_hidden_input('Confirm password: ');
        if (!hash_equals($password, $confirmation) || strlen($password) < 12) {
            throw new RecoveryUsageError('Password confirmation failed or the password is shorter than 12 characters.');
        }
        fwrite(STDOUT, password_hash($password, PASSWORD_DEFAULT) . PHP_EOL);
        exit(0);
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
    if ($create) {
        $pdo->beginTransaction();
        recovery_create_first_owner($pdo, $username, $password);
        $pdo->commit();
        chef_log('info', 'owner_create_complete', 0);
        fwrite(STDOUT, "Owner creation complete.\n");
        exit(0);
    }
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
    fwrite(STDERR, 'Owner recovery failed.' . ($exception instanceof RecoveryUsageError ? ' ' . $exception->getMessage() : '') . PHP_EOL);
    exit(1);
}
