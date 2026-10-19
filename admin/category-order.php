<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
require_login();
chef_require_method(['GET', 'POST']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf((string)($_POST['csrf_token'] ?? ''))) {
        flash_message('error', 'İstek doğrulanamadı.');
        header('Location: category-order.php', true, 303);
        exit;
    }
    if (!chef_consume_submission_token((string)($_POST['submission_token'] ?? ''))) {
        flash_message('error', chef_submission_token_error());
        header('Location: category-order.php', true, 303);
        exit;
    }
    try {
        $order = is_array($_POST['order'] ?? null) ? $_POST['order'] : [];
        $orderedIds = [];
        foreach ($order as $rawId) {
            $id = chef_canonical_id($rawId);
            if ($id === null || isset($orderedIds[$id])) throw new DomainException('Kategori sırası geçersiz veya tekrar eden kimlik içeriyor.');
            $orderedIds[$id] = true;
        }
        $pdo->beginTransaction();
        $validIds = array_map('intval', $pdo->query('SELECT id FROM categories ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN));
        $submittedIds = array_keys($orderedIds);
        $validSet = $validIds;
        sort($submittedIds, SORT_NUMERIC);
        sort($validSet, SORT_NUMERIC);
        if ($submittedIds !== $validSet) throw new DomainException('Kategori sırası eksik veya farklı kayıt içeriyor.');
        $update = $pdo->prepare('UPDATE categories SET sort_order = :sort_order WHERE id = :id');
        foreach (array_keys($orderedIds) as $position => $id) $update->execute([':sort_order' => $position + 1, ':id' => $id]);
        bump_menu_version($pdo);
        $pdo->commit();
        flash_message('success', 'Kategori sırası kaydedildi.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_message('error', $e instanceof DomainException ? $e->getMessage() : chef_admin_error('category_order_failed', $e));
    }
    header('Location: category-order.php', true, 303);
    exit;
}

$categories = $pdo->query('SELECT id, public_id, name, sort_order FROM categories ORDER BY sort_order ASC, id ASC')->fetchAll();
include __DIR__ . '/header.php';
?>
<section class="mx-auto max-w-3xl space-y-5" aria-labelledby="category-order-title">
    <div>
        <h2 id="category-order-title" class="text-2xl">Kategori sırası</h2>
        <p class="admin-muted text-sm">Düğmelerle kategorileri taşıyın, ardından sırayı kaydedin.</p>
    </div>
    <form method="POST" class="admin-panel space-y-4 rounded-xl p-4" data-mutation-form data-warn-unsaved data-testid="category-order-form">
        <input type="hidden" name="csrf_token" value="<?php echo sanitize(chef_csrf_token()); ?>">
        <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
        <ol class="space-y-2" data-reorder-list>
            <?php foreach ($categories as $category): $testId = sanitize((string)($category['public_id'] ?: $category['id'])); ?>
                <li class="admin-raised flex items-center gap-3 rounded-lg p-3" data-reorder-item data-id="<?php echo (int)$category['id']; ?>" data-item-name="<?php echo sanitize($category['name']); ?>" data-testid="category-order-row-<?php echo $testId; ?>">
                    <span class="min-w-0 flex-1 font-medium"><?php echo sanitize($category['name']); ?></span>
                    <span class="admin-muted text-xs" data-order-badge></span>
                    <input type="hidden" name="order[]" value="<?php echo (int)$category['id']; ?>">
                    <button type="button" class="admin-button admin-button-secondary" data-reorder-direction="up" data-testid="reorder-up-<?php echo (int)$category['id']; ?>" aria-label="<?php echo sanitize($category['name']); ?> kategorisini yukarı taşı">Yukarı</button>
                    <button type="button" class="admin-button admin-button-secondary" data-reorder-direction="down" data-testid="reorder-down-<?php echo (int)$category['id']; ?>" aria-label="<?php echo sanitize($category['name']); ?> kategorisini aşağı taşı">Aşağı</button>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php if (!$categories): ?><p class="admin-muted text-sm">Sıralanacak kategori yok.</p><?php endif; ?>
        <div class="flex justify-end"><button type="submit" class="admin-button admin-button-primary" data-testid="category-order-save" <?php echo !$categories ? 'disabled' : ''; ?>>Sırayı kaydet</button></div>
    </form>
</section>
<?php include __DIR__ . '/footer.php'; ?>
