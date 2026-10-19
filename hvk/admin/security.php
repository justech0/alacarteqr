<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/database/lib.php';

if (chef_direct_request(__FILE__)) {
    http_response_code(404);
    exit;
}

const CHEF_LOG_FIELDS = [
    'timestamp', 'level', 'request_id', 'event', 'method', 'route', 'status',
    'exception_class', 'exception_code', 'message', 'source', 'line',
];

function chef_direct_request(string $file): bool
{
    $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
    return $script !== '' && realpath($script) === realpath($file);
}

function chef_is_api_request(): bool
{
    return str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/api/');
}

function chef_request_id(): string
{
    static $requestId;
    return $requestId ??= bin2hex(random_bytes(8));
}

function chef_absolute_path(string $path): bool
{
    return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
}

function chef_path_inside(string $path, string $parent): bool
{
    $path = strtolower(rtrim(str_replace('\\', '/', $path), '/')) . '/';
    $parent = strtolower(rtrim(str_replace('\\', '/', $parent), '/')) . '/';
    return str_starts_with($path, $parent);
}

function chef_document_root(): string
{
    $root = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
    // PHP CLI sets DOCUMENT_ROOT to an empty string. CLI tools read the environment value instead.
    if ($root === '' && PHP_SAPI === 'cli') {
        $root = (string)getenv('DOCUMENT_ROOT');
    }
    if ($root === '') {
        $root = dirname(__DIR__, 2);
    }
    return realpath($root) ?: $root;
}

function chef_windows_sddl(string $path): string
{
    $escaped = str_replace("'", "''", $path);
    $script = "(Get-Acl -LiteralPath '{$escaped}').Sddl";
    $encoded = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
    $output = [];
    $code = 1;
    exec('powershell.exe -NoProfile -NonInteractive -EncodedCommand ' . $encoded, $output, $code);
    return $code === 0 ? trim(implode('', $output)) : '';
}

function chef_windows_acl_is_restricted(string $path): bool
{
    $output = [];
    $code = 1;
    exec('icacls ' . escapeshellarg($path), $output, $code);
    $acl = implode("\n", $output);
    if ($code !== 0 || $acl === '') {
        return false;
    }
    foreach (['Everyone', 'Authenticated Users', 'BUILTIN\\Users', 'S-1-1-0', 'S-1-5-11', 'S-1-5-32-545'] as $forbidden) {
        if (stripos($acl, $forbidden) !== false) {
            return false;
        }
    }
    return str_contains($acl, '(F)');
}

function chef_apply_windows_acl(string $path): bool
{
    $user = trim((string)getenv('USERDOMAIN') . '\\' . (string)getenv('USERNAME'), '\\');
    if ($user === '') {
        return false;
    }
    $output = [];
    $code = 1;
    $command = 'icacls ' . escapeshellarg($path)
        . ' /inheritance:r /grant:r ' . escapeshellarg($user . ':(F)')
        . ' ' . escapeshellarg('*S-1-5-18:(F)')
        . ' ' . escapeshellarg('*S-1-5-32-544:(F)');
    exec($command, $output, $code);
    return $code === 0 && chef_windows_acl_is_restricted($path);
}

function chef_private_file_is_safe(string $path): bool
{
    if (!is_file($path) || is_link($path)) {
        return false;
    }
    if (PHP_OS_FAMILY === 'Windows') {
        return chef_windows_acl_is_restricted($path);
    }
    return (fileperms($path) & 0077) === 0;
}

function chef_load_web_config(): array
{
    $path = (string)getenv('CHEF_CONFIG_FILE');
    if ($path === '' || !chef_absolute_path($path) || is_link($path)) {
        throw new RuntimeException('Private configuration is unavailable.');
    }
    $resolved = realpath($path);
    if ($resolved === false || chef_path_inside($resolved, chef_document_root()) || !chef_private_file_is_safe($resolved)) {
        throw new RuntimeException('Private configuration is unsafe.');
    }
    $config = require $resolved;
    if (!is_array($config) || !isset($config['database'], $config['security'], $config['logging'])) {
        throw new RuntimeException('Private configuration is invalid.');
    }
    return $config;
}

function chef_prepare_log_file(array $config): string
{
    $path = (string)($config['logging']['file'] ?? '');
    if ($path === '' || !chef_absolute_path($path) || is_link($path)) {
        throw new RuntimeException('Private log target is invalid.');
    }
    $parent = dirname($path);
    $resolvedParent = realpath($parent);
    if ($resolvedParent === false || !is_dir($resolvedParent) || chef_path_inside($resolvedParent, chef_document_root())) {
        throw new RuntimeException('Private log directory is unavailable.');
    }
    if (file_exists($path)) {
        if (!is_file($path) || is_link($path) || !chef_private_file_is_safe($path)) {
            throw new RuntimeException('Private log target is unsafe.');
        }
        return $path;
    }
    $handle = @fopen($path, 'x');
    if ($handle === false) {
        throw new RuntimeException('Private log target cannot be created.');
    }
    fclose($handle);
    $secured = PHP_OS_FAMILY === 'Windows' ? chef_apply_windows_acl($path) : chmod($path, 0600);
    if (!$secured || !chef_private_file_is_safe($path)) {
        @unlink($path);
        throw new RuntimeException('Private log permissions are unsafe.');
    }
    return $path;
}

function chef_secret_values(array $config): array
{
    $values = [];
    $walk = static function (array $items) use (&$walk, &$values): void {
        foreach ($items as $key => $value) {
            if (is_array($value)) {
                $walk($value);
            } elseif (is_string($value) && preg_match('/pass|secret|token|key|dsn/i', (string)$key)) {
                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }
    };
    $walk($config);
    return array_unique($values);
}

function chef_clean_log_value(string $value, int $limit = 500): string
{
    global $chefConfig;
    foreach (chef_secret_values(is_array($chefConfig ?? null) ? $chefConfig : []) as $secret) {
        $value = str_replace($secret, '[redacted]', $value);
    }
    $value = preg_replace('/(?:mysql:|mariadb:)[^\s]+/i', '[redacted-dsn]', $value) ?? $value;
    $value = preg_replace('/(?:password|passwd|pwd|token|secret|authorization)\s*[:=]\s*[^\s,;]+/i', '$1=[redacted]', $value) ?? $value;
    $repo = realpath(dirname(__DIR__, 2));
    if ($repo !== false) {
        $value = str_ireplace(str_replace('\\', '/', $repo), '.', str_replace('\\', '/', $value));
    }
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
    return mb_substr(trim($value), 0, $limit);
}

function chef_log(string $level, string $event, int $status = 0, ?Throwable $exception = null, string $message = ''): void
{
    global $chefLogFile;
    if (!is_string($chefLogFile ?? null) || $chefLogFile === '') {
        return;
    }
    $record = [
        'timestamp' => gmdate('c'),
        'level' => chef_clean_log_value($level, 16),
        'request_id' => chef_request_id(),
        'event' => chef_clean_log_value($event, 64),
        'method' => chef_clean_log_value($_SERVER['REQUEST_METHOD'] ?? (PHP_SAPI === 'cli' ? 'CLI' : ''), 12),
        'route' => chef_clean_log_value(parse_url($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? ''), PHP_URL_PATH) ?: '', 180),
        'status' => $status,
        'exception_class' => $exception ? chef_clean_log_value($exception::class, 120) : '',
        'exception_code' => $exception ? (string)$exception->getCode() : '',
        'message' => chef_clean_log_value($message !== '' ? $message : ($exception?->getMessage() ?? ''), 500),
        'source' => $exception ? chef_clean_log_value($exception->getFile(), 240) : '',
        'line' => $exception?->getLine() ?? 0,
    ];
    $record = array_intersect_key($record, array_flip(CHEF_LOG_FIELDS));
    @file_put_contents($chefLogFile, json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function chef_public_error(int $status, string $message = 'Request failed.'): never
{
    http_response_code($status);
    header('Cache-Control: no-store');
    if (chef_is_api_request()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message, 'requestId' => chef_request_id()], JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Request failed</title><p>'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><p>Request ID: '
            . htmlspecialchars(chef_request_id(), ENT_QUOTES, 'UTF-8') . '</p>';
    }
    exit;
}

function chef_admin_error(string $event, Throwable $exception): string
{
    chef_log('error', $event, 500, $exception);
    return 'İşlem tamamlanamadı. İstek kodu: ' . chef_request_id();
}

// Admin pages load only same-origin assets. Keep this list in sync with header.php and login.php.
const CHEF_ADMIN_CSP = "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; "
    . "connect-src 'self'; font-src 'self'; manifest-src 'self'; form-action 'self'; "
    . "frame-ancestors 'none'; base-uri 'none'; object-src 'none'";

function chef_send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('Content-Security-Policy: ' . CHEF_ADMIN_CSP);
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cross-Origin-Opener-Policy: same-origin');
}

function chef_bootstrap(): array
{
    global $chefConfig, $chefLogFile;
    ini_set('display_errors', '0');
    chef_request_id();
    chef_send_security_headers();
    try {
        $chefConfig = chef_load_web_config();
        if (($chefConfig['security']['environment'] ?? 'production') === 'production' && chef_request_is_https() && !headers_sent()) {
            header('Strict-Transport-Security: max-age=31536000');
        }
        $chefLogFile = chef_prepare_log_file($chefConfig);
        $pdo = chef_connect($chefConfig);
        chef_assert_schema_current($pdo);
        set_exception_handler(static function (Throwable $exception): void {
            chef_log('error', 'unhandled_exception', 500, $exception);
            chef_public_error(500);
        });
        return [$chefConfig, $pdo];
    } catch (Throwable $exception) {
        chef_log('error', 'bootstrap_failed', 503, $exception);
        chef_public_error(503, 'Service unavailable.');
    }
}

function chef_security(): array
{
    global $chefConfig;
    return $chefConfig['security'] ?? [];
}

function chef_no_store(): void
{
    header('Cache-Control: no-store, private, max-age=0');
    header('Pragma: no-cache');
}

function chef_canonical_ip(string $ip): ?string
{
    $packed = @inet_pton(trim($ip));
    return $packed === false ? null : inet_ntop($packed);
}

function chef_ip_in_cidr(string $ip, string $cidr): bool
{
    [$network, $prefix] = array_pad(explode('/', $cidr, 2), 2, null);
    $ipBytes = @inet_pton($ip);
    $networkBytes = @inet_pton((string)$network);
    if ($ipBytes === false || $networkBytes === false || strlen($ipBytes) !== strlen($networkBytes)) {
        return false;
    }
    $bits = $prefix === null ? strlen($ipBytes) * 8 : filter_var($prefix, FILTER_VALIDATE_INT);
    if ($bits === false || $bits < 0 || $bits > strlen($ipBytes) * 8) {
        return false;
    }
    $full = intdiv((int)$bits, 8);
    $rest = (int)$bits % 8;
    if (substr($ipBytes, 0, $full) !== substr($networkBytes, 0, $full)) {
        return false;
    }
    if ($rest === 0) {
        return true;
    }
    $mask = (0xFF << (8 - $rest)) & 0xFF;
    return (ord($ipBytes[$full]) & $mask) === (ord($networkBytes[$full]) & $mask);
}

function chef_trusted_proxy(string $ip): bool
{
    foreach ((array)(chef_security()['trusted_proxies'] ?? []) as $cidr) {
        if (is_string($cidr) && chef_ip_in_cidr($ip, $cidr)) {
            return true;
        }
    }
    return false;
}

function chef_client_ip(array $server = null): string
{
    $server ??= $_SERVER;
    $peer = chef_canonical_ip((string)($server['REMOTE_ADDR'] ?? '')) ?? '0.0.0.0';
    if (!chef_trusted_proxy($peer)) {
        return $peer;
    }
    $forwarded = trim((string)($server['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($forwarded === '') {
        return $peer;
    }
    $chain = array_map('trim', explode(',', $forwarded));
    $resolved = [];
    foreach ($chain as $candidate) {
        $canonical = chef_canonical_ip($candidate);
        if ($canonical === null) {
            return $peer;
        }
        $resolved[] = $canonical;
    }
    $resolved[] = $peer;
    for ($index = count($resolved) - 1; $index >= 0; $index--) {
        if (!chef_trusted_proxy($resolved[$index])) {
            return $resolved[$index];
        }
    }
    return $peer;
}

function chef_request_is_https(array $server = null): bool
{
    $server ??= $_SERVER;
    $peer = chef_canonical_ip((string)($server['REMOTE_ADDR'] ?? '')) ?? '0.0.0.0';
    if (chef_trusted_proxy($peer) && isset($server['HTTP_X_FORWARDED_PROTO'])) {
        return strtolower(trim(explode(',', (string)$server['HTTP_X_FORWARDED_PROTO'])[0])) === 'https';
    }
    return isset($server['HTTPS']) && strtolower((string)$server['HTTPS']) !== 'off' && (string)$server['HTTPS'] !== '';
}

function chef_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $security = chef_security();
    $session = $security['session'] ?? [];
    $environment = (string)($security['environment'] ?? 'production');
    $secure = (bool)($session['secure'] ?? ($environment === 'production'));
    if ($environment === 'production' && (!$secure || !chef_request_is_https())) {
        chef_log('error', 'production_https_required', 503);
        chef_public_error(503, 'Service unavailable.');
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name((string)($session['name'] ?? 'CHEFADMIN'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => (string)($session['path'] ?? '/'),
        'domain' => (string)($session['domain'] ?? ''),
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
    chef_no_store();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function chef_csrf_token(): string
{
    chef_start_session();
    return (string)$_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool
{
    chef_start_session();
    return $token !== '' && hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token);
}

function chef_regenerate_csrf(): void
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function chef_destroy_session(): void
{
    chef_start_session();
    $_SESSION = [];
    $params = session_get_cookie_params();
    if (ini_get('session.use_cookies')) {
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Strict',
        ]);
    }
    session_destroy();
}

function chef_require_method(array $allowed): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        chef_public_error(405, 'Method not allowed.');
    }
}

function chef_session_valid(PDO $pdo): bool
{
    chef_start_session();
    if (!isset($_SESSION['admin_id'], $_SESSION['created_at'], $_SESSION['last_seen_at'], $_SESSION['auth_version'])) {
        return false;
    }
    $now = time();
    $session = chef_security()['session'] ?? [];
    $idle = (int)($session['idle_seconds'] ?? 1800);
    $absolute = (int)($session['absolute_seconds'] ?? 28800);
    if ($now - (int)$_SESSION['last_seen_at'] > $idle || $now - (int)$_SESSION['created_at'] > $absolute) {
        chef_destroy_session();
        return false;
    }
    try {
        $stmt = $pdo->prepare('SELECT id, username, auth_version FROM admins WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int)$_SESSION['admin_id']]);
        $admin = $stmt->fetch();
    } catch (Throwable $exception) {
        chef_log('error', 'admin_validation_failed', 503, $exception);
        chef_public_error(503, 'Service unavailable.');
    }
    if (!$admin || (int)$admin['auth_version'] !== (int)$_SESSION['auth_version']) {
        chef_destroy_session();
        return false;
    }
    $_SESSION['admin_name'] = $admin['username'];
    $_SESSION['last_seen_at'] = $now;
    return true;
}

function is_logged_in(): bool
{
    global $pdo;
    return chef_session_valid($pdo);
}

function require_login(): void
{
    if (!is_logged_in()) {
        if (chef_is_api_request()) {
            chef_public_error(401, 'Authentication required.');
        }
        header('Location: login.php', true, 302);
        exit;
    }
}

function chef_network_prefix(string $ip): string
{
    $packed = inet_pton($ip);
    if ($packed === false) {
        return 'invalid';
    }
    if (strlen($packed) === 4) {
        return inet_ntop(substr($packed, 0, 3) . "\0") . '/24';
    }
    return inet_ntop(substr($packed, 0, 7) . str_repeat("\0", 9)) . '/56';
}

function chef_signal_hash(string $value): string
{
    $key = (string)(chef_security()['throttle']['hmac_key'] ?? '');
    if (strlen($key) < 32) {
        throw new RuntimeException('Throttle HMAC key is invalid.');
    }
    return hash_hmac('sha256', $value, $key, true);
}

function chef_normalize_username(string $username): string
{
    return mb_strtolower(trim($username), 'UTF-8');
}

function chef_login_attempt(PDO $pdo, string $username, string $password): array
{
    $throttle = chef_security()['throttle'] ?? [];
    $window = (int)($throttle['window_seconds'] ?? 900);
    $block = (int)($throttle['block_seconds'] ?? 900);
    $stale = (int)($throttle['stale_seconds'] ?? 172800);
    $limits = ['account' => (int)($throttle['account_limit'] ?? 5), 'address' => (int)($throttle['address_limit'] ?? 10)];
    if ($window < 1 || $block < 1 || $stale < 1 || min($limits) < 1) {
        chef_log('error', 'login_throttle_config_invalid', 503);
        return ['status' => 'storage_error'];
    }
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $usernameKey = chef_normalize_username($username);
    $ip = chef_client_ip();
    $signals = [
        'account' => chef_signal_hash($usernameKey),
        'address' => chef_signal_hash($ip),
    ];
    ksort($signals);

    try {
        $pdo->beginTransaction();
        $cleanup = $pdo->prepare(
            'DELETE FROM admin_login_throttles
             WHERE updated_at < :cutoff AND (blocked_until IS NULL OR blocked_until < UTC_TIMESTAMP())'
        );
        $cleanup->execute([':cutoff' => $now->modify("-{$stale} seconds")->format('Y-m-d H:i:s')]);
        $insert = $pdo->prepare(
            'INSERT INTO admin_login_throttles (signal_type, signal_hash, window_started_at, failure_count, blocked_until)
             VALUES (:type, :hash, UTC_TIMESTAMP(), 0, NULL)
             ON DUPLICATE KEY UPDATE signal_hash = VALUES(signal_hash)'
        );
        foreach ($signals as $type => $hash) {
            $insert->execute([':type' => $type, ':hash' => $hash]);
        }
        $select = $pdo->prepare(
            'SELECT signal_type, window_started_at, failure_count, blocked_until
             FROM admin_login_throttles WHERE signal_type = :type AND signal_hash = :hash FOR UPDATE'
        );
        $rows = [];
        $retryAfter = 0;
        foreach ($signals as $type => $hash) {
            $select->execute([':type' => $type, ':hash' => $hash]);
            $rows[$type] = $select->fetch();
            if (!empty($rows[$type]['blocked_until'])) {
                $until = new DateTimeImmutable($rows[$type]['blocked_until'], new DateTimeZone('UTC'));
                $retryAfter = max($retryAfter, $until->getTimestamp() - $now->getTimestamp());
            }
        }
        if ($retryAfter > 0) {
            $pdo->commit();
            return ['status' => 'blocked', 'retry_after' => $retryAfter];
        }

        $stmt = $pdo->prepare('SELECT id, username, password_hash, auth_version FROM admins WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $usernameKey]);
        $admin = $stmt->fetch();
        $dummyHash = (string)($throttle['dummy_password_hash'] ?? '');
        $hash = $admin ? (string)$admin['password_hash'] : $dummyHash;
        if ($hash === '') {
            throw new RuntimeException('Dummy password hash is unavailable.');
        }
        $valid = password_verify($password, $hash) && is_array($admin);

        if ($valid) {
            $delete = $pdo->prepare('DELETE FROM admin_login_throttles WHERE signal_type = :type AND signal_hash = :hash');
            foreach ($signals as $type => $signalHash) {
                $delete->execute([':type' => $type, ':hash' => $signalHash]);
            }
            $pdo->commit();
            chef_start_session();
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_name'] = (string)$admin['username'];
            $_SESSION['auth_version'] = (int)$admin['auth_version'];
            $_SESSION['created_at'] = time();
            $_SESSION['last_seen_at'] = time();
            chef_regenerate_csrf();
            return ['status' => 'ok'];
        }

        $update = $pdo->prepare(
            'UPDATE admin_login_throttles
             SET window_started_at = :window_start, failure_count = :failures, blocked_until = :blocked_until
             WHERE signal_type = :type AND signal_hash = :hash'
        );
        foreach ($signals as $type => $signalHash) {
            $started = new DateTimeImmutable($rows[$type]['window_started_at'], new DateTimeZone('UTC'));
            $expired = $now->getTimestamp() - $started->getTimestamp() >= $window;
            $failures = $expired ? 1 : ((int)$rows[$type]['failure_count'] + 1);
            $windowStart = $expired ? $now : $started;
            $blockedUntil = $failures >= $limits[$type] ? $now->modify("+{$block} seconds")->format('Y-m-d H:i:s') : null;
            $update->execute([
                ':window_start' => $windowStart->format('Y-m-d H:i:s'),
                ':failures' => $failures,
                ':blocked_until' => $blockedUntil,
                ':type' => $type,
                ':hash' => $signalHash,
            ]);
        }
        $network = chef_signal_hash(chef_network_prefix($ip));
        $monitor = $pdo->prepare(
            "INSERT INTO admin_login_throttles (signal_type, signal_hash, window_started_at, failure_count, blocked_until)
             VALUES ('network', :hash, UTC_TIMESTAMP(), 1, NULL)
             ON DUPLICATE KEY UPDATE failure_count = failure_count + 1, updated_at = CURRENT_TIMESTAMP"
        );
        $monitor->execute([':hash' => $network]);
        $pdo->commit();
        return ['status' => 'invalid'];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        chef_log('error', 'login_storage_failed', 503, $exception);
        return ['status' => 'storage_error'];
    }
}

function chef_update_admin_password(PDO $pdo, int $adminId, string $newPassword): void
{
    if (strlen($newPassword) < 12) {
        throw new InvalidArgumentException('Password must contain at least 12 characters.');
    }
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE admins SET password_hash = :hash, auth_version = auth_version + 1 WHERE id = :id');
    $stmt->execute([':hash' => $hash, ':id' => $adminId]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Administrator recovery target was not found.');
    }
}

function chef_feedback_throttle_check(PDO $pdo, string $ip, int $limit = 30, int $window = 60, int $block = 300): ?int
{
    $signalHash = chef_signal_hash('fb:' . $ip);
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $stale = 86400;

    try {
        $pdo->beginTransaction();
        $cleanup = $pdo->prepare(
            "DELETE FROM admin_login_throttles
             WHERE signal_type = 'feedback' AND updated_at < :cutoff
               AND (blocked_until IS NULL OR blocked_until < UTC_TIMESTAMP())"
        );
        $cleanup->execute([':cutoff' => $now->modify("-{$stale} seconds")->format('Y-m-d H:i:s')]);

        $insert = $pdo->prepare(
            "INSERT INTO admin_login_throttles (signal_type, signal_hash, window_started_at, failure_count, blocked_until)
             VALUES ('feedback', :hash, UTC_TIMESTAMP(), 0, NULL)
             ON DUPLICATE KEY UPDATE signal_hash = VALUES(signal_hash)"
        );
        $insert->execute([':hash' => $signalHash]);

        $select = $pdo->prepare(
            "SELECT window_started_at, failure_count, blocked_until
             FROM admin_login_throttles
             WHERE signal_type = 'feedback' AND signal_hash = :hash FOR UPDATE"
        );
        $select->execute([':hash' => $signalHash]);
        $row = $select->fetch();

        if (!empty($row['blocked_until'])) {
            $until = new DateTimeImmutable($row['blocked_until'], new DateTimeZone('UTC'));
            $diff = $until->getTimestamp() - $now->getTimestamp();
            if ($diff > 0) {
                $pdo->commit();
                return $diff;
            }
        }

        $started = new DateTimeImmutable($row['window_started_at'] ?? 'now', new DateTimeZone('UTC'));
        $expired = ($now->getTimestamp() - $started->getTimestamp()) >= $window;
        $count = $expired ? 1 : ((int)($row['failure_count'] ?? 0) + 1);
        $windowStart = $expired ? $now : $started;
        $blockedUntil = null;
        $retryAfter = null;

        if ($count > $limit) {
            $blockedUntil = $now->modify("+{$block} seconds")->format('Y-m-d H:i:s');
            $retryAfter = $block;
        }

        $update = $pdo->prepare(
            "UPDATE admin_login_throttles
             SET window_started_at = :window_start, failure_count = :count, blocked_until = :blocked_until
             WHERE signal_type = 'feedback' AND signal_hash = :hash"
        );
        $update->execute([
            ':window_start' => $windowStart->format('Y-m-d H:i:s'),
            ':count' => $count,
            ':blocked_until' => $blockedUntil,
            ':hash' => $signalHash,
        ]);
        $pdo->commit();

        return $retryAfter;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        chef_log('error', 'feedback_throttle_failed', 500, $e);
        return null;
    }
}

