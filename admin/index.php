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

$stmt = $pdo->query('SELECT customer_name, rating, comment, created_at FROM feedbacks ORDER BY created_at DESC LIMIT 5');
$latestFeedbacks = $stmt->fetchAll();

include 'header.php';
?>
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="p-6 rounded-2xl border border-chef-accent/30 bg-black/40">
        <p class="text-xs uppercase tracking-widest text-chef-muted mb-2">Toplam Ürün</p>
        <div class="text-3xl font-serif text-chef-accent"><?php echo $counts['products']; ?></div>
    </div>
    <div class="p-6 rounded-2xl border border-chef-accent/30 bg-black/40">
        <p class="text-xs uppercase tracking-widest text-chef-muted mb-2">Toplam Kategori</p>
        <div class="text-3xl font-serif text-chef-accent"><?php echo $counts['categories']; ?></div>
    </div>
    <div class="p-6 rounded-2xl border border-chef-accent/30 bg-black/40">
        <p class="text-xs uppercase tracking-widest text-chef-muted mb-2">Geri Bildirimler</p>
        <div class="text-3xl font-serif text-chef-accent"><?php echo $counts['feedbacks']; ?></div>
    </div>
</div>

<div class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-4 rounded-xl border border-chef-accent/20 bg-white/5">
    <div>
        <p class="text-xs uppercase tracking-[0.25em] text-chef-muted">Menü önbelleği</p>
        <p class="text-sm text-chef-text">Menüyü yenile butonuna bastığınızda önbellek güncellenir.</p>
    </div>
    <form method="POST" class="flex items-center gap-2" data-mutation-form data-testid="menu-invalidate-form">
        <input type="hidden" name="csrf_token" value="<?php echo sanitize($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
        <input type="hidden" name="action" value="invalidate">
        <button class="admin-button admin-button-primary" data-testid="menu-invalidate-save">Menüyü yenile</button>
    </form>
</div>

<div class="bg-black/50 border border-chef-accent/20 rounded-2xl p-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="font-serif text-lg text-chef-accent tracking-[0.15em]">Son Yorumlar</h2>
        <a href="feedbacks.php" class="text-xs text-chef-muted underline">Tümü</a>
    </div>
    <?php if (empty($latestFeedbacks)): ?>
        <p class="text-chef-muted text-sm">Henüz yorum yok.</p>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($latestFeedbacks as $feedback): ?>
                <div class="p-4 rounded-xl border border-chef-accent/10 bg-white/5">
                    <div class="flex items-center justify-between mb-1">
                        <div class="text-sm font-semibold text-chef-text"><?php echo sanitize($feedback['customer_name']); ?></div>
                        <div class="text-xs text-chef-accent font-bold">★ <?php echo (int)$feedback['rating']; ?>/5</div>
                    </div>
                    <p class="text-sm text-chef-muted">"<?php echo sanitize($feedback['comment']); ?>"</p>
                    <p class="text-[10px] text-chef-muted mt-2"><?php echo date('d.m.Y H:i', strtotime($feedback['created_at'])); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
