<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login();
chef_require_method(['POST']);

if (!verify_csrf((string)($_POST['csrf_token'] ?? ''))) {
    chef_public_error(403, 'Request validation failed.');
}
if (!chef_consume_submission_token((string)($_POST['submission_token'] ?? ''))) {
    chef_public_error(409, 'Submission token is invalid or expired.');
}

chef_destroy_session();
header('Location: login.php', true, 303);
exit;
