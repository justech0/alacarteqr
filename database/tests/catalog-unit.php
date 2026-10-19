<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/hvk/admin/functions.php';

$_SESSION = [];
$clock = static fn(): int => 2_000_000_000;
$tokens = [];
for ($i = 0; $i < 25; $i++) $tokens['token-' . $i] = 2_000_000_000 - $i;
$_SESSION['submission_tokens'] = $tokens;
$token = chef_submission_token($clock);
if (count($_SESSION['submission_tokens']) > 20) {
    throw new RuntimeException('Submission token limit failed.');
}
if (!chef_consume_submission_token($token, $clock) || chef_consume_submission_token($token, $clock)) {
    throw new RuntimeException('Submission token reuse control failed.');
}

$_SESSION['submission_tokens'] = ['expired' => 2_000_000_000 - 1801];
if (chef_consume_submission_token('expired', $clock)) {
    throw new RuntimeException('Expired submission token was accepted.');
}
$invalidIds = ['01', '+1', '1.0', ' 1', '1 ', '0', '-1', (string)PHP_INT_MAX . '0'];
foreach ($invalidIds as $invalidId) {
    if (chef_canonical_id($invalidId) !== null) throw new RuntimeException('Malformed ID was accepted: ' . $invalidId);
}
if (chef_canonical_id('1') !== 1) throw new RuntimeException('Canonical ID was rejected.');

$errors = [];
if (chef_validate_price('99999999.99', $errors) !== '99999999.99') throw new RuntimeException('Maximum price was rejected.');
foreach (['100000000.00', '1.234', '1e2', '-1', '+1'] as $invalidPrice) {
    $errors = [];
    if (chef_validate_price($invalidPrice, $errors) !== null) throw new RuntimeException('Invalid price was accepted: ' . $invalidPrice);
}
foreach (['1.0', '1e2', '-1', '+1', '65536'] as $invalidCalories) {
    $errors = [];
    if (chef_validate_calories($invalidCalories, $errors) !== null) throw new RuntimeException('Invalid calories were accepted: ' . $invalidCalories);
}
$errors = [];
if (chef_validate_calories('0', $errors) !== 0 || chef_validate_calories('65535', $errors) !== 65535) {
    throw new RuntimeException('Valid calorie boundary was rejected.');
}

$validImportedPrices = [
    '88,50' => '88.50',
    '91.25' => '91.25',
    '1.234,56' => '1234.56',
    '1,234.56' => '1234.56',
];
foreach ($validImportedPrices as $input => $expected) {
    if (parse_price_input($input) !== $expected) throw new RuntimeException('Valid imported price was rejected: ' . $input);
}
foreach (['1.234', '1,234', '1.23.4', '1,23,4', '1e2', '-1'] as $ambiguousPrice) {
    if (parse_price_input($ambiguousPrice) !== null) throw new RuntimeException('Ambiguous imported price was accepted: ' . $ambiguousPrice);
}
if (chef_spreadsheet_text('=CMD()') !== "'=CMD()" || chef_spreadsheet_text('Normal') !== 'Normal') {
    throw new RuntimeException('Spreadsheet formula neutralization failed.');
}

echo json_encode(['status' => 'PASS', 'checks' => ['submission reuse', 'submission expiry', 'submission limit', 'canonical IDs', 'prices', 'calories', 'import prices', 'spreadsheet text']]) . PHP_EOL;
