<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login();
chef_require_method(['GET', 'POST']);

$safeProductFields = [
    'action', 'id', 'name', 'name_en', 'name_ar', 'description', 'description_en', 'description_ar',
    'price', 'category_id', 'redirect_category',
    'ingredients', 'ingredients_en', 'ingredients_ar', 'calories',
    'ingredients_verified', 'calories_verified', 'new_category_id',
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
        $message = $e instanceof DomainException ? $e->getMessage() : chef_admin_error('product_mutation_failed', $e);
        flash_message('error', $message);
    }

    $redirect = 'products.php' . ($redirectCategory > 0 ? '?category=' . $redirectCategory : '');
    if ($action === 'update' && $editId) {
        $redirect .= ($redirectCategory > 0 ? '&' : '?') . 'edit=' . $editId;
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
$formKey = $editProduct ? 'product-edit-' . (int)$editProduct['id'] : 'product-create';
$formState = chef_take_form_state($formKey);
$searchQuery = trim(is_scalar($_GET['search'] ?? null) ? (string)$_GET['search'] : '');
$conditions = [];
$params = [];
if ($selectedCategory > 0) {
    $conditions[] = 'p.category_id = :category_id';
    $params[':category_id'] = $selectedCategory;
}
if ($searchQuery !== '') {
    $conditions[] = '(p.name LIKE :search OR p.description LIKE :search)';
    $params[':search'] = '%' . $searchQuery . '%';
}
$sql = 'SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id';
if ($conditions) $sql .= ' WHERE ' . implode(' AND ', $conditions);
$sql .= ' ORDER BY c.sort_order ASC, p.sort_order ASC, p.id ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

include __DIR__ . '/header.php';
$value = static fn(string $field, mixed $fallback = ''): string => chef_form_value($formState, $field, $fallback);
$error = static fn(string $field): ?string => chef_field_error($formState, $field);
$testId = static fn(array $row): string => sanitize((string)($row['public_id'] ?: $row['id']));
?>
<div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(22rem,1fr)]">
    <section class="min-w-0 space-y-4" aria-labelledby="products-title">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <h2 id="products-title" class="text-2xl">Ürünler</h2>
                <p class="admin-muted text-sm">Menü ürünlerini, fiyatlarını ve doğrulanmış bilgilerini yönetin.</p>
            </div>
            <form method="GET" class="flex flex-col gap-2 sm:flex-row" data-testid="product-filter-form">
                <div>
                    <label for="product-search" class="admin-muted block text-xs">Ürün ara</label>
                    <input id="product-search" name="search" value="<?php echo sanitize($searchQuery); ?>" class="w-full rounded-lg px-3 py-2 sm:w-48" data-testid="product-search">
                </div>
                <div>
                    <label for="product-category-filter" class="admin-muted block text-xs">Kategori</label>
                    <select id="product-category-filter" name="category" class="w-full rounded-lg px-3 py-2" data-testid="product-category-filter">
                        <option value="">Tüm kategoriler</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo (int)$category['id']; ?>" <?php echo $selectedCategory === (int)$category['id'] ? 'selected' : ''; ?>><?php echo sanitize($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="admin-button admin-button-secondary self-end" type="submit" data-testid="product-filter-save">Filtrele</button>
            </form>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <?php foreach ($products as $product): $publicId = $testId($product); ?>
                <article class="admin-panel min-w-0 flex flex-col gap-3 rounded-xl p-4" data-testid="product-row-<?php echo $publicId; ?>">
                    <div class="flex gap-4">
                        <div class="admin-raised h-24 w-28 shrink-0 overflow-hidden rounded-lg">
                            <?php if (!empty($product['image_path'])): ?>
                                <img src="<?php echo sanitize((string)chef_media_image_url($product['image_path'])); ?>" alt="<?php echo sanitize($product['name']); ?>" class="h-full w-full object-cover">
                            <?php endif; ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate text-lg"><?php echo sanitize($product['name']); ?></h3>
                            <p class="admin-muted text-xs"><?php echo sanitize($product['category_name']); ?></p>
                            <p class="mt-2 font-semibold tabular-nums"><?php echo number_format((float)$product['price'], 2, ',', '.'); ?> ₺</p>
                        </div>
                    </div>
                    <p class="admin-muted line-clamp-3 text-sm"><?php echo sanitize((string)($product['description'] ?? '')); ?></p>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="admin-raised rounded-full px-2 py-1"><?php echo $product['ingredients_verified_at'] ? 'İçerik doğrulandı' : 'İçerik doğrulanmadı'; ?></span>
                        <span class="admin-raised rounded-full px-2 py-1"><?php echo $product['calories_verified_at'] ? ((int)$product['calories'] . ' kcal doğrulandı') : 'Kalori doğrulanmadı'; ?></span>
                    </div>
                    <form method="POST" class="flex gap-2" data-mutation-form data-testid="product-move-form-<?php echo $publicId; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize(chef_csrf_token()); ?>">
                        <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
                        <input type="hidden" name="action" value="move_category">
                        <input type="hidden" name="id" value="<?php echo (int)$product['id']; ?>">
                        <input type="hidden" name="redirect_category" value="<?php echo $selectedCategory ?: ''; ?>">
                        <label class="sr-only" for="move-<?php echo $publicId; ?>">Yeni kategori</label>
                        <select id="move-<?php echo $publicId; ?>" name="new_category_id" class="min-w-0 flex-1 rounded-lg px-3 py-2" data-testid="product-move-category-<?php echo $publicId; ?>">
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo (int)$category['id']; ?>" <?php echo (int)$product['category_id'] === (int)$category['id'] ? 'selected' : ''; ?>><?php echo sanitize($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="admin-button admin-button-secondary" type="submit" data-testid="product-move-<?php echo $publicId; ?>">Taşı</button>
                    </form>
                    <div class="flex gap-2">
                        <a href="products.php?<?php echo $selectedCategory ? 'category=' . $selectedCategory . '&' : ''; ?>edit=<?php echo (int)$product['id']; ?>" class="admin-button admin-button-secondary flex-1" data-testid="product-edit-<?php echo $publicId; ?>">Düzenle</a>
                        <form method="POST" class="flex-1" data-mutation-form data-confirm-name="<?php echo sanitize($product['name']); ?>" data-confirm-detail="Ürün kaydı ve ilişkili görseller silinir." data-testid="product-delete-form-<?php echo $publicId; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize(chef_csrf_token()); ?>">
                            <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int)$product['id']; ?>">
                            <input type="hidden" name="redirect_category" value="<?php echo $selectedCategory ?: ''; ?>">
                            <button type="submit" class="admin-button admin-button-danger w-full" data-testid="product-delete-<?php echo $publicId; ?>">Sil</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if (!$products): ?>
                <p class="admin-panel rounded-xl p-5 text-sm">Bu filtreyle eşleşen ürün yok.</p>
            <?php endif; ?>
        </div>
    </section>

    <aside class="admin-panel h-fit rounded-xl p-5 xl:sticky xl:top-24" aria-labelledby="product-form-title">
        <h2 id="product-form-title" class="text-xl"><?php echo $editProduct ? 'Ürünü düzenle' : 'Yeni ürün'; ?></h2>
        <p class="admin-muted mt-1 text-sm">Doğrulanmamış içerik ve kalori bilgileri menüde gösterilmez.</p>
        <form method="POST" enctype="multipart/form-data" class="mt-5 space-y-4" data-mutation-form <?php echo $editProduct ? 'data-warn-unsaved' : ''; ?> data-testid="product-form">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize(chef_csrf_token()); ?>">
            <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
            <input type="hidden" name="action" value="<?php echo $editProduct ? 'update' : 'create'; ?>">
            <input type="hidden" name="redirect_category" value="<?php echo $selectedCategory ?: ''; ?>">
            <?php if ($editProduct): ?><input type="hidden" name="id" value="<?php echo (int)$editProduct['id']; ?>"><?php endif; ?>

            <?php
            $fields = [
                ['name', 'Ürün adı', 'text', 200, $editProduct['name'] ?? ''],
                ['price', 'Fiyat', 'text', null, $editProduct['price'] ?? ''],
                ['calories', 'Kalori', 'text', 5, $editProduct['calories'] ?? ''],
            ];
            foreach ($fields as [$field, $label, $type, $maxlength, $fallback]): $fieldError = $error($field); ?>
                <div>
                    <label for="product-<?php echo $field; ?>" class="admin-muted mb-1 block text-sm"><?php echo $label; ?></label>
                    <input id="product-<?php echo $field; ?>" type="<?php echo $type; ?>" name="<?php echo $field; ?>" value="<?php echo sanitize($value($field, $fallback)); ?>" <?php echo $field !== 'calories' ? 'required' : ''; ?> <?php echo $maxlength ? 'maxlength="' . $maxlength . '"' : ''; ?> class="w-full rounded-lg px-3 py-2" data-testid="product-<?php echo $field; ?>" <?php echo $fieldError ? 'aria-invalid="true" aria-describedby="product-' . $field . '-error"' : ''; ?>>
                    <p id="product-<?php echo $field; ?>-error" role="alert" class="admin-field-error" <?php echo $fieldError ? '' : 'hidden'; ?>><?php echo $fieldError ? sanitize($fieldError) : ''; ?></p>
                </div>
            <?php endforeach; ?>

            <div>
                <label for="product-category" class="admin-muted mb-1 block text-sm">Kategori</label>
                <select id="product-category" name="category_id" required class="w-full rounded-lg px-3 py-2" data-testid="product-category">
                    <option value="">Kategori seçin</option>
                    <?php $chosenCategory = chef_canonical_id($value('category_id', $editProduct['category_id'] ?? ($selectedCategory ?: ''))) ?? 0; ?>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo (int)$category['id']; ?>" <?php echo $chosenCategory === (int)$category['id'] ? 'selected' : ''; ?>><?php echo sanitize($category['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($error('category_id')): ?><p role="alert" class="admin-field-error"><?php echo sanitize($error('category_id')); ?></p><?php endif; ?>
            </div>

            <div>
                <label for="product-description" class="admin-muted mb-1 block text-sm">Servis açıklaması</label>
                <textarea id="product-description" name="description" rows="3" maxlength="5000" class="w-full rounded-lg px-3 py-2" data-testid="product-description"><?php echo sanitize($value('description', $editProduct['description'] ?? '')); ?></textarea>
                <?php if ($error('description')): ?><p role="alert" class="admin-field-error"><?php echo sanitize($error('description')); ?></p><?php endif; ?>
            </div>

            <fieldset class="space-y-3 border-t pt-4">
                <legend class="px-1 font-serif text-lg">Çeviriler</legend>
                <?php foreach ([
                    ['name_en', 'İngilizce ürün adı', 200, 1],
                    ['name_ar', 'Arapça ürün adı', 200, 1],
                    ['description_en', 'İngilizce açıklama', 5000, 3],
                    ['description_ar', 'Arapça açıklama', 5000, 3],
                ] as [$field, $label, $maximum, $rows]): ?>
                    <div>
                        <label for="product-<?php echo $field; ?>" class="admin-muted mb-1 block text-sm"><?php echo $label; ?></label>
                        <textarea id="product-<?php echo $field; ?>" name="<?php echo $field; ?>" rows="<?php echo $rows; ?>" maxlength="<?php echo $maximum; ?>" class="w-full rounded-lg px-3 py-2" data-testid="product-<?php echo str_replace('_', '-', $field); ?>"><?php echo sanitize($value($field, $editProduct[$field] ?? '')); ?></textarea>
                        <?php if ($error($field)): ?><p role="alert" class="admin-field-error"><?php echo sanitize($error($field)); ?></p><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <p class="admin-muted text-xs">Boş çeviriler misafir menüsünde Türkçe metni kullanır.</p>
            </fieldset>

            <fieldset class="space-y-3 border-t pt-4">
                <legend class="px-1 font-serif text-lg">İçerikler</legend>
                <?php foreach ([['ingredients', 'Türkçe içerik'], ['ingredients_en', 'İngilizce içerik'], ['ingredients_ar', 'Arapça içerik']] as [$field, $label]): ?>
                    <div>
                        <label for="product-<?php echo $field; ?>" class="admin-muted mb-1 block text-sm"><?php echo $label; ?></label>
                        <textarea id="product-<?php echo $field; ?>" name="<?php echo $field; ?>" rows="2" maxlength="5000" class="w-full rounded-lg px-3 py-2" data-testid="product-<?php echo str_replace('_', '-', $field); ?>"><?php echo sanitize($value($field, $editProduct[$field] ?? '')); ?></textarea>
                        <?php if ($error($field)): ?><p role="alert" class="admin-field-error"><?php echo sanitize($error($field)); ?></p><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php $ingredientsChecked = $value('ingredients_verified', $editProduct && $editProduct['ingredients_verified_at'] ? '1' : '') !== ''; ?>
                <label class="flex min-h-11 items-center gap-3 text-sm">
                    <input type="checkbox" name="ingredients_verified" value="1" <?php echo $ingredientsChecked ? 'checked' : ''; ?> class="h-5 w-5" data-testid="product-ingredients-verified">
                    İçerik bilgisini doğruladım
                </label>
                <?php if ($error('ingredients_verified')): ?><p role="alert" class="admin-field-error"><?php echo sanitize($error('ingredients_verified')); ?></p><?php endif; ?>
            </fieldset>

            <?php $caloriesChecked = $value('calories_verified', $editProduct && $editProduct['calories_verified_at'] ? '1' : '') !== ''; ?>
            <label class="flex min-h-11 items-center gap-3 text-sm">
                <input type="checkbox" name="calories_verified" value="1" <?php echo $caloriesChecked ? 'checked' : ''; ?> class="h-5 w-5" data-testid="product-calories-verified">
                Kalori bilgisini doğruladım
            </label>
            <?php if ($error('calories_verified')): ?><p role="alert" class="admin-field-error"><?php echo sanitize($error('calories_verified')); ?></p><?php endif; ?>

            <div>
                <label for="product-image" class="admin-muted mb-1 block text-sm">Ürün görseli</label>
                <input id="product-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" class="w-full rounded-lg p-2 text-sm" data-testid="product-image">
                <?php if ($editProduct && $editProduct['image_path']): ?><p class="admin-muted mt-1 text-xs">Yeni dosya seçmezseniz mevcut görsel korunur.</p><?php endif; ?>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="admin-button admin-button-primary flex-1" data-testid="product-save"><?php echo $editProduct ? 'Değişiklikleri kaydet' : 'Ürünü ekle'; ?></button>
                <?php if ($editProduct): ?><a href="products.php<?php echo $selectedCategory ? '?category=' . $selectedCategory : ''; ?>" class="admin-button admin-button-secondary">Vazgeç</a><?php endif; ?>
            </div>
        </form>
    </aside>
</div>
<?php include __DIR__ . '/footer.php'; ?>
