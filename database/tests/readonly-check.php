<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/hvk/admin/security.php';
require_once dirname(__DIR__, 2) . '/hvk/admin/media-service.php';

$chefConfig = chef_load_web_config();

$credentialsPath = (string)getenv('CHEF_TEST_CREDENTIALS');
if ($credentialsPath === '' || !chef_absolute_path($credentialsPath) || chef_path_inside($credentialsPath, chef_document_root())) {
    throw new RuntimeException('The credentials path is invalid.');
}
$credentials = json_decode((string)file_get_contents($credentialsPath), true, 512, JSON_THROW_ON_ERROR);
$readonly = $credentials['readonly'] ?? [];
$pdo = new PDO((string)$readonly['dsn'], (string)$readonly['username'], (string)$readonly['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$check = $argv[1] ?? '';
$result = match ($check) {
    'row-counts' => [
        'categories' => (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
        'products' => (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
        'feedbacks' => (int)$pdo->query('SELECT COUNT(*) FROM feedbacks')->fetchColumn(),
        'managed_uploads' => (int)$pdo->query('SELECT COUNT(*) FROM managed_uploads')->fetchColumn(),
    ],
    'category-order' => $pdo->query('SELECT id, public_id, name, sort_order FROM categories ORDER BY sort_order, id LIMIT 100')->fetchAll(),
    'product-state' => $pdo->query(
        'SELECT id, public_id, category_id, name, price, sort_order, image_path,
                ingredients, ingredients_en, ingredients_ar, calories,
                ingredients_verified_at, calories_verified_at
         FROM products ORDER BY id DESC LIMIT 100'
    )->fetchAll(),
    'catalog-order' => $pdo->query(
        'SELECT p.id, p.public_id, p.category_id, p.name, p.sort_order
         FROM products p JOIN categories c ON c.id = p.category_id
         ORDER BY c.sort_order, p.sort_order, p.id LIMIT 200'
    )->fetchAll(),
    'media-file-presence' => array_map(static function (array $row): array {
        $files = [];
        foreach (['temporary_path' => 'temporary', 'pending_path' => (string)$row['storage_scope'], 'storage_path' => (string)$row['storage_scope']] as $field => $scope) {
            $relative = $row[$field] ?? null;
            $files[$field] = is_string($relative) && $relative !== '' ? file_exists(chef_media_absolute_path($scope, $relative)) : false;
        }
        return [
            'id' => (int)$row['id'], 'owner_type' => $row['owner_type'], 'owner_id' => (int)$row['owner_id'],
            'file_role' => $row['file_role'], 'state' => $row['state'], 'files' => $files,
        ];
    }, $pdo->query(
        "SELECT id, owner_type, owner_id, file_role, state, storage_scope, temporary_path, pending_path, storage_path
         FROM managed_uploads ORDER BY id DESC LIMIT 100"
    )->fetchAll()),
    'menu-version' => ['menu_version' => (int)$pdo->query("SELECT meta_value FROM meta WHERE meta_key = 'menu_version'")->fetchColumn()],
    default => throw new InvalidArgumentException('Unknown named check.'),
};

echo json_encode(['check' => $check, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
