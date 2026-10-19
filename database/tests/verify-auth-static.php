<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/admin/security.php';

$inventory = require __DIR__ . '/admin-route-inventory.php';
$expected = array_merge(
    array_keys($inventory['public']),
    array_keys($inventory['protected']),
    $inventory['includes']
);
sort($expected);
$actual = [];
$root = dirname(__DIR__, 2) . '/admin';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    $actual[] = $relative;
}
sort($actual);
if ($actual !== $expected) {
    throw new RuntimeException('The administrator route inventory does not match the PHP entry points.');
}

$source = '';
foreach ($actual as $route) {
    $source .= file_get_contents($root . '/' . $route);
}
preg_match_all('/\$action\s*={2,3}\s*[\'\"]([^\'\"]+)[\'\"]/', $source, $matches);
$listed = array_unique(array_merge(...array_values($inventory['actions'])));
foreach (array_unique($matches[1]) as $action) {
    if (!in_array($action, $listed, true)) {
        throw new RuntimeException('Unlisted administrator action: ' . $action);
    }
}

$chefConfig = [
    'security' => [
        'trusted_proxies' => ['10.0.0.0/8', '192.0.2.10/32'],
        'throttle' => ['hmac_key' => str_repeat('k', 32)],
    ],
    'database' => ['dsn' => 'mysql:host=invalid', 'username' => 'test', 'password' => 'sensitive-marker'],
];
$untrusted = chef_client_ip([
    'REMOTE_ADDR' => '203.0.113.7',
    'HTTP_X_FORWARDED_FOR' => '198.51.100.4',
]);
if ($untrusted !== '203.0.113.7') {
    throw new RuntimeException('An untrusted peer changed the client address.');
}
$trusted = chef_client_ip([
    'REMOTE_ADDR' => '10.0.0.5',
    'HTTP_X_FORWARDED_FOR' => '198.51.100.9, 192.0.2.10, 10.0.0.4',
]);
if ($trusted !== '198.51.100.9') {
    throw new RuntimeException('The trusted proxy chain was not resolved right to left.');
}
if (chef_request_is_https(['REMOTE_ADDR' => '203.0.113.7', 'HTTP_X_FORWARDED_PROTO' => 'https'])) {
    throw new RuntimeException('An untrusted peer changed the request scheme.');
}
if (!chef_request_is_https(['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_PROTO' => 'https'])) {
    throw new RuntimeException('A trusted proxy scheme was not accepted.');
}
if (chef_network_prefix('198.51.100.19') !== '198.51.100.0/24') {
    throw new RuntimeException('The IPv4 monitoring prefix is invalid.');
}
if (!str_ends_with(chef_network_prefix('2001:db8:1234:5678::1'), '/56')) {
    throw new RuntimeException('The IPv6 monitoring prefix is invalid.');
}
$cleaned = chef_clean_log_value("sensitive-marker\r\nmysql:host=private;dbname=secret");
if (str_contains($cleaned, 'sensitive-marker') || str_contains($cleaned, "\n") || str_contains($cleaned, 'dbname=secret')) {
    throw new RuntimeException('Log redaction failed.');
}

echo "Static auth checks: PASS\n";
