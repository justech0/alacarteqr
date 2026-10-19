<?php
declare(strict_types=1);

// Translates short Turkish menu text for the admin form. Free services, no API key.
// Destinations are fixed below. User input never selects a host.
require_once __DIR__ . '/../functions.php';
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

function chef_translate_fetch(string $url): ?string
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'ChefMenuAdmin/1.0',
    ]);
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return is_string($body) && $status === 200 && strlen($body) < 200000 ? $body : null;
}

// Primary: Google's public web endpoint. Fallback: MyMemory free tier.
function chef_translate(string $text, string $target): ?string
{
    $query = rawurlencode($text);
    $body = chef_translate_fetch('https://translate.googleapis.com/translate_a/single?client=gtx&sl=tr&tl=' . $target . '&dt=t&q=' . $query);
    $data = $body !== null ? json_decode($body, true) : null;
    if (is_array($data[0] ?? null)) {
        $out = '';
        foreach ($data[0] as $part) {
            $out .= is_string($part[0] ?? null) ? $part[0] : '';
        }
        if (trim($out) !== '') return trim($out);
    }
    $body = chef_translate_fetch('https://api.mymemory.translated.net/get?langpair=tr|' . $target . '&q=' . $query);
    $data = $body !== null ? json_decode($body, true) : null;
    $out = $data['responseData']['translatedText'] ?? null;
    return is_string($out) && trim($out) !== '' && (int)($data['responseStatus'] ?? 0) === 200 ? trim(html_entity_decode($out, ENT_QUOTES, 'UTF-8')) : null;
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
