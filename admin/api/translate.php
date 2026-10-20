<?php
declare(strict_types=1);

// Translates short Turkish menu text for the admin form. Free services, no API key.
// Destinations are fixed below. User input never selects a host.
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../translate-service.php';
require_login();
chef_require_method(['POST']);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!verify_csrf((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['error' => 'Oturum doğrulanamadı. Sayfayı yenileyin.']);
    exit;
}

$input = json_decode((string)file_get_contents('php://input', false, null, 0, 20000), true);
$texts = is_array($input['texts'] ?? null) ? $input['texts'] : [];
$targets = is_array($input['targets'] ?? null) ? $input['targets'] : [];
$targets = array_values(array_intersect(['en', 'ar'], $targets));
if ($texts === [] || count($texts) > 4 || $targets === []) {
    http_response_code(422);
    echo json_encode(['error' => 'Çevrilecek metin yok.']);
    exit;
}

$result = [];
foreach ($texts as $key => $text) {
    if (!is_string($key) || !preg_match('/^[a-z_]{1,30}$/', $key) || !is_string($text)) continue;
    $text = trim($text);
    if ($text === '' || mb_strlen($text, 'UTF-8') > 1000) continue;
    foreach ($targets as $target) {
        $translated = chef_translate($text, $target);
        if ($translated !== null) {
            $result[$key . '_' . $target] = mb_substr($translated, 0, 5000, 'UTF-8');
        }
    }
}

if ($result === []) {
    http_response_code(502);
    echo json_encode(['error' => 'Çeviri servisine ulaşılamadı. Biraz sonra tekrar deneyin.']);
    exit;
}
echo json_encode(['translations' => $result], JSON_UNESCAPED_UNICODE);
