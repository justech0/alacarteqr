<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login();
chef_require_method(['GET', 'POST']);

$safeProductFields = [
    'action', 'id', 'name', 'name_en', 'name_ar', 'description', 'description_en', 'description_ar',
    'price', 'category_id', 'redirect_category',
    'ingredients', 'ingredients_en', 'ingredients_ar', 'calories',
    'ingredients_verified', 'calories_verified', 'new_category_id', 'auto_verify',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = is_scalar($_POST['action'] ?? null) ? (string)$_POST['action'] : '';
    $redirectCategory = chef_canonical_id($_POST['redirect_category'] ?? '') ?? 0;
    $redirect = 'products.php' . ($redirectCategory > 0 ? '?category=' . $redirectCategory : '');
    $formKey = 'product-create';
    $editId = null;
    if ($action === 'update') {
        $editId = chef_canonical_id($_POST['id'] ?? '');
        $formKey = 'product-edit-' . ($editId ?? 'invalid');
    }

    if (!verify_csrf((string)($_POST['csrf_token'] ?? ''))) {
        chef_store_form_state($formKey, $_POST, $safeProductFields);
        flash_message('error', 'İstek doğrulanamadı. Formu yeniden gönderin.');
        header('Location: ' . $redirect, true, 303);
        exit;
    }
    if (!chef_consume_submission_token((string)($_POST['submission_token'] ?? ''))) {
        chef_store_form_state($formKey, $_POST, $safeProductFields);
        flash_message('error', chef_submission_token_error());
        header('Location: ' . $redirect . ($editId ? (($redirectCategory ? '&' : '?') . 'edit=' . $editId) : ''), true, 303);
        exit;
    }

    $mediaStage = null;
    $failed = false;
    try {
        if (in_array($action, ['create', 'update'], true)) {
            $errors = [];
            $name = chef_validate_required_text($_POST['name'] ?? '', 200, 'Ürün adı', $errors, 'name');
            $nameEn = chef_validate_optional_text($_POST['name_en'] ?? '', 200, 'İngilizce ürün adı', $errors, 'name_en');
            $nameAr = chef_validate_optional_text($_POST['name_ar'] ?? '', 200, 'Arapça ürün adı', $errors, 'name_ar');
            $description = chef_validate_optional_text($_POST['description'] ?? '', 5000, 'Servis açıklaması', $errors, 'description');
            $descriptionEn = chef_validate_optional_text($_POST['description_en'] ?? '', 5000, 'İngilizce açıklama', $errors, 'description_en');
            $descriptionAr = chef_validate_optional_text($_POST['description_ar'] ?? '', 5000, 'Arapça açıklama', $errors, 'description_ar');
            $ingredients = chef_validate_optional_text($_POST['ingredients'] ?? '', 5000, 'Türkçe içerik', $errors, 'ingredients');
            $ingredientsEn = chef_validate_optional_text($_POST['ingredients_en'] ?? '', 5000, 'İngilizce içerik', $errors, 'ingredients_en');
            $ingredientsAr = chef_validate_optional_text($_POST['ingredients_ar'] ?? '', 5000, 'Arapça içerik', $errors, 'ingredients_ar');
            $price = chef_validate_price($_POST['price'] ?? '', $errors);
            $calories = chef_validate_calories($_POST['calories'] ?? '', $errors);
            $categoryId = chef_canonical_id($_POST['category_id'] ?? '');
            if ($categoryId === null) {
                $errors['category_id'] = 'Geçerli bir kategori seçin.';
            }
            if ($action === 'update' && $editId === null) {
                $errors['id'] = 'Ürün kimliği geçersiz.';
            }
            $verifyIngredients = isset($_POST['ingredients_verified']);
            $verifyCalories = isset($_POST['calories_verified']);
            // The admin form sends auto_verify. Values the owner enters there count as verified.
            if (isset($_POST['auto_verify'])) {
                $verifyIngredients = $ingredients !== null || $ingredientsEn !== null || $ingredientsAr !== null;
                $verifyCalories = $calories !== null;
            }
            if ($verifyIngredients && $ingredients === null && $ingredientsEn === null && $ingredientsAr === null) {
                $errors['ingredients_verified'] = 'İçerik doğrulaması için en az bir içerik alanını doldurun.';
            }
            if ($verifyCalories && $calories === null) {
                $errors['calories_verified'] = 'Kalori doğrulaması için kalori değerini girin.';
            }
            if ($errors) {
                chef_store_form_state($formKey, $_POST, $safeProductFields, $errors);
                flash_message('error', (string)reset($errors));
                $editSuffix = $editId ? (($redirectCategory ? '&' : '?') . 'edit=' . $editId) : '';
                header('Location: ' . $redirect . $editSuffix, true, 303);
                exit;
            }

            chef_media_assert_request_size();
            $mediaStage = chef_media_stage_upload($pdo, 'image', 'product');
            $pdo->beginTransaction();

            if ($action === 'create') {
                chef_lock_category($pdo, $categoryId);
                $sortOrder = next_product_sort_order($pdo, $categoryId);
                $now = gmdate('Y-m-d H:i:s');
                $stmt = $pdo->prepare(
                    'INSERT INTO products (
                        category_id, name, name_en, name_ar, description, description_en, description_ar,
                        price, sort_order, image_path,
                        ingredients, ingredients_en, ingredients_ar, calories,
                        ingredients_verified_at, calories_verified_at
                    ) VALUES (
                        :category_id, :name, :name_en, :name_ar, :description, :description_en, :description_ar,
                        :price, :sort_order, NULL,
                        :ingredients, :ingredients_en, :ingredients_ar, :calories,
                        :ingredients_verified_at, :calories_verified_at
                    )'
                );
                $stmt->execute([
                    ':category_id' => $categoryId,
                    ':name' => $name,
                    ':name_en' => $nameEn,
                    ':name_ar' => $nameAr,
                    ':description' => $description,
                    ':description_en' => $descriptionEn,
                    ':description_ar' => $descriptionAr,
                    ':price' => $price,
                    ':sort_order' => $sortOrder,
                    ':ingredients' => $ingredients,
                    ':ingredients_en' => $ingredientsEn,
                    ':ingredients_ar' => $ingredientsAr,
                    ':calories' => $calories,
                    ':ingredients_verified_at' => $verifyIngredients ? $now : null,
                    ':calories_verified_at' => $verifyCalories ? $now : null,
                ]);
                $productId = (int)$pdo->lastInsertId();
                if ($mediaStage !== null) {
                    $imagePath = chef_media_activate($pdo, $mediaStage, 'product', $productId);
                    $pdo->prepare('UPDATE products SET image_path = :image_path WHERE id = :id')->execute([':image_path' => $imagePath, ':id' => $productId]);
                }
                bump_menu_version($pdo);
                $pdo->commit();
                flash_message('success', 'Ürün eklendi.');
                $redirectCategory = $categoryId;
            } else {
                $currentCategoryStmt = $pdo->prepare('SELECT category_id FROM products WHERE id = :id');
                $currentCategoryStmt->execute([':id' => $editId]);
                $currentCategoryId = chef_canonical_id((string)$currentCategoryStmt->fetchColumn());
                if ($currentCategoryId === null) {
                    throw new DomainException('Ürün bulunamadı.');
                }
                $lockedCategories = array_values(array_unique([$currentCategoryId, $categoryId]));
                sort($lockedCategories, SORT_NUMERIC);
                foreach ($lockedCategories as $lockedCategoryId) {
                    chef_lock_category($pdo, $lockedCategoryId);
                }
                $existingStmt = $pdo->prepare('SELECT * FROM products WHERE id = :id FOR UPDATE');
                $existingStmt->execute([':id' => $editId]);
                $existing = $existingStmt->fetch();
                if (!$existing || (int)$existing['category_id'] !== $currentCategoryId) {
                    throw new DomainException('Ürün değişti. Sayfayı yenileyip yeniden deneyin.');
                }

                $ingredientChanged = ($existing['ingredients'] ?? null) !== $ingredients
                    || ($existing['ingredients_en'] ?? null) !== $ingredientsEn
                    || ($existing['ingredients_ar'] ?? null) !== $ingredientsAr;
                $calorieChanged = ($existing['calories'] === null ? null : (int)$existing['calories']) !== $calories;
                $now = gmdate('Y-m-d H:i:s');
                $ingredientsVerifiedAt = $verifyIngredients
                    ? (!$ingredientChanged && !empty($existing['ingredients_verified_at']) ? $existing['ingredients_verified_at'] : $now)
                    : null;
                $caloriesVerifiedAt = $verifyCalories
                    ? (!$calorieChanged && !empty($existing['calories_verified_at']) ? $existing['calories_verified_at'] : $now)
                    : null;
                $categoryChanged = $currentCategoryId !== $categoryId;
                $sortOrder = $categoryChanged ? next_product_sort_order($pdo, $categoryId) : (int)$existing['sort_order'];
                $imagePath = $existing['image_path'];
                if ($mediaStage !== null) {
                    $imagePath = chef_media_activate($pdo, $mediaStage, 'product', $editId);
                }
                $stmt = $pdo->prepare(
                    'UPDATE products SET
                        category_id = :category_id, name = :name, name_en = :name_en, name_ar = :name_ar,
                        description = :description, description_en = :description_en, description_ar = :description_ar,
                        price = :price, sort_order = :sort_order, image_path = :image_path,
                        ingredients = :ingredients, ingredients_en = :ingredients_en,
                        ingredients_ar = :ingredients_ar, calories = :calories,
                        ingredients_verified_at = :ingredients_verified_at,
                        calories_verified_at = :calories_verified_at
                     WHERE id = :id'
                );
                $stmt->execute([
                    ':category_id' => $categoryId,
                    ':name' => $name,
                    ':name_en' => $nameEn,
                    ':name_ar' => $nameAr,
                    ':description' => $description,
                    ':description_en' => $descriptionEn,
                    ':description_ar' => $descriptionAr,
                    ':price' => $price,
                    ':sort_order' => $sortOrder,
                    ':image_path' => $imagePath,
                    ':ingredients' => $ingredients,
                    ':ingredients_en' => $ingredientsEn,
                    ':ingredients_ar' => $ingredientsAr,
                    ':calories' => $calories,
                    ':ingredients_verified_at' => $ingredientsVerifiedAt,
                    ':calories_verified_at' => $caloriesVerifiedAt,
                    ':id' => $editId,
                ]);
                if ($categoryChanged) {
                    chef_normalize_product_order($pdo, $currentCategoryId);
                    chef_normalize_product_order($pdo, $categoryId);
                }
                bump_menu_version($pdo);
                $pdo->commit();
                flash_message('success', 'Ürün güncellendi.');
                $redirectCategory = $categoryId;
            }
        } elseif ($action === 'move_category') {
            $productId = chef_canonical_id($_POST['id'] ?? '');
            $newCategoryId = chef_canonical_id($_POST['new_category_id'] ?? '');
            if ($productId === null || $newCategoryId === null) {
                throw new DomainException('Ürün veya kategori kimliği geçersiz.');
            }
            $stmt = $pdo->prepare('SELECT category_id FROM products WHERE id = :id');
            $stmt->execute([':id' => $productId]);
            $oldCategoryId = chef_canonical_id((string)$stmt->fetchColumn());
            if ($oldCategoryId === null) {
                throw new DomainException('Ürün bulunamadı.');
            }
            $pdo->beginTransaction();
            $lockedCategories = array_values(array_unique([$oldCategoryId, $newCategoryId]));
            sort($lockedCategories, SORT_NUMERIC);
            foreach ($lockedCategories as $categoryId) chef_lock_category($pdo, $categoryId);
            $productStmt = $pdo->prepare('SELECT id, category_id FROM products WHERE id = :id FOR UPDATE');
            $productStmt->execute([':id' => $productId]);
            $product = $productStmt->fetch();
            if (!$product || (int)$product['category_id'] !== $oldCategoryId) {
                throw new DomainException('Ürün değişti. Sayfayı yenileyip yeniden deneyin.');
            }
            if ($oldCategoryId !== $newCategoryId) {
                $nextOrder = next_product_sort_order($pdo, $newCategoryId);
                $pdo->prepare('UPDATE products SET category_id = :category_id, sort_order = :sort_order WHERE id = :id')
                    ->execute([':category_id' => $newCategoryId, ':sort_order' => $nextOrder, ':id' => $productId]);
                chef_normalize_product_order($pdo, $oldCategoryId);
                chef_normalize_product_order($pdo, $newCategoryId);
                bump_menu_version($pdo);
            }
            $pdo->commit();
            flash_message('success', 'Ürün kategorisi güncellendi.');
        } elseif ($action === 'remove_image') {
            $productId = chef_canonical_id($_POST['id'] ?? '');
            if ($productId === null) {
                throw new DomainException('Ürün kimliği geçersiz.');
            }
            $pdo->beginTransaction();
            $productStmt = $pdo->prepare('SELECT id, image_path FROM products WHERE id = :id FOR UPDATE');
            $productStmt->execute([':id' => $productId]);
            $row = $productStmt->fetch();
            if (!$row) {
                throw new DomainException('Ürün bulunamadı.');
            }
            if (!empty($row['image_path'])) {
                chef_media_mark_owner_deletion($pdo, 'product', $productId);
                $pdo->prepare('UPDATE products SET image_path = NULL WHERE id = :id')->execute([':id' => $productId]);
                bump_menu_version($pdo);
            }
            $pdo->commit();
            chef_media_cleanup($pdo);
            flash_message('success', 'Ürün görseli kaldırıldı.');
        } elseif ($action === 'delete') {
            $productId = chef_canonical_id($_POST['id'] ?? '');
            if ($productId === null) {
                throw new DomainException('Ürün kimliği geçersiz.');
            }
            $stmt = $pdo->prepare('SELECT category_id FROM products WHERE id = :id');
            $stmt->execute([':id' => $productId]);
            $categoryId = chef_canonical_id((string)$stmt->fetchColumn());
            if ($categoryId === null) {
                throw new DomainException('Ürün bulunamadı.');
            }
            $pdo->beginTransaction();
            chef_lock_category($pdo, $categoryId);
            $productStmt = $pdo->prepare('SELECT id FROM products WHERE id = :id AND category_id = :category_id FOR UPDATE');
            $productStmt->execute([':id' => $productId, ':category_id' => $categoryId]);
            if (!$productStmt->fetchColumn()) {
                throw new DomainException('Ürün değişti. Sayfayı yenileyip yeniden deneyin.');
            }
            chef_media_mark_owner_deletion($pdo, 'product', $productId);
            $pdo->prepare('DELETE FROM products WHERE id = :id')->execute([':id' => $productId]);
            chef_normalize_product_order($pdo, $categoryId);
            bump_menu_version($pdo);
            $pdo->commit();
            chef_media_cleanup($pdo);
            flash_message('success', 'Ürün ve ilişkili görseller silindi.');
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
        if (in_array($action, ['create', 'update'], true)) {
            chef_store_form_state($formKey, $_POST, $safeProductFields);
        }
        $failed = true;
        $message = $e instanceof DomainException ? $e->getMessage() : chef_admin_error('product_mutation_failed', $e);
        flash_message('error', $message);
    }

    $redirect = 'products.php' . ($redirectCategory > 0 ? '?category=' . $redirectCategory : '');
    // After a successful save the drawer closes. A failed save reopens it with the entered values.
    if ($action === 'update' && $editId && $failed) {
        $redirect .= ($redirectCategory > 0 ? '&' : '?') . 'edit=' . $editId;
    } elseif ($action === 'create' && $failed) {
        $redirect .= ($redirectCategory > 0 ? '&' : '?') . 'new=1';
    }
    header('Location: ' . $redirect, true, 303);
    exit;
}

$categories = $pdo->query('SELECT id, public_id, name FROM categories ORDER BY sort_order ASC, id ASC')->fetchAll();
$selectedCategory = chef_canonical_id($_GET['category'] ?? '') ?? 0;
$editId = chef_canonical_id($_GET['edit'] ?? '') ?? 0;
$editProduct = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->execute([':id' => $editId]);
    $editProduct = $stmt->fetch() ?: null;
}
$openDrawer = $editProduct !== null || isset($_GET['new']);
$formKey = $editProduct ? 'product-edit-' . (int)$editProduct['id'] : 'product-create';
$formState = chef_take_form_state($formKey);
// The whole catalog loads once. Search and category filters run in the browser.
$products = $pdo->query(
    'SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id
     ORDER BY c.sort_order ASC, p.sort_order ASC, p.id ASC'
)->fetchAll();
$categoryCounts = [];
foreach ($products as $product) {
    $categoryCounts[(int)$product['category_id']] = ($categoryCounts[(int)$product['category_id']] ?? 0) + 1;
}

include __DIR__ . '/header.php';
$value = static fn(string $field, mixed $fallback = ''): string => chef_form_value($formState, $field, $fallback);
$error = static fn(string $field): ?string => chef_field_error($formState, $field);
$testId = static fn(array $row): string => sanitize((string)($row['public_id'] ?: $row['id']));
$searchText = static fn(array $row): string => sanitize(mb_strtolower(trim($row['name'] . ' ' . ($row['description'] ?? '') . ' ' . $row['category_name']), 'UTF-8'));
$closeHref = 'products.php' . ($selectedCategory ? '?category=' . $selectedCategory : '');
?>
<section class="space-y-4" aria-labelledby="products-title" data-product-page>
    <h2 id="products-title" class="sr-only">Ürünler</h2>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <label class="relative min-w-[12rem] flex-1 sm:max-w-sm">
            <span class="sr-only">Ürün ara</span>
            <svg class="admin-muted pointer-events-none absolute left-3 top-1/2 -translate-y-1/2" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
            <input type="search" class="w-full py-2 pl-10 pr-3" placeholder="Ürün ara" autocomplete="off" data-product-search>
        </label>
        <a href="products.php?new=1" class="admin-button admin-button-primary" data-testid="product-new">+ Yeni ürün</a>
    </div>

    <div class="filter-chips" role="toolbar" aria-label="Kategori filtresi">
        <button type="button" class="filter-chip" data-filter-category="" aria-pressed="<?php echo $selectedCategory ? 'false' : 'true'; ?>">Tümü<span class="chip-count"><?php echo count($products); ?></span></button>
        <?php foreach ($categories as $category): ?>
            <button type="button" class="filter-chip" data-filter-category="<?php echo (int)$category['id']; ?>" aria-pressed="<?php echo $selectedCategory === (int)$category['id'] ? 'true' : 'false'; ?>"><?php echo sanitize($category['name']); ?><span class="chip-count"><?php echo (int)($categoryCounts[(int)$category['id']] ?? 0); ?></span></button>
        <?php endforeach; ?>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-3" data-product-grid>
        <?php foreach ($products as $product): $publicId = $testId($product); $thumb = chef_media_thumbnail_url($product['image_path'] ?? null); ?>
            <article class="admin-panel flex min-w-0 gap-3 rounded-xl p-3" data-category="<?php echo (int)$product['category_id']; ?>" data-search="<?php echo $searchText($product); ?>" data-testid="product-row-<?php echo $publicId; ?>">
                <div class="admin-raised h-20 w-20 shrink-0 overflow-hidden rounded-lg">
                    <?php if ($thumb): ?>
                        <img src="<?php echo sanitize($thumb); ?>" alt="" class="h-full w-full object-cover" loading="lazy" decoding="async">
                    <?php endif; ?>
                </div>
                <div class="flex min-w-0 flex-1 flex-col">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="truncate font-sans text-base font-semibold"><?php echo sanitize($product['name']); ?></h3>
                        <span class="shrink-0 font-semibold tabular-nums"><?php echo number_format((float)$product['price'], 2, ',', '.'); ?> ₺</span>
                    </div>
                    <p class="admin-muted truncate text-xs"><?php echo sanitize($product['category_name']); ?><?php echo !empty($product['description']) ? ' · ' . sanitize((string)$product['description']) : ''; ?></p>
                    <div class="mt-auto flex gap-2 pt-2">
                        <a href="products.php?<?php echo $selectedCategory ? 'category=' . $selectedCategory . '&' : ''; ?>edit=<?php echo (int)$product['id']; ?>" class="admin-button admin-button-secondary min-h-9 flex-1 py-1.5 text-sm" data-keep-scroll data-testid="product-edit-<?php echo $publicId; ?>">Düzenle</a>
                        <form method="POST" data-mutation-form data-keep-scroll data-confirm-name="<?php echo sanitize($product['name']); ?>" data-confirm-detail="Ürün kaydı ve ilişkili görseller silinir." data-testid="product-delete-form-<?php echo $publicId; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize(chef_csrf_token()); ?>">
                            <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int)$product['id']; ?>">
                            <input type="hidden" name="redirect_category" value="<?php echo $selectedCategory ?: ''; ?>">
                            <button type="submit" class="admin-button admin-button-danger min-h-9 py-1.5 text-sm" data-testid="product-delete-<?php echo $publicId; ?>">Sil</button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <p class="admin-panel rounded-xl p-5 text-sm" data-product-empty <?php echo $products ? 'hidden' : ''; ?>>Eşleşen ürün yok.</p>
</section>

<dialog class="admin-drawer" aria-labelledby="product-form-title" data-product-drawer data-close-href="<?php echo sanitize($closeHref); ?>" <?php echo $openDrawer ? 'data-open-on-load' : ''; ?>>
    <form method="POST" enctype="multipart/form-data" class="flex h-full flex-col" data-mutation-form data-keep-scroll data-warn-unsaved data-auto-translate data-testid="product-form">
        <div class="flex items-center justify-between gap-3 border-b px-5 py-4">
            <h2 id="product-form-title" class="text-xl"><?php echo $editProduct ? sanitize($editProduct['name']) : 'Yeni ürün'; ?></h2>
            <a href="<?php echo sanitize($closeHref); ?>" class="icon-button" aria-label="Kapat" data-keep-scroll data-drawer-close>
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </a>
        </div>
        <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize(chef_csrf_token()); ?>">
            <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
            <input type="hidden" name="action" value="<?php echo $editProduct ? 'update' : 'create'; ?>">
            <input type="hidden" name="redirect_category" value="<?php echo $selectedCategory ?: ''; ?>">
            <input type="hidden" name="auto_verify" value="1">
            <?php if ($editProduct): ?><input type="hidden" name="id" value="<?php echo (int)$editProduct['id']; ?>"><?php endif; ?>

            <?php $currentImage = $editProduct ? chef_media_image_url($editProduct['image_path'] ?? null) : null; ?>
            <div class="flex items-center gap-4">
                <div class="admin-raised h-24 w-24 shrink-0 overflow-hidden rounded-xl">
                    <img src="<?php echo $currentImage ? sanitize($currentImage) : ''; ?>" alt="" class="h-full w-full object-cover" data-image-preview <?php echo $currentImage ? '' : 'hidden'; ?>>
                </div>
                <div class="min-w-0 flex-1">
                    <label for="product-image" class="mb-1 block text-sm font-semibold">Ürün görseli</label>
                    <input id="product-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" class="w-full text-sm" data-image-input data-testid="product-image">
                    <p class="admin-muted mt-1 text-xs">Kare kırpılır. En iyi sonuç: 1200 × 1200 piksel, ürün ortada, düz arka plan.</p>
                    <?php if ($currentImage): ?>
                        <button type="submit" form="product-image-remove-form" class="admin-button admin-button-danger mt-2 min-h-9 py-1.5 text-sm" data-testid="product-image-remove">Görseli kaldır</button>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            $fields = [
                ['name', 'Ürün adı', 'text', 200, $editProduct['name'] ?? ''],
            ];
            foreach ($fields as [$field, $label, $type, $maxlength, $fallback]): $fieldError = $error($field); ?>
                <div>
                    <label for="product-<?php echo $field; ?>" class="mb-1 block text-sm font-semibold"><?php echo $label; ?></label>
                    <input id="product-<?php echo $field; ?>" type="<?php echo $type; ?>" name="<?php echo $field; ?>" value="<?php echo sanitize($value($field, $fallback)); ?>" required <?php echo $maxlength ? 'maxlength="' . $maxlength . '"' : 'inputmode="decimal"'; ?> class="w-full px-3 py-2" data-testid="product-<?php echo $field; ?>" <?php echo $fieldError ? 'aria-invalid="true" aria-describedby="product-' . $field . '-error"' : ''; ?>>
                    <p id="product-<?php echo $field; ?>-error" role="alert" class="admin-field-error" <?php echo $fieldError ? '' : 'hidden'; ?>><?php echo $fieldError ? sanitize($fieldError) : ''; ?></p>
                </div>
            <?php endforeach; ?>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="product-price" class="mb-1 block text-sm font-semibold">Fiyat (₺)</label>
                    <input id="product-price" type="text" name="price" inputmode="decimal" required value="<?php echo sanitize($value('price', $editProduct['price'] ?? '')); ?>" class="w-full px-3 py-2" data-testid="product-price" <?php echo $error('price') ? 'aria-invalid="true" aria-describedby="product-price-error"' : ''; ?>>
                    <p id="product-price-error" role="alert" class="admin-field-error" <?php echo $error('price') ? '' : 'hidden'; ?>><?php echo $error('price') ? sanitize($error('price')) : ''; ?></p>
                </div>
                <div>
                    <label for="product-calories" class="mb-1 block text-sm font-semibold">Kalori (kcal)</label>
                    <input id="product-calories" type="text" name="calories" inputmode="numeric" maxlength="5" placeholder="İsteğe bağlı" value="<?php echo sanitize($value('calories', $editProduct['calories'] ?? '')); ?>" class="w-full px-3 py-2" data-testid="product-calories" <?php echo $error('calories') ? 'aria-invalid="true"' : ''; ?>>
                    <?php if ($error('calories')): ?><p role="alert" class="admin-field-error"><?php echo sanitize($error('calories')); ?></p><?php endif; ?>
                </div>
            </div>
            <p class="admin-muted -mt-2 text-xs">Kalori sadece ürün penceresinde gösterilir, fiyatın yanında görünmez.</p>

            <div>
                <label for="product-category" class="mb-1 block text-sm font-semibold">Kategori</label>
                <select id="product-category" name="category_id" required class="w-full px-3 py-2" data-testid="product-category">
                    <option value="">Kategori seçin</option>
                    <?php $chosenCategory = chef_canonical_id($value('category_id', $editProduct['category_id'] ?? ($selectedCategory ?: ''))) ?? 0; ?>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo (int)$category['id']; ?>" <?php echo $chosenCategory === (int)$category['id'] ? 'selected' : ''; ?>><?php echo sanitize($category['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($error('category_id')): ?><p role="alert" class="admin-field-error"><?php echo sanitize($error('category_id')); ?></p><?php endif; ?>
            </div>

            <div>
                <label for="product-description" class="mb-1 block text-sm font-semibold">Açıklama</label>
                <textarea id="product-description" name="description" rows="2" maxlength="5000" class="w-full px-3 py-2" placeholder="Örn. 160 gr köfte + patates + salata" data-testid="product-description"><?php echo sanitize($value('description', $editProduct['description'] ?? '')); ?></textarea>
                <?php if ($error('description')): ?><p role="alert" class="admin-field-error"><?php echo sanitize($error('description')); ?></p><?php endif; ?>
            </div>

            <details class="admin-raised rounded-xl px-4 py-3" <?php echo $error('ingredients') ? 'open' : ''; ?>>
                <summary class="cursor-pointer text-sm font-semibold">İçerik <span class="admin-muted font-normal">(isteğe bağlı)</span></summary>
                <div class="mt-3 space-y-3">
                    <?php foreach ([['ingredients', 'Türkçe içerik'], ['ingredients_en', 'İngilizce içerik'], ['ingredients_ar', 'Arapça içerik']] as [$field, $label]): ?>
                        <div>
                            <label for="product-<?php echo $field; ?>" class="admin-muted mb-1 block text-sm"><?php echo $label; ?></label>
                            <textarea id="product-<?php echo $field; ?>" name="<?php echo $field; ?>" rows="2" maxlength="5000" class="w-full px-3 py-2" data-testid="product-<?php echo str_replace('_', '-', $field); ?>"><?php echo sanitize($value($field, $editProduct[$field] ?? '')); ?></textarea>
                            <?php if ($error($field)): ?><p role="alert" class="admin-field-error"><?php echo sanitize($error($field)); ?></p><?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <p class="admin-muted text-xs">Doldurulan içerik menüde ürün penceresinde gösterilir. Boş alan gösterilmez.</p>
                </div>
            </details>

            <details class="admin-raised rounded-xl px-4 py-3">
                <summary class="cursor-pointer text-sm font-semibold">Çeviriler <span class="admin-muted font-normal">(otomatik)</span></summary>
                <div class="mt-3 space-y-3">
                    <?php foreach ([
                        ['name_en', 'İngilizce ürün adı', 200, 1],
                        ['name_ar', 'Arapça ürün adı', 200, 1],
                        ['description_en', 'İngilizce açıklama', 5000, 2],
                        ['description_ar', 'Arapça açıklama', 5000, 2],
                    ] as [$field, $label, $maximum, $rows]): ?>
                        <div>
                            <label for="product-<?php echo $field; ?>" class="admin-muted mb-1 block text-sm"><?php echo $label; ?></label>
                            <textarea id="product-<?php echo $field; ?>" name="<?php echo $field; ?>" rows="<?php echo $rows; ?>" maxlength="<?php echo $maximum; ?>" class="w-full px-3 py-2" data-testid="product-<?php echo str_replace('_', '-', $field); ?>"><?php echo sanitize($value($field, $editProduct[$field] ?? '')); ?></textarea>
                            <?php if ($error($field)): ?><p role="alert" class="admin-field-error"><?php echo sanitize($error($field)); ?></p><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <button type="button" class="admin-button admin-button-secondary w-full" data-translate-now>Türkçeden otomatik çevir</button>
                    <p class="admin-muted text-xs" data-translate-status>Kaydederken boş çeviriler Türkçeden otomatik doldurulur. İsterseniz düzeltebilirsiniz.</p>
                </div>
            </details>
        </div>
        <div class="flex gap-3 border-t px-5 py-4">
            <button type="submit" class="admin-button admin-button-primary flex-1" data-testid="product-save"><?php echo $editProduct ? 'Kaydet' : 'Ürünü ekle'; ?></button>
            <a href="<?php echo sanitize($closeHref); ?>" class="admin-button admin-button-secondary" data-keep-scroll data-drawer-close>Vazgeç</a>
        </div>
    </form>
    <?php if ($editProduct && !empty($editProduct['image_path'])): ?>
        <form id="product-image-remove-form" method="POST" class="hidden" data-mutation-form data-keep-scroll data-confirm-name="<?php echo sanitize($editProduct['name']); ?>" data-confirm-detail="Ürün görseli silinir. Ürün menüde görselsiz görünür.">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize(chef_csrf_token()); ?>">
            <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
            <input type="hidden" name="action" value="remove_image">
            <input type="hidden" name="id" value="<?php echo (int)$editProduct['id']; ?>">
            <input type="hidden" name="redirect_category" value="<?php echo $selectedCategory ?: ''; ?>">
        </form>
    <?php endif; ?>
</dialog>
<?php include __DIR__ . '/footer.php'; ?>
