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

try {
    chef_media_assert_request_size();
} catch (ChefMediaValidationException $exception) {
    http_response_code(413);
    echo json_encode(['error' => 'Yükleme isteği çok büyük.']);
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$isMultipart = stripos($contentType, 'multipart/form-data') !== false;
$input = $isMultipart ? $_POST : json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input) && !empty($_POST)) {
    $input = $_POST;
}

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Geçersiz veri']);
    exit;
}

$branchId = isset($input['branch_id']) && $input['branch_id'] !== '' ? (int)$input['branch_id'] : null;
$topic = trim((string)($input['topic'] ?? ''));
$rating = (int)($input['rating'] ?? 0);
$comment = trim((string)($input['message'] ?? $input['comment'] ?? ''));
$contact = trim((string)($input['contact'] ?? ''));
$language = trim((string)($input['language'] ?? 'tr'));
$allowedTopics = ['taste', 'service', 'staff', 'hygiene', 'other'];
$allowedLanguages = ['tr', 'en', 'ar'];

if (
    $rating < 1 || $rating > 5
    || $comment === '' || mb_strlen($comment) > 2000
    || mb_strlen($contact) > 150
    || !in_array($topic, $allowedTopics, true)
    || !in_array($language, $allowedLanguages, true)
) {
    http_response_code(422);
    echo json_encode(['error' => 'Gönderilen alanlar geçersiz.']);
    exit;
}

$mediaStage = null;
try {
    if ($isMultipart) {
        $mediaStage = chef_media_stage_upload($pdo, 'image', 'feedback');
    }
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'INSERT INTO feedbacks (customer_name, rating, comment, branch_id, topic, contact, image_path, language)
         VALUES (NULL, :rating, :comment, :branch_id, :topic, :contact, :image_path, :language)'
    );
    $stmt->execute([
        ':rating' => $rating,
        ':comment' => $comment,
        ':branch_id' => $branchId,
        ':topic' => $topic,
        ':contact' => $contact !== '' ? $contact : null,
        ':image_path' => null,
        ':language' => $language,
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
    echo json_encode(['success' => true, 'image_url' => null]);
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
