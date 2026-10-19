<?php
require_once __DIR__ . '/functions.php';
require_login();
chef_require_method(['GET', 'POST']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'invalidate') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        flash_message('error', 'Geçersiz istek.');
        header('Location: index.php');
        exit;
    }
    if (!chef_consume_submission_token((string)($_POST['submission_token'] ?? ''))) {
        flash_message('error', chef_submission_token_error());
        header('Location: index.php', true, 303);
        exit;
    }
    try {
        $pdo->beginTransaction();
        bump_menu_version($pdo);
        $pdo->commit();
        flash_message('success', 'Menü önbelleği güncellendi.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_message('error', chef_admin_error('menu_invalidate_failed', $e));
    }
    header('Location: index.php');
    exit;
}

$counts = fetch_counts($pdo);

$stmt = $pdo->query('SELECT customer_name, topic, rating, comment, created_at FROM feedbacks ORDER BY created_at DESC LIMIT 5');
$latestFeedbacks = $stmt->fetchAll();

include 'header.php';
?>
<div class="mb-6 grid grid-cols-3 gap-3 sm:gap-6">
    <div class="admin-panel rounded-2xl p-4 sm:p-6">
        <p class="admin-muted mb-1 text-xs font-semibold">Ürün</p>
        <div class="font-serif text-2xl text-chef-accent sm:text-3xl"><?php echo $counts['products']; ?></div>
    </div>
    <div class="admin-panel rounded-2xl p-4 sm:p-6">
        <p class="admin-muted mb-1 text-xs font-semibold">Kategori</p>
        <div class="font-serif text-2xl text-chef-accent sm:text-3xl"><?php echo $counts['categories']; ?></div>
    </div>
    <div class="admin-panel rounded-2xl p-4 sm:p-6">
        <p class="admin-muted mb-1 text-xs font-semibold">Yorum</p>
        <div class="font-serif text-2xl text-chef-accent sm:text-3xl"><?php echo $counts['feedbacks']; ?></div>
    </div>
</div>

<div class="admin-panel rounded-2xl p-4 sm:p-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg">Son yorumlar</h2>
        <a href="feedbacks.php" class="text-xs text-chef-muted underline">Tümü</a>
    </div>
    <?php if (empty($latestFeedbacks)): ?>
        <p class="text-chef-muted text-sm">Henüz yorum yok.</p>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($latestFeedbacks as $feedback): ?>
                <div class="p-4 rounded-xl border border-chef-accent/10 bg-white/5">
                    <div class="flex items-center justify-between mb-1">
                        <div class="text-sm font-semibold text-chef-text"><?php echo sanitize(trim((string)($feedback['customer_name'] ?? '')) ?: 'Müşteri yorumu'); ?></div>
                        <div class="text-xs text-chef-accent font-bold">★ <?php echo (int)$feedback['rating']; ?>/5</div>
                    </div>
                    <p class="text-sm text-chef-muted">"<?php echo sanitize((string)($feedback['comment'] ?? '')); ?>"</p>
                    <p class="text-[10px] text-chef-muted mt-2"><?php echo sanitize(date('d.m.Y H:i', strtotime((string)$feedback['created_at']))); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
