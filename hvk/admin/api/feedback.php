<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST, OPTIONS');
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$isMultipart = stripos($contentType, 'multipart/form-data') !== false;
$isJson = stripos($contentType, 'application/json') !== false;
$isForm = stripos($contentType, 'application/x-www-form-urlencoded') !== false;

if (!$isMultipart && !$isJson && !$isForm) {
    http_response_code(415);
    echo json_encode(['error' => 'Desteklenmeyen içerik türü.']);
    exit;
}

$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '') {
    $parsedOrigin = parse_url($origin, PHP_URL_HOST);
    $serverHost = explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0];
    // Loopback origins are accepted only outside production. An opaque "null" origin is never accepted.
    $loopbackAllowed = (chef_security()['environment'] ?? 'production') !== 'production'
        && in_array($parsedOrigin, ['localhost', '127.0.0.1'], true);
    if (!is_string($parsedOrigin) || $parsedOrigin === '' || (strcasecmp($parsedOrigin, $serverHost) !== 0 && !$loopbackAllowed)) {
        http_response_code(403);
        echo json_encode(['error' => 'Geçersiz kaynak.']);
        exit;
    }
}

$clientIp = chef_client_ip();
$retryAfter = chef_feedback_throttle_check($pdo, $clientIp);
if ($retryAfter !== null) {
    http_response_code(429);
    header('Retry-After: ' . $retryAfter);
    echo json_encode(['error' => 'Çok fazla istek. Lütfen bir süre sonra tekrar deneyin.']);
    exit;
}

try {
    chef_media_assert_request_size();
} catch (ChefMediaValidationException $exception) {
    http_response_code(413);
    echo json_encode(['error' => 'Yükleme isteği çok büyük.']);
    exit;
}

$input = $isMultipart ? $_POST : ($isJson ? json_decode((string)file_get_contents('php://input'), true) : $_POST);
if (!is_array($input) && !empty($_POST)) {
    $input = $_POST;
}

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Geçersiz veri']);
    exit;
}

foreach (['topic', 'rating', 'message', 'comment', 'contact', 'language', 'branch_id', 'idempotency_key'] as $field) {
    if (isset($input[$field]) && is_array($input[$field])) {
        http_response_code(422);
        echo json_encode(['error' => 'Gönderilen alanlar geçersiz.']);
        exit;
    }
}

$branchId = isset($input['branch_id']) && $input['branch_id'] !== '' ? (int)$input['branch_id'] : null;
$topic = trim((string)($input['topic'] ?? ''));
$rawRating = $input['rating'] ?? null;
$comment = trim((string)($input['message'] ?? $input['comment'] ?? ''));
$contact = trim((string)($input['contact'] ?? ''));
$language = trim((string)($input['language'] ?? 'tr'));
$allowedTopics = ['taste', 'service', 'staff', 'hygiene', 'other'];
$allowedLanguages = ['tr', 'en', 'ar'];

if (
    !is_numeric($rawRating)
    || (int)$rawRating != $rawRating
    || (int)$rawRating < 1
    || (int)$rawRating > 5
    || $comment === ''
    || mb_strlen($comment) > 2000
    || mb_strlen($contact) > 150
    || !in_array($topic, $allowedTopics, true)
    || !in_array($language, $allowedLanguages, true)
) {
    http_response_code(422);
    echo json_encode(['error' => 'Gönderilen alanlar geçersiz.']);
    exit;
}

$rating = (int)$rawRating;

$rawKey = trim((string)($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ($input['idempotency_key'] ?? '')));
$idempotencyKeyHash = null;
$requestFingerprint = null;

if ($rawKey !== '') {
    if (strlen($rawKey) > 255 || !preg_match('/^[\x21-\x7E]+$/', $rawKey)) {
        http_response_code(400);
        echo json_encode(['error' => 'Geçersiz Idempotency-Key.']);
        exit;
    }
    $idempotencyKeyHash = hash('sha256', $rawKey, true);

    $imageHash = '';
    if ($isMultipart && isset($_FILES['image']) && is_uploaded_file((string)($_FILES['image']['tmp_name'] ?? ''))) {
        $imageHash = (string)hash_file('sha256', (string)$_FILES['image']['tmp_name']);
    }

    $fingerprintData = json_encode([
        'b' => $branchId,
        't' => $topic,
        'r' => $rating,
        'c' => $comment,
        'k' => $contact !== '' ? $contact : null,
        'l' => $language,
        'img' => $imageHash,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $requestFingerprint = hash('sha256', (string)$fingerprintData, true);

    $stmtKey = $pdo->prepare('SELECT id, request_fingerprint FROM feedbacks WHERE idempotency_key_hash = :key_hash LIMIT 1');
    $stmtKey->execute([':key_hash' => $idempotencyKeyHash]);
    $existing = $stmtKey->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if (hash_equals((string)$existing['request_fingerprint'], (string)$requestFingerprint)) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'id' => (int)$existing['id'],
                'duplicate' => true,
                'image_url' => null,
            ]);
            exit;
        }
        http_response_code(409);
        echo json_encode(['error' => 'Idempotency key conflict.']);
        exit;
    }
}

$mediaStage = null;
try {
    if ($isMultipart) {
        $mediaStage = chef_media_stage_upload($pdo, 'image', 'feedback');
    }
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'INSERT INTO feedbacks (customer_name, rating, comment, branch_id, topic, contact, image_path, language, idempotency_key_hash, request_fingerprint)
         VALUES (NULL, :rating, :comment, :branch_id, :topic, :contact, :image_path, :language, :key_hash, :fingerprint)'
    );
    $stmt->execute([
        ':rating' => $rating,
        ':comment' => $comment,
        ':branch_id' => $branchId,
        ':topic' => $topic,
        ':contact' => $contact !== '' ? $contact : null,
        ':image_path' => null,
        ':language' => $language,
        ':key_hash' => $idempotencyKeyHash,
        ':fingerprint' => $requestFingerprint,
    ]);
    $feedbackId = (int)$pdo->lastInsertId();
    $imagePath = null;
    if ($mediaStage !== null) {
        $imagePath = chef_media_activate($pdo, $mediaStage, 'feedback', $feedbackId);
        $pdo->prepare('UPDATE feedbacks SET image_path = :image_path WHERE id = :id')->execute([':image_path' => $imagePath, ':id' => $feedbackId]);
    }
    $pdo->commit();
    if ($mediaStage !== null) {
        chef_media_finalize_operation($pdo, $mediaStage['operation_id']);
    }

    http_response_code(201);
    echo json_encode(['success' => true, 'id' => $feedbackId, 'duplicate' => false, 'image_url' => null]);
} catch (ChefMediaValidationException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($mediaStage !== null) chef_media_abandon_operation($pdo, $mediaStage['operation_id'], 'validation_failed');
    http_response_code(422);
    echo json_encode(['error' => 'Görsel geçersiz.']);
} catch (ChefMediaStorageException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($mediaStage !== null) chef_media_abandon_operation($pdo, $mediaStage['operation_id'], 'storage_failed');
    chef_log('error', 'feedback_media_failed', 503, $e);
    chef_public_error(503, 'Service unavailable.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($mediaStage !== null) chef_media_abandon_operation($pdo, $mediaStage['operation_id'], 'database_failed');
    chef_log('error', 'feedback_write_failed', 500, $e);
    chef_public_error(500, 'Request failed.');
}
