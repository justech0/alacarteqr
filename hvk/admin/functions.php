<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/media-service.php';

function next_product_sort_order(PDO $pdo, int $categoryId): int
{
    if ($categoryId <= 0) {
        return 1;
    }

    $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM products WHERE category_id = :category_id');
    $stmt->execute([':category_id' => $categoryId]);
    return max(1, (int)$stmt->fetchColumn());
}

function chef_now(?callable $clock = null): int
{
    return $clock === null ? time() : (int)$clock();
}

function chef_submission_token(?callable $clock = null): string
{
    static $requestToken = null;
    if (is_string($requestToken)) {
        return $requestToken;
    }

    $now = chef_now($clock);
    $tokens = is_array($_SESSION['submission_tokens'] ?? null) ? $_SESSION['submission_tokens'] : [];
    $tokens = array_filter($tokens, static fn($createdAt) => is_int($createdAt) && $createdAt >= $now - 1800);
    asort($tokens, SORT_NUMERIC);
    if (count($tokens) >= 20) {
        $tokens = array_slice($tokens, -19, null, true);
    }

    $requestToken = bin2hex(random_bytes(32));
    $tokens[$requestToken] = $now;
    $_SESSION['submission_tokens'] = $tokens;
    return $requestToken;
}

function chef_consume_submission_token(string $token, ?callable $clock = null): bool
{
    $now = chef_now($clock);
    $tokens = is_array($_SESSION['submission_tokens'] ?? null) ? $_SESSION['submission_tokens'] : [];
    $valid = [];
    foreach ($tokens as $storedToken => $createdAt) {
        if (is_string($storedToken) && is_int($createdAt) && $createdAt >= $now - 1800) {
            $valid[$storedToken] = $createdAt;
        }
    }

    $matchedToken = null;
    if ($token !== '') {
        foreach (array_keys($valid) as $storedToken) {
            if (hash_equals($storedToken, $token)) {
                $matchedToken = $storedToken;
                break;
            }
        }
    }
    $accepted = $matchedToken !== null;
    if ($matchedToken !== null) unset($valid[$matchedToken]);
    $_SESSION['submission_tokens'] = $valid;
    return $accepted;
}

function chef_submission_token_error(): string
{
    return 'Bu form daha önce gönderilmiş veya süresi dolmuş. Bilgileri kontrol edip yeniden kaydedin.';
}

function chef_canonical_id(mixed $value): ?int
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }
    $raw = (string)$value;
    if (!preg_match('/^[1-9][0-9]*$/D', $raw)) {
        return null;
    }
    $maximum = (string)PHP_INT_MAX;
    if (strlen($raw) > strlen($maximum) || (strlen($raw) === strlen($maximum) && strcmp($raw, $maximum) > 0)) {
        return null;
    }
    return (int)$raw;
}

function chef_text_length(string $value): int
{
    return mb_strlen($value, 'UTF-8');
}

function chef_validate_required_text(mixed $value, int $maximum, string $label, array &$errors, string $field): string
{
    $text = trim(is_scalar($value) ? (string)$value : '');
    $length = chef_text_length($text);
    if ($length < 1 || $length > $maximum) {
        $errors[$field] = sprintf('%s 1 ile %d karakter arasında olmalıdır.', $label, $maximum);
    }
    return $text;
}

function chef_validate_optional_text(mixed $value, int $maximum, string $label, array &$errors, string $field): ?string
{
    $text = trim(is_scalar($value) ? (string)$value : '');
    if (chef_text_length($text) > $maximum) {
        $errors[$field] = sprintf('%s en fazla %d karakter olabilir.', $label, $maximum);
    }
    return $text === '' ? null : $text;
}

function chef_validate_price(mixed $value, array &$errors, string $field = 'price'): ?string
{
    $raw = trim(is_scalar($value) ? (string)$value : '');
    if (!preg_match('/^(?:[0-9]{1,8})(?:\.[0-9]{1,2})?$/D', $raw)) {
        $errors[$field] = 'Fiyat 0,00 ile 99.999.999,99 arasında ve en fazla iki ondalık basamaklı olmalıdır.';
        return null;
    }
    $parts = explode('.', $raw, 2);
    $whole = ltrim($parts[0], '0');
    $whole = $whole === '' ? '0' : $whole;
    $fraction = str_pad($parts[1] ?? '', 2, '0');
    $normalized = $whole . '.' . $fraction;
    if (strlen($whole) > 8 || ($whole === '99999999' && $fraction > '99')) {
        $errors[$field] = 'Fiyat 0,00 ile 99.999.999,99 arasında olmalıdır.';
        return null;
    }
    return $normalized;
}

function chef_validate_calories(mixed $value, array &$errors): ?int
{
    $raw = trim(is_scalar($value) ? (string)$value : '');
    if ($raw === '') {
        return null;
    }
    if (!preg_match('/^(?:0|[1-9][0-9]{0,4})$/D', $raw) || (int)$raw > 65535) {
        $errors['calories'] = 'Kalori 0 ile 65535 arasında bir tam sayı olmalıdır.';
        return null;
    }
    return (int)$raw;
}

function chef_store_form_state(string $key, array $values, array $allowedFields, array $errors = []): void
{
    $safe = [];
    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $values) && is_scalar($values[$field])) {
            $safe[$field] = (string)$values[$field];
        }
    }
    $_SESSION['form_state'][$key] = ['values' => $safe, 'errors' => $errors];
}

function chef_take_form_state(string $key): array
{
    $state = $_SESSION['form_state'][$key] ?? ['values' => [], 'errors' => []];
    unset($_SESSION['form_state'][$key]);
    return is_array($state) ? $state : ['values' => [], 'errors' => []];
}

function chef_form_value(array $state, string $field, mixed $fallback = ''): string
{
    return (string)($state['values'][$field] ?? $fallback ?? '');
}

function chef_field_error(array $state, string $field): ?string
{
    $error = $state['errors'][$field] ?? null;
    return is_string($error) && $error !== '' ? $error : null;
}

function chef_lock_category(PDO $pdo, int $categoryId): array
{
    $stmt = $pdo->prepare('SELECT id, sort_order, image_path FROM categories WHERE id = :id FOR UPDATE');
    $stmt->execute([':id' => $categoryId]);
    $category = $stmt->fetch();
    if (!$category) {
        throw new DomainException('Seçilen kategori bulunamadı.');
    }
    return $category;
}

function chef_normalize_product_order(PDO $pdo, int $categoryId): void
{
    $stmt = $pdo->prepare('SELECT id FROM products WHERE category_id = :category_id ORDER BY sort_order ASC, id ASC FOR UPDATE');
    $stmt->execute([':category_id' => $categoryId]);
    $update = $pdo->prepare('UPDATE products SET sort_order = :sort_order WHERE id = :id');
    $position = 1;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $update->execute([':sort_order' => $position++, ':id' => (int)$id]);
    }
}

function chef_normalize_category_order(PDO $pdo): void
{
    $ids = $pdo->query('SELECT id FROM categories ORDER BY sort_order ASC, id ASC FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN);
    $update = $pdo->prepare('UPDATE categories SET sort_order = :sort_order WHERE id = :id');
    $position = 1;
    foreach ($ids as $id) {
        $update->execute([':sort_order' => $position++, ':id' => (int)$id]);
    }
}

function fetch_url(string $url, int $timeout = 12): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'HVK QR Menu Translation',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body !== false && $status >= 200 && $status < 300) {
            return $body;
        }

        return null;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'header' => "User-Agent: HVK QR Menu Translation\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $context);

    return $body === false ? null : $body;
}

function translate_text(string $text, string $targetLang, ?array &$warnings = null): string
{
    $source = trim($text);
    if ($source === '') {
        return '';
    }

    if (!in_array($targetLang, ['en', 'ar'], true)) {
        return $source;
    }

    $url = 'https://api.mymemory.translated.net/get?q=' . rawurlencode($source) . '&langpair=tr|' . rawurlencode($targetLang);
    $body = fetch_url($url);
    if ($body === null) {
        $warnings[] = 'Çeviri servisine ulaşılamadı; bazı alanlarda Türkçe metin kullanılacak.';
        return $source;
    }

    $payload = json_decode($body, true);
    $translated = trim((string)($payload['responseData']['translatedText'] ?? ''));
    $status = (int)($payload['responseStatus'] ?? 0);

    if ($status !== 200 || $translated === '') {
        $message = trim((string)($payload['responseDetails'] ?? 'Çeviri servisi sonuç döndürmedi.'));
        $warnings[] = $message . ' Bazı alanlarda Türkçe metin kullanılacak.';
        return $source;
    }

    return html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function build_translations(string $name, string $description, ?array &$warnings = null): array
{
    return [
        'name_en' => translate_text($name, 'en', $warnings),
        'name_ar' => translate_text($name, 'ar', $warnings),
        'description_en' => translate_text($description, 'en', $warnings),
        'description_ar' => translate_text($description, 'ar', $warnings),
    ];
}

function update_product_translations(PDO $pdo, int $id, string $name, string $description, ?array &$warnings = null): void
{
    $translations = build_translations($name, $description, $warnings);
    $stmt = $pdo->prepare('UPDATE products SET name_en = :name_en, name_ar = :name_ar, description_en = :description_en, description_ar = :description_ar WHERE id = :id LIMIT 1');
    $stmt->execute([
        ':name_en' => $translations['name_en'],
        ':name_ar' => $translations['name_ar'],
        ':description_en' => $translations['description_en'],
        ':description_ar' => $translations['description_ar'],
        ':id' => $id,
    ]);
}

function update_category_translations(PDO $pdo, int $id, string $name, string $description, ?array &$warnings = null): void
{
    $translations = build_translations($name, $description, $warnings);
    $stmt = $pdo->prepare('UPDATE categories SET name_en = :name_en, name_ar = :name_ar, description_en = :description_en, description_ar = :description_ar WHERE id = :id LIMIT 1');
    $stmt->execute([
        ':name_en' => $translations['name_en'],
        ':name_ar' => $translations['name_ar'],
        ':description_en' => $translations['description_en'],
        ':description_ar' => $translations['description_ar'],
        ':id' => $id,
    ]);
}

function append_translation_warning(array $warnings): void
{
    $warnings = array_values(array_unique(array_filter($warnings)));
    if (!$warnings) {
        return;
    }

    flash_message('error', 'Kayıt yapıldı ancak çeviri uyarısı var: ' . $warnings[0]);
}

function sanitize(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function parse_price_input(string $raw): ?string
{
    $clean = preg_replace('/[\s₺TLtl]/u', '', trim($raw));
    if ($clean === '') {
        return null;
    }

    if (!preg_match('/^[0-9.,]+$/D', $clean)) {
        return null;
    }

    $lastComma = strrpos($clean, ',');
    $lastDot   = strrpos($clean, '.');
    $decimalPos = max($lastComma === false ? -1 : $lastComma, $lastDot === false ? -1 : $lastDot);

    if ($decimalPos !== -1) {
        $intPart = preg_replace('/[\.,]/', '', substr($clean, 0, $decimalPos));
        $fracPart = substr($clean, $decimalPos + 1);
        $normalized = $intPart . '.' . $fracPart;
    } else {
        $normalized = preg_replace('/[\.,]/', '', $clean);
    }

    $errors = [];
    return chef_validate_price($normalized, $errors);
}

function flash_message(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $msg = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $msg;
    }
    return null;
}

function fetch_counts(PDO $pdo): array
{
    $tables = ['products', 'categories', 'feedbacks'];
    $counts = [];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM {$table}");
        $counts[$table] = (int)$stmt->fetch()['total'];
    }
    return $counts;
}
