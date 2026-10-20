<?php
declare(strict_types=1);

// Bulk catalog import for the owner's server terminal.
// Usage: CHEF_CONFIG_FILE=/path/config.php php database/catalog-import.php plan.json image-directory
// The plan can add categories, rename products, add products and set product images.
// Every step is idempotent: a second run skips what already exists.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/admin/functions.php';
require_once dirname(__DIR__) . '/admin/translate-service.php';

[$_, $planFile, $imageDir] = $argv + [null, null, null];
if (!is_string($planFile) || !is_file($planFile) || !is_string($imageDir) || !is_dir($imageDir)) {
    fwrite(STDERR, "Usage: php database/catalog-import.php plan.json image-directory\n");
    exit(2);
}
$plan = json_decode((string)file_get_contents($planFile), true, 16, JSON_THROW_ON_ERROR);
$report = [];
$say = static function (string $line) use (&$report): void {
    $report[] = $line;
    fwrite(STDOUT, $line . PHP_EOL);
};

$categoryId = static function (PDO $pdo, string $name): ?int {
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = :name LIMIT 1');
    $stmt->execute([':name' => $name]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int)$id;
};
$productId = static function (PDO $pdo, string $name): ?int {
    $stmt = $pdo->prepare('SELECT id FROM products WHERE name = :name LIMIT 1');
    $stmt->execute([':name' => $name]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int)$id;
};
$translate = static function (string $text): array {
    return ['en' => chef_translate($text, 'en'), 'ar' => chef_translate($text, 'ar')];
};

// 1. Categories, placed after a named category.
foreach ($plan['categories'] ?? [] as $category) {
    $name = (string)$category['name'];
    if ($categoryId($pdo, $name) !== null) {
        $say("SKIP category exists: $name");
        continue;
    }
    $names = $translate($name);
    $pdo->beginTransaction();
    $after = $categoryId($pdo, (string)($category['after'] ?? ''));
    $position = $after === null
        ? (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM categories')->fetchColumn()
        : (int)$pdo->query('SELECT sort_order FROM categories WHERE id = ' . $after)->fetchColumn() + 1;
    $pdo->prepare('UPDATE categories SET sort_order = sort_order + 1 WHERE sort_order >= :position')->execute([':position' => $position]);
    $pdo->prepare('INSERT INTO categories (public_id, name, name_en, name_ar, sort_order) VALUES (:public_id, :name, :name_en, :name_ar, :sort_order)')
        ->execute([
            ':public_id' => $category['public_id'] ?? null,
            ':name' => $name,
            ':name_en' => $names['en'],
            ':name_ar' => $names['ar'],
            ':sort_order' => $position,
        ]);
    chef_normalize_category_order($pdo);
    bump_menu_version($pdo);
    $pdo->commit();
    $say("ADD category: $name (en: {$names['en']})");
}

// 2. Product renames. Translations follow the new name.
foreach ($plan['renames'] ?? [] as $rename) {
    $from = (string)$rename['from'];
    $to = (string)$rename['to'];
    $id = $productId($pdo, $from);
    if ($id === null) {
        $say($productId($pdo, $to) !== null ? "SKIP already renamed: $to" : "MISS product not found: $from");
        continue;
    }
    $names = $translate($to);
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE products SET name = :name, name_en = :name_en, name_ar = :name_ar WHERE id = :id')
        ->execute([':name' => $to, ':name_en' => $names['en'], ':name_ar' => $names['ar'], ':id' => $id]);
    bump_menu_version($pdo);
    $pdo->commit();
    $say("RENAME: $from -> $to");
}

// 3. New products.
foreach ($plan['products'] ?? [] as $product) {
    $name = (string)$product['name'];
    if ($productId($pdo, $name) !== null) {
        $say("SKIP product exists: $name");
        continue;
    }
    $category = $categoryId($pdo, (string)$product['category']);
    if ($category === null) {
        $say("MISS category not found for $name: {$product['category']}");
        continue;
    }
    $names = $translate($name);
    $pdo->beginTransaction();
    chef_lock_category($pdo, $category);
    $pdo->prepare(
        'INSERT INTO products (category_id, name, name_en, name_ar, price, sort_order)
         VALUES (:category_id, :name, :name_en, :name_ar, :price, :sort_order)'
    )->execute([
        ':category_id' => $category,
        ':name' => $name,
        ':name_en' => $names['en'],
        ':name_ar' => $names['ar'],
        ':price' => number_format((float)($product['price'] ?? 0), 2, '.', ''),
        ':sort_order' => next_product_sort_order($pdo, $category),
    ]);
    bump_menu_version($pdo);
    $pdo->commit();
    $say("ADD product: $name ({$product['category']}, {$product['price']} TL)");
}

// 4. Product images through the same media pipeline as admin uploads.
foreach ($plan['images'] ?? [] as $name => $file) {
    $id = $productId($pdo, (string)$name);
    $path = rtrim($imageDir, '/') . '/' . basename((string)$file);
    if ($id === null || !is_file($path)) {
        $say("MISS image target: $name ($file)");
        continue;
    }
    $stage = null;
    try {
        $stage = chef_media_stage_upload($pdo, 'image', 'product', $path);
        $pdo->beginTransaction();
        $reference = chef_media_activate($pdo, $stage, 'product', $id);
        $pdo->prepare('UPDATE products SET image_path = :image WHERE id = :id')->execute([':image' => $reference, ':id' => $id]);
        bump_menu_version($pdo);
        $pdo->commit();
        chef_media_finalize_operation($pdo, $stage['operation_id']);
        $say("IMAGE: $name");
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($stage !== null) chef_media_abandon_operation($pdo, $stage['operation_id']);
        $say("FAIL image $name: " . $exception->getMessage());
    }
}

$cleanup = chef_media_cleanup($pdo);
$say('CLEANUP: ' . json_encode($cleanup));
