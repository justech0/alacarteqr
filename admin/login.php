<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
chef_require_method(['GET', 'POST']);
chef_start_session();
chef_no_store();

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = is_scalar($_POST['username'] ?? null) ? trim((string)$_POST['username']) : '';
    if (!verify_csrf((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        $error = 'İstek doğrulanamadı.';
    } elseif (!chef_consume_submission_token((string)($_POST['submission_token'] ?? ''))) {
        http_response_code(409);
        $error = chef_submission_token_error();
    } else {
        $result = chef_login_attempt($pdo, $username, (string)($_POST['password'] ?? ''));
        if ($result['status'] === 'ok') {
            header('Location: index.php');
            exit;
        }
        if ($result['status'] === 'blocked') {
            http_response_code(429);
            header('Retry-After: ' . max(1, (int)$result['retry_after']));
            $error = 'Çok fazla giriş denemesi yapıldı. Daha sonra tekrar deneyin.';
        } elseif ($result['status'] === 'storage_error') {
            chef_public_error(503, 'Service unavailable.');
        } else {
            http_response_code(401);
            $error = 'Kullanıcı adı veya şifre hatalı.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <title>Chef À La Carte | Giriş</title>
    <link rel="icon" href="assets/chef-logo.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/admin.css?v=<?php echo (int)@filemtime(__DIR__ . '/assets/admin.css'); ?>">
    <script src="assets/admin.js?v=<?php echo (int)@filemtime(__DIR__ . '/assets/admin.js'); ?>" defer></script>
</head>
<body class="admin-shell flex min-h-screen items-center justify-center p-4">
    <main class="admin-panel w-full max-w-md rounded-2xl p-6 shadow-xl sm:p-8">
        <div class="mb-8 flex flex-col items-center gap-4 text-center">
            <span class="logo-badge flex h-24 w-24 items-center justify-center p-2">
                <img src="assets/chef-logo.svg" alt="Chef À La Carte" class="h-20 w-20 object-contain">
            </span>
            <div>
                <h1 class="text-2xl">Chef À La Carte</h1>
                <p class="admin-muted mt-1 text-sm">Yönetim paneli</p>
            </div>
        </div>
        <div role="status" aria-live="polite" class="admin-status mb-4 rounded-lg px-4 py-3 text-sm" <?php echo $error ? '' : 'hidden'; ?>><?php echo $error ? sanitize($error) : ''; ?></div>
        <form method="POST" class="space-y-4" data-mutation-form data-testid="login-form">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize(chef_csrf_token()); ?>">
            <input type="hidden" name="submission_token" value="<?php echo sanitize(chef_submission_token()); ?>">
            <div>
                <label for="username" class="admin-muted mb-2 block text-sm">Kullanıcı adı</label>
                <input id="username" type="text" name="username" value="<?php echo sanitize($username); ?>" required autocomplete="username" class="w-full rounded-lg px-4 py-3 text-sm" data-testid="login-username">
            </div>
            <div>
                <label for="password" class="admin-muted mb-2 block text-sm">Şifre</label>
                <input id="password" type="password" name="password" required autocomplete="current-password" class="w-full rounded-lg px-4 py-3 text-sm" data-testid="login-password" aria-describedby="login-error">
                <p id="login-error" role="alert" class="admin-field-error" <?php echo $error ? '' : 'hidden'; ?>><?php echo $error ? sanitize($error) : ''; ?></p>
            </div>
            <button type="submit" class="admin-button admin-button-primary w-full" data-testid="login-save">Giriş yap</button>
        </form>
    </main>
</body>
</html>
