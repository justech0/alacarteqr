<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
require_login();
chef_require_method(['GET', 'POST']);

$categories = $pdo->query('SELECT id, public_id, name FROM categories ORDER BY sort_order ASC, id ASC')->fetchAll();
$categoryIds = array_map(static fn(array $category): int => (int)$category['id'], $categories);
$selectedCategory = chef_canonical_id($_POST['category_id'] ?? ($_GET['category'] ?? '')) ?? ($categoryIds[0] ?? 0);
if (!in_array($selectedCategory, $categoryIds, true)) $selectedCategory = $categoryIds[0] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect = 'product-order.php' . ($selectedCategory ? '?category=' . $selectedCategory : '');
    if (!verify_csrf((string)($_POST['csrf_token'] ?? ''))) {
        flash_message('error', 'İstek doğrulanamadı.');
        header('Location: ' . $redirect, true, 303);
        exit;
    }
    if (!chef_consume_submission_token((string)($_POST['submission_token'] ?? ''))) {
        flash_message('error', chef_submission_token_error());
        header('Location: ' . $redirect, true, 303);
        exit;
    }
    try {
        $categoryId = chef_canonical_id($_POST['category_id'] ?? '');
        if ($categoryId === null) throw new DomainException('Kategori kimliği geçersiz.');
        $order = is_array($_POST['order'] ?? null) ? $_POST['order'] : [];
        $orderedIds = [];
        foreach ($order as $rawId) {
            $id = chef_canonical_id($rawId);
            if ($id === null || isset($orderedIds[$id])) throw new DomainException('Ürün sırası geçersiz veya tekrar eden kimlik içeriyor.');
            $orderedIds[$id] = true;
        }
        $pdo->beginTransaction();
        chef_lock_category($pdo, $categoryId);
        $stmt = $pdo->prepare('SELECT id FROM products WHERE category_id = :category_id ORDER BY id FOR UPDATE');
        $stmt->execute([':category_id' => $categoryId]);
        $validIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $submittedIds = array_keys($orderedIds);
        $validSet = $validIds;
        sort($submittedIds, SORT_NUMERIC);
        sort($validSet, SORT_NUMERIC);
        if ($submittedIds !== $validSet) throw new DomainException('Ürün sırası eksik veya başka kategoriye ait kayıt içeriyor.');
        $update = $pdo->prepare('UPDATE products SET sort_order = :sort_order WHERE id = :id AND category_id = :category_id');
        foreach (array_keys($orderedIds) as $position => $id) $update->execute([':sort_order' => $position + 1, ':id' => $id, ':category_id' => $categoryId]);
        bump_menu_version($pdo);
        $pdo->commit();
        flash_message('success', 'Ürün sırası kaydedildi.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_message('error', $e instanceof DomainException ? $e->getMessage() : chef_admin_error('product_order_failed', $e));
    }
    header('Location: ' . $redirect, true, 303);
    exit;
}

$products = [];
if ($selectedCategory > 0) {
    $stmt = $pdo->prepare('SELECT id, public_id, name, price, sort_order FROM products WHERE category_id = :category_id ORDER BY sort_order ASC, id ASC');
    $stmt->execute([':category_id' => $selectedCategory]);
    $products = $stmt->fetchAll();
}
include __DIR__ . '/header.php';
?>
<section class="mx-auto max-w-3xl space-y-5" aria-labelledby="product-order-title">
    <div>
        <h2 id="product-order-title" class="text-2xl">Ürün sırası</h2>
        <p class="admin-muted text-sm">Bir kategori seçin ve ürünleri düğmelerle taşıyın.</p>
    </div>
    <form method="GET" class="admin-panel flex flex-col gap-3 rounded-xl p-4 sm:flex-row sm:items-end" data-testid="product-order-filter-form">
        <div class="flex-1">
            <label for="product-order-category" class="admin-muted mb-1 block text-sm">Kategori</label>
            <select id="product-order-category" name="category" class="w-full rounded-lg px-3 py-2" data-testid="product-order-category">
                <?php foreach ($categories as $category): ?><option value="<?php echo (int)$category['id']; ?>" <?php echo $selectedCategory === (int)$category['id'] ? 'selected' : ''; ?>><?php echo sanitize($category['name']); ?></option><?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="admin-button admin-button-secondary" data-testid="product-order-filter-save">Göster</button>
    </form>
    <form method="POST" class="admin-panel space-y-4 rounded-xl p-4" data-mutation-form data-warn-unsaved data-testid="product-order-form">
        <input type="hidden" name="csrf_token" value="<?php echo sanitize(chef_csrf_token()); ?>">
        <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
        <input type="hidden" name="category_id" value="<?php echo $selectedCategory ?: ''; ?>">
        <ol class="space-y-2" data-reorder-list>
            <?php foreach ($products as $product): $testId = sanitize((string)($product['public_id'] ?: $product['id'])); ?>
                <li class="admin-raised flex items-center gap-3 rounded-lg p-3" data-reorder-item data-id="<?php echo (int)$product['id']; ?>" data-item-name="<?php echo sanitize($product['name']); ?>" data-testid="product-order-row-<?php echo $testId; ?>">
                    <div class="min-w-0 flex-1"><p class="truncate font-medium"><?php echo sanitize($product['name']); ?></p><p class="admin-muted text-xs"><?php echo number_format((float)$product['price'], 2, ',', '.'); ?> ₺</p></div>
                    <span class="admin-muted text-xs" data-order-badge></span>
                    <input type="hidden" name="order[]" value="<?php echo (int)$product['id']; ?>">
                    <button type="button" class="admin-button admin-button-secondary" data-reorder-direction="up" data-testid="reorder-up-<?php echo (int)$product['id']; ?>" aria-label="<?php echo sanitize($product['name']); ?> ürününü yukarı taşı">Yukarı</button>
                    <button type="button" class="admin-button admin-button-secondary" data-reorder-direction="down" data-testid="reorder-down-<?php echo (int)$product['id']; ?>" aria-label="<?php echo sanitize($product['name']); ?> ürününü aşağı taşı">Aşağı</button>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php if (!$products): ?><p class="admin-muted text-sm">Bu kategoride sıralanacak ürün yok.</p><?php endif; ?>
        <div class="flex justify-end"><button type="submit" class="admin-button admin-button-primary" data-testid="product-order-save" <?php echo !$products ? 'disabled' : ''; ?>>Sırayı kaydet</button></div>
    </form>
</section>
<?php include __DIR__ . '/footer.php'; ?>
