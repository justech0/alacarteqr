<?php
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
chef_require_method(['GET']);

$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
$basePath = $basePath === '' ? '.' : $basePath;

function build_image_url(?string $path, string $basePath): ?string
{
    if (!$path) {
        return null;
    }
    if (str_starts_with($path, '/')) {
        return $path;
    }
    if (str_starts_with($path, 'managed:')) {
        return null;
    }
    $cleanPath = ltrim($path, '/');
    return $basePath . '/' . $cleanPath;
}

function localized_field(array $row, string $baseColumn): array
{
    $tr = (string)($row[$baseColumn] ?? '');
    $en = trim((string)($row[$baseColumn . '_en'] ?? ''));
    $ar = trim((string)($row[$baseColumn . '_ar'] ?? ''));

    return [
        'tr' => $tr,
        'en' => $en !== '' ? $en : $tr,
        'ar' => $ar !== '' ? $ar : $tr,
    ];
}

try {
    try {
        $categories = $pdo->query('SELECT id, name, name_en, name_ar, description, description_en, description_ar, image_path, COALESCE(sort_order, 0) AS sort_order FROM categories ORDER BY sort_order ASC, name ASC')->fetchAll();
    } catch (Throwable $e) {
        $categories = $pdo->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();
    }

    try {
        $products = $pdo->query('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY COALESCE(p.sort_order,0) ASC, p.id ASC')->fetchAll();
    } catch (Throwable $e) {
        $products = $pdo->query('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.id ASC')->fetchAll();
    }

    $branches = $pdo->query('SELECT * FROM branches WHERE id = 1 LIMIT 1')->fetchAll();
    if (!$branches) {
        $branches = $pdo->query('SELECT * FROM branches ORDER BY id ASC LIMIT 1')->fetchAll();
    }
    $campaign = $pdo->query('SELECT is_active, image_path FROM campaigns WHERE id = 1 LIMIT 1')->fetch();
    $version = get_menu_version($pdo);

    $response = [
        'categories' => array_map(function ($row) use ($basePath) {
            return [
                'id' => (string)$row['id'],
                'name' => localized_field($row, 'name'),
                'description' => localized_field($row, 'description'),
                'image' => build_image_url($row['image_path'], $basePath),
            ];
        }, $categories),
        'products' => array_map(function ($row) use ($basePath) {
            return [
                'id' => (string)$row['id'],
                'categoryId' => (string)$row['category_id'],
                'name' => localized_field($row, 'name'),
                'description' => localized_field($row, 'description'),
                'price' => (float)$row['price'],
                'image' => build_image_url($row['image_path'], $basePath),
                'categoryName' => $row['category_name'] ?? null,
                'createdAt' => $row['created_at'],
            ];
        }, $products),
        'branches' => array_map(function ($row) {
            return [
                'id' => (string)$row['id'],
                'name' => $row['name'],
                'wifiName' => $row['wifi_name'] ?? null,
                'wifiPassword' => $row['wifi_password'] ?? null,
            ];
        }, $branches),
        'version' => $version,
        'campaign' => [
            'active' => ($campaign['is_active'] ?? 0) == 1 && !empty($campaign['image_path']),
            'image' => !empty($campaign['image_path']) ? build_image_url($campaign['image_path'], $basePath) : null,
        ],
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    chef_log('error', 'menu_read_failed', 500, $e);
    chef_public_error(500, 'Request failed.');
}
