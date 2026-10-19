<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/admin/security.php';

$source = $argv[1] ?? '';
$target = $argv[2] ?? '';
if (!chef_absolute_path($source) || !chef_absolute_path($target) || chef_path_inside($target, chef_document_root())) {
    throw new RuntimeException('The outage configuration path is invalid.');
}
$config = require $source;
if (!is_array($config) || !isset($config['database'])) {
    throw new RuntimeException('The source configuration is invalid.');
}
$config['database']['dsn'] = 'mysql:host=127.0.0.1;port=1;dbname=chef_alacarte_test;charset=utf8mb4';
file_put_contents($target, "<?php\ndeclare(strict_types=1);\nreturn " . var_export($config, true) . ";\n");
$secured = PHP_OS_FAMILY === 'Windows' ? chef_apply_windows_acl($target) : chmod($target, 0600);
if (!$secured || !chef_private_file_is_safe($target)) {
    throw new RuntimeException('The outage configuration ACL is unsafe.');
}
