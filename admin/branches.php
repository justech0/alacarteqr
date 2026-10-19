<?php
require_once __DIR__ . '/functions.php';
require_login();
chef_require_method(['GET', 'POST']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        flash_message('error', 'Geçersiz istek.');
        header('Location: branches.php');
        exit;
    }
    if (!chef_consume_submission_token((string)($_POST['submission_token'] ?? ''))) {
        flash_message('error', chef_submission_token_error());
        header('Location: branches.php', true, 303);
        exit;
    }

    $wifi_name = trim($_POST['wifi_name'] ?? '');
    $wifi_password = trim($_POST['wifi_password'] ?? '');
    $branch_name = 'Merkez';

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('
            INSERT INTO branches (id, name, wifi_name, wifi_password)
            VALUES (1, :name, :wifi_name, :wifi_password)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                wifi_name = VALUES(wifi_name),
                wifi_password = VALUES(wifi_password)
        ');
        $stmt->execute([
            ':name' => $branch_name,
            ':wifi_name' => $wifi_name,
            ':wifi_password' => $wifi_password,
        ]);
        bump_menu_version($pdo);
        $pdo->commit();
        flash_message('success', 'WiFi bilgileri güncellendi.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_message('error', chef_admin_error('branch_save_failed', $e));
    }
    header('Location: branches.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM branches WHERE id = 1 LIMIT 1');
$stmt->execute();
$branch = $stmt->fetch();
if (!$branch) {
    $branch = ['id' => 1, 'wifi_name' => '', 'wifi_password' => ''];
}

include 'header.php';
?>
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="font-serif text-lg text-chef-accent tracking-[0.15em]">WiFi Ayarları</h2>
        <p class="text-xs text-chef-muted">Müşterilerin kolayca kopyalayacağı tek WiFi bilgisi</p>
    </div>

    <?php if (!$branch): ?>
        <div class="p-6 rounded-xl border border-chef-accent/10 bg-white/5 text-chef-muted text-sm">WiFi bilgisi bulunamadı.</div>
    <?php else: ?>
        <form method="POST" class="admin-panel max-w-xl space-y-3 rounded-xl p-5" data-mutation-form data-warn-unsaved data-testid="venue-form">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
            <input type="hidden" name="id" value="<?php echo $branch['id']; ?>">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-chef-text">WiFi Bilgileri</h3>
                <span class="text-[10px] text-chef-muted">Güncelle</span>
            </div>
            <div>
                <label for="venue-wifi-name" class="block text-xs text-chef-muted mb-1">WiFi adı</label>
                <input id="venue-wifi-name" name="wifi_name" value="<?php echo sanitize($branch['wifi_name']); ?>" class="w-full rounded-lg px-3 py-2 text-sm" data-testid="venue-wifi-name">
            </div>
            <div>
                <label for="venue-wifi-password" class="block text-xs text-chef-muted mb-1">WiFi şifresi</label>
                <input id="venue-wifi-password" name="wifi_password" value="<?php echo sanitize($branch['wifi_password']); ?>" class="w-full rounded-lg px-3 py-2 text-sm" data-testid="venue-wifi-password">
            </div>
            <button class="admin-button admin-button-primary w-full" data-testid="venue-save">Kaydet</button>
        </form>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>

