<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login();
chef_require_method(['GET', 'POST']);

$safeCategoryFields = [
    'action', 'id', 'name', 'name_en', 'name_ar', 'description', 'description_en', 'description_ar',
    'delete_dependents',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = is_scalar($_POST['action'] ?? null) ? (string)$_POST['action'] : '';
    $categoryId = in_array($action, ['update', 'delete'], true) ? chef_canonical_id($_POST['id'] ?? '') : null;
    $formKey = $action === 'update' ? 'category-edit-' . ($categoryId ?? 'invalid') : 'category-create';
    $redirect = 'categories.php' . ($categoryId && $action === 'update' ? '?edit=' . $categoryId : '');

    if (!verify_csrf((string)($_POST['csrf_token'] ?? ''))) {
        chef_store_form_state($formKey, $_POST, $safeCategoryFields);
        flash_message('error', 'İstek doğrulanamadı. Formu yeniden gönderin.');
        header('Location: ' . $redirect, true, 303);
        exit;
    }
    if (!chef_consume_submission_token((string)($_POST['submission_token'] ?? ''))) {
        chef_store_form_state($formKey, $_POST, $safeCategoryFields);
        flash_message('error', chef_submission_token_error());
        header('Location: ' . $redirect, true, 303);
        exit;
    }

    $mediaStage = null;
    try {
        if (in_array($action, ['create', 'update'], true)) {
            $errors = [];
            $name = chef_validate_required_text($_POST['name'] ?? '', 150, 'Kategori adı', $errors, 'name');
            $nameEn = chef_validate_optional_text($_POST['name_en'] ?? '', 200, 'İngilizce kategori adı', $errors, 'name_en');
            $nameAr = chef_validate_optional_text($_POST['name_ar'] ?? '', 200, 'Arapça kategori adı', $errors, 'name_ar');
            $description = chef_validate_optional_text($_POST['description'] ?? '', 5000, 'Kategori açıklaması', $errors, 'description');
            $descriptionEn = chef_validate_optional_text($_POST['description_en'] ?? '', 5000, 'İngilizce açıklama', $errors, 'description_en');
            $descriptionAr = chef_validate_optional_text($_POST['description_ar'] ?? '', 5000, 'Arapça açıklama', $errors, 'description_ar');
            if ($action === 'update' && $categoryId === null) $errors['id'] = 'Kategori kimliği geçersiz.';
            if ($errors) {
                chef_store_form_state($formKey, $_POST, $safeCategoryFields, $errors);
                flash_message('error', (string)reset($errors));
                header('Location: ' . $redirect, true, 303);
                exit;
            }

            chef_media_assert_request_size();
            $mediaStage = chef_media_stage_upload($pdo, 'image', 'category');
            $pdo->beginTransaction();
            if ($action === 'create') {
                $nextOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM categories FOR UPDATE')->fetchColumn();
                $stmt = $pdo->prepare('INSERT INTO categories (name, name_en, name_ar, description, description_en, description_ar, image_path, sort_order) VALUES (:name, :name_en, :name_ar, :description, :description_en, :description_ar, NULL, :sort_order)');
                $stmt->execute([
                    ':name' => $name, ':name_en' => $nameEn, ':name_ar' => $nameAr,
                    ':description' => $description, ':description_en' => $descriptionEn, ':description_ar' => $descriptionAr,
                    ':sort_order' => max(1, $nextOrder),
                ]);
                $categoryId = (int)$pdo->lastInsertId();
                if ($mediaStage !== null) {
                    $imagePath = chef_media_activate($pdo, $mediaStage, 'category', $categoryId);
                    $pdo->prepare('UPDATE categories SET image_path = :image_path WHERE id = :id')->execute([':image_path' => $imagePath, ':id' => $categoryId]);
                }
                bump_menu_version($pdo);
                $pdo->commit();
                flash_message('success', 'Kategori eklendi.');
            } else {
                $existing = chef_lock_category($pdo, $categoryId);
                $imagePath = $existing['image_path'];
                if ($mediaStage !== null) $imagePath = chef_media_activate($pdo, $mediaStage, 'category', $categoryId);
                $stmt = $pdo->prepare('UPDATE categories SET name = :name, name_en = :name_en, name_ar = :name_ar, description = :description, description_en = :description_en, description_ar = :description_ar, image_path = :image_path WHERE id = :id');
                $stmt->execute([
                    ':name' => $name, ':name_en' => $nameEn, ':name_ar' => $nameAr,
                    ':description' => $description, ':description_en' => $descriptionEn, ':description_ar' => $descriptionAr,
                    ':image_path' => $imagePath, ':id' => $categoryId,
                ]);
                bump_menu_version($pdo);
                $pdo->commit();
                flash_message('success', 'Kategori güncellendi.');
            }
        } elseif ($action === 'delete') {
            if ($categoryId === null) throw new DomainException('Kategori kimliği geçersiz.');
            $pdo->beginTransaction();
            chef_lock_category($pdo, $categoryId);
            $productsStmt = $pdo->prepare('SELECT id FROM products WHERE category_id = :category_id ORDER BY id FOR UPDATE');
            $productsStmt->execute([':category_id' => $categoryId]);
            $productIds = array_map('intval', $productsStmt->fetchAll(PDO::FETCH_COLUMN));
            $dependentCount = count($productIds);
            if ($dependentCount > 0 && (string)($_POST['delete_dependents'] ?? '') !== '1') {
                throw new DomainException(sprintf('Bu kategoride %d ürün var. Ürünleri de silmek için işlemi yeniden onaylayın.', $dependentCount));
            }
            foreach ($productIds as $productId) chef_media_mark_owner_deletion($pdo, 'product', $productId);
            chef_media_mark_owner_deletion($pdo, 'category', $categoryId);
            $pdo->prepare('DELETE FROM products WHERE category_id = :category_id')->execute([':category_id' => $categoryId]);
            $pdo->prepare('DELETE FROM categories WHERE id = :id')->execute([':id' => $categoryId]);
            chef_normalize_category_order($pdo);
            bump_menu_version($pdo);
            $pdo->commit();
            chef_media_cleanup($pdo);
            flash_message('success', $dependentCount > 0
                ? sprintf('Kategori, %d ürün ve ilişkili görseller silindi.', $dependentCount)
                : 'Kategori ve ilişkili görsel silindi.');
        } else {
            throw new DomainException('Geçersiz işlem.');
        }

        if ($mediaStage !== null) {
            chef_media_finalize_operation($pdo, $mediaStage['operation_id']);
            chef_media_cleanup($pdo);
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($mediaStage !== null) chef_media_abandon_operation($pdo, $mediaStage['operation_id']);
        if (in_array($action, ['create', 'update'], true)) chef_store_form_state($formKey, $_POST, $safeCategoryFields);
        flash_message('error', $e instanceof DomainException ? $e->getMessage() : chef_admin_error('category_mutation_failed', $e));
    }

    header('Location: ' . ($action === 'update' && $categoryId ? 'categories.php?edit=' . $categoryId : 'categories.php'), true, 303);
    exit;
}

$editId = chef_canonical_id($_GET['edit'] ?? '') ?? 0;
$editCategory = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = :id');
    $stmt->execute([':id' => $editId]);
    $editCategory = $stmt->fetch() ?: null;
}
$formKey = $editCategory ? 'category-edit-' . (int)$editCategory['id'] : 'category-create';
$formState = chef_take_form_state($formKey);
$categories = $pdo->query(
    'SELECT c.*, COUNT(p.id) AS product_count
     FROM categories c LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id ORDER BY c.sort_order ASC, c.id ASC'
)->fetchAll();

include __DIR__ . '/header.php';
$value = static fn(string $field, mixed $fallback = ''): string => chef_form_value($formState, $field, $fallback);
$error = static fn(string $field): ?string => chef_field_error($formState, $field);
?>
<div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]">
    <section class="space-y-4" aria-labelledby="categories-title">
        <div>
            <h2 id="categories-title" class="text-2xl">Kategoriler</h2>
            <p class="admin-muted text-sm">Menü bölümlerini ve kategori görsellerini yönetin.</p>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <?php foreach ($categories as $category): $publicId = sanitize((string)($category['public_id'] ?: $category['id'])); ?>
                <article class="admin-panel flex gap-4 rounded-xl p-4" data-testid="category-row-<?php echo $publicId; ?>">
                    <div class="admin-raised h-20 w-20 shrink-0 overflow-hidden rounded-lg">
                        <?php if (!empty($category['image_path'])): ?>
                            <img src="<?php echo sanitize((string)chef_media_image_url($category['image_path'])); ?>" alt="<?php echo sanitize($category['name']); ?>" class="h-full w-full object-cover">
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="truncate text-lg"><?php echo sanitize($category['name']); ?></h3>
                        <p class="admin-muted mt-1 line-clamp-2 text-sm"><?php echo sanitize((string)($category['description'] ?? '')); ?></p>
                        <p class="admin-muted mt-2 text-xs"><?php echo (int)$category['product_count']; ?> ürün</p>
                        <div class="mt-3 flex gap-2">
                            <a href="categories.php?edit=<?php echo (int)$category['id']; ?>" class="admin-button admin-button-secondary flex-1" data-testid="category-edit-<?php echo $publicId; ?>">Düzenle</a>
                            <form method="POST" class="flex-1" data-mutation-form data-confirm-name="<?php echo sanitize($category['name']); ?>" data-confirm-detail="<?php echo (int)$category['product_count']; ?> ürün ve ilişkili görseller kalıcı olarak silinir." data-dependent-count="<?php echo (int)$category['product_count']; ?>" data-testid="category-delete-form-<?php echo $publicId; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo sanitize(chef_csrf_token()); ?>">
                                <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int)$category['id']; ?>">
                                <input type="hidden" name="delete_dependents" value="">
                                <button type="submit" class="admin-button admin-button-danger w-full" data-testid="category-delete-<?php echo $publicId; ?>">Sil</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if (!$categories): ?><p class="admin-panel rounded-xl p-5 text-sm">Henüz kategori yok.</p><?php endif; ?>
        </div>
    </section>

    <aside class="admin-panel h-fit rounded-xl p-5 lg:sticky lg:top-24" aria-labelledby="category-form-title">
        <h2 id="category-form-title" class="text-xl"><?php echo $editCategory ? 'Kategoriyi düzenle' : 'Yeni kategori'; ?></h2>
        <form method="POST" enctype="multipart/form-data" class="mt-5 space-y-4" data-mutation-form data-auto-translate <?php echo $editCategory ? 'data-warn-unsaved' : ''; ?> data-testid="category-form">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize(chef_csrf_token()); ?>">
            <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
            <input type="hidden" name="action" value="<?php echo $editCategory ? 'update' : 'create'; ?>">
            <?php if ($editCategory): ?><input type="hidden" name="id" value="<?php echo (int)$editCategory['id']; ?>"><?php endif; ?>
            <div>
                <label for="category-name" class="admin-muted mb-1 block text-sm">Kategori adı</label>
                <input id="category-name" name="name" value="<?php echo sanitize($value('name', $editCategory['name'] ?? '')); ?>" maxlength="150" required class="w-full rounded-lg px-3 py-2" data-testid="category-name" <?php echo $error('name') ? 'aria-invalid="true" aria-describedby="category-name-error"' : ''; ?>>
                <p id="category-name-error" role="alert" class="admin-field-error" <?php echo $error('name') ? '' : 'hidden'; ?>><?php echo $error('name') ? sanitize($error('name')) : ''; ?></p>
            </div>
            <div>
                <label for="category-description" class="admin-muted mb-1 block text-sm">Açıklama</label>
                <textarea id="category-description" name="description" rows="4" maxlength="5000" class="w-full rounded-lg px-3 py-2" data-testid="category-description"><?php echo sanitize($value('description', $editCategory['description'] ?? '')); ?></textarea>
                <?php if ($error('description')): ?><p role="alert" class="admin-field-error"><?php echo sanitize($error('description')); ?></p><?php endif; ?>
            </div>
            <fieldset class="space-y-3 border-t pt-4">
                <legend class="px-1 font-serif text-lg">Çeviriler</legend>
                <?php foreach ([
                    ['name_en', 'İngilizce kategori adı', 200, 1],
                    ['name_ar', 'Arapça kategori adı', 200, 1],
                    ['description_en', 'İngilizce açıklama', 5000, 3],
                    ['description_ar', 'Arapça açıklama', 5000, 3],
                ] as [$field, $label, $maximum, $rows]): ?>
                    <div>
                        <label for="category-<?php echo $field; ?>" class="admin-muted mb-1 block text-sm"><?php echo $label; ?></label>
                        <textarea id="category-<?php echo $field; ?>" name="<?php echo $field; ?>" rows="<?php echo $rows; ?>" maxlength="<?php echo $maximum; ?>" class="w-full rounded-lg px-3 py-2" data-testid="category-<?php echo str_replace('_', '-', $field); ?>"><?php echo sanitize($value($field, $editCategory[$field] ?? '')); ?></textarea>
                        <?php if ($error($field)): ?><p role="alert" class="admin-field-error"><?php echo sanitize($error($field)); ?></p><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <p class="admin-muted text-xs">Boş çeviriler misafir menüsünde Türkçe metni kullanır.</p>
            </fieldset>
            <div>
                <label for="category-image" class="admin-muted mb-1 block text-sm">Kategori görseli</label>
                <input id="category-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" class="w-full rounded-lg p-2 text-sm" data-testid="category-image">
                <?php if ($editCategory && $editCategory['image_path']): ?><p class="admin-muted mt-1 text-xs">Yeni dosya seçmezseniz mevcut görsel korunur.</p><?php endif; ?>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="admin-button admin-button-primary flex-1" data-testid="category-save"><?php echo $editCategory ? 'Değişiklikleri kaydet' : 'Kategoriyi ekle'; ?></button>
                <?php if ($editCategory): ?><a href="categories.php" class="admin-button admin-button-secondary">Vazgeç</a><?php endif; ?>
            </div>
        </form>
    </aside>
</div>
<?php include __DIR__ . '/footer.php'; ?>
