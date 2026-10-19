<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, no-cache, max-age=0, must-revalidate');
header('Vary: Accept-Encoding');
chef_require_method(['GET']);

function menu_nullable_text(mixed $value): ?string
{
    $text = trim((string)($value ?? ''));
    return $text === '' ? null : $text;
}

function menu_localized_field(array $row, string $column): array
{
    return [
        'tr' => menu_nullable_text($row[$column] ?? null),
        'en' => menu_nullable_text($row[$column . '_en'] ?? null),
        'ar' => menu_nullable_text($row[$column . '_ar'] ?? null),
    ];
}

function menu_ingredient_field(array $row): ?array
{
    if (empty($row['ingredients_verified_at'])) {
        return null;
    }

    $ingredients = [];
    foreach (['tr' => 'ingredients', 'en' => 'ingredients_en', 'ar' => 'ingredients_ar'] as $language => $column) {
        $text = menu_nullable_text($row[$column] ?? null);
        $ingredients[$language] = $text === null
            ? null
            : array_values(array_filter(array_map('trim', preg_split('/\R+/u', $text) ?: []), static fn(string $line): bool => $line !== ''));
    }

    return array_filter($ingredients, static fn(?array $value): bool => $value !== null) === [] ? null : $ingredients;
}

function menu_public_image(?string $reference): ?string
{
    if ($reference === null || $reference === '' || str_starts_with($reference, 'managed:')) {
        return null;
    }
    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $reference) === 1) {
        return null;
    }
    $url = chef_media_image_url($reference);
    if ($url === null || !str_starts_with($url, '/') || str_starts_with($url, '//') || str_contains($url, '\\')) {
        return null;
    }
    if (preg_match('#(?:^|/)\.\.(?:/|$)|[\x00-\x1F\x7F]#', $url) === 1) {
        return null;
    }
    return $url;
}

// Grid cards use the small derivative. Falls back to the main image when no thumbnail exists.
function menu_public_thumbnail(?string $reference): ?string
{
    if (menu_public_image($reference) === null) {
        return null;
    }
    $url = chef_media_thumbnail_url($reference);
    return is_string($url) && str_starts_with($url, '/') && !str_starts_with($url, '//') ? $url : menu_public_image($reference);
}

function menu_etag_matches(string $etag): bool
{
    $header = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    if ($header === '*') {
        return true;
    }
    return in_array($etag, array_map('trim', explode(',', $header)), true);
}

try {
    $pdo->beginTransaction();
    $version = get_menu_version($pdo);
    $etag = '"chef-menu-v' . $version . '"';
    header('ETag: ' . $etag);
    if (menu_etag_matches($etag)) {
        $pdo->rollBack();
        http_response_code(304);
        exit;
    }

    $categoryStatement = $pdo->prepare(
        'SELECT id, public_id, name, name_en, name_ar, image_path
         FROM categories
         ORDER BY sort_order ASC, id ASC'
    );
    $categoryStatement->execute();
    $categoryRows = $categoryStatement->fetchAll();
    $categoryIds = [];
    $categories = [];
    foreach ($categoryRows as $row) {
        $publicId = menu_nullable_text($row['public_id'] ?? null) ?? (string)$row['id'];
        $categoryIds[(int)$row['id']] = $publicId;
        $categories[] = [
            'id' => $publicId,
            'name' => menu_localized_field($row, 'name'),
            'image' => menu_public_image(menu_nullable_text($row['image_path'] ?? null)),
            'thumbnail' => menu_public_thumbnail(menu_nullable_text($row['image_path'] ?? null)),
        ];
    }

    $productStatement = $pdo->prepare(
        'SELECT id, public_id, category_id, name, name_en, name_ar,
                description, description_en, description_ar, price, image_path,
                ingredients, ingredients_en, ingredients_ar, calories,
                ingredients_verified_at, calories_verified_at
         FROM products
         ORDER BY sort_order ASC, id ASC'
    );
    $productStatement->execute();
    $items = [];
    foreach ($productStatement->fetchAll() as $row) {
        $category = $categoryIds[(int)$row['category_id']] ?? null;
        if ($category === null) {
            continue;
        }
        $calories = !empty($row['calories_verified_at']) && $row['calories'] !== null
            ? (int)$row['calories']
            : null;
        $items[] = [
            'id' => menu_nullable_text($row['public_id'] ?? null) ?? (string)$row['id'],
            'category' => $category,
            'name' => menu_localized_field($row, 'name'),
            'description' => menu_localized_field($row, 'description'),
            'image' => menu_public_image(menu_nullable_text($row['image_path'] ?? null)),
            'thumbnail' => menu_public_thumbnail(menu_nullable_text($row['image_path'] ?? null)),
            'price' => (float)$row['price'],
            'ingredients' => menu_ingredient_field($row),
            'calories' => $calories,
        ];
    }

    $wifiStatement = $pdo->prepare('SELECT wifi_name, wifi_password FROM branches WHERE id = :id LIMIT 1');
    $wifiStatement->execute([':id' => 1]);
    $branch = $wifiStatement->fetch();
    if (!$branch) {
        $wifiStatement = $pdo->prepare('SELECT wifi_name, wifi_password FROM branches ORDER BY id ASC LIMIT 1');
        $wifiStatement->execute();
        $branch = $wifiStatement->fetch();
    }
    $network = $branch ? menu_nullable_text($branch['wifi_name'] ?? null) : null;
    $wifi = $network === null ? null : [
        'network' => $network,
        'password' => menu_nullable_text($branch['wifi_password'] ?? null),
    ];

    $campaignStatement = $pdo->prepare('SELECT is_active, image_path FROM campaigns WHERE id = :id LIMIT 1');
    $campaignStatement->execute([':id' => 1]);
    $campaignRow = $campaignStatement->fetch();
    $campaignImage = $campaignRow ? menu_public_image(menu_nullable_text($campaignRow['image_path'] ?? null)) : null;
    $campaign = $campaignRow && (int)$campaignRow['is_active'] === 1 && $campaignImage !== null ? [
        'enabled' => true,
        'image' => $campaignImage,
        'text' => ['tr' => null, 'en' => null, 'ar' => null],
    ] : null;

    $json = json_encode([
        'version' => $version,
        'currency' => 'TRY',
        'categories' => $categories,
        'items' => $items,
        'wifi' => $wifi,
        'campaign' => $campaign,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $pdo->commit();
    echo $json;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    chef_log('error', 'menu_read_failed', 500, $exception);
    chef_public_error(500, 'Request failed.');
}
