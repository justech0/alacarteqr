<?php
declare(strict_types=1);

if ((string)getenv('CHEF_TEST_ALLOW_RESET') !== 'task02-isolated-docker') {
    throw new RuntimeException('Set CHEF_TEST_ALLOW_RESET=task02-isolated-docker for the disposable test server.');
}

$host = (string)(getenv('CHEF_TEST_HOST') ?: '127.0.0.1');
$port = (int)(getenv('CHEF_TEST_PORT') ?: 3306);
$user = (string)(getenv('CHEF_TEST_USER') ?: 'root');
$password = (string)getenv('CHEF_TEST_PASSWORD');
$configPath = tempnam(sys_get_temp_dir(), 'chef-task02-config-');
if ($configPath === false) {
    throw new RuntimeException('Could not create the temporary CLI configuration.');
}

$config = [
    'database' => [
        'dsn' => "mysql:host={$host};port={$port};dbname=chef_alacarte_test;charset=utf8mb4",
        'username' => $user,
        'password' => $password,
    ],
];
file_put_contents($configPath, "<?php\nreturn " . var_export($config, true) . ";\n");

try {
    putenv('CHEF_DB_CONFIG=' . $configPath);
    foreach (['status', 'migrate', 'seed', 'verify'] as $command) {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/cli.php', $command],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 2)
        );
        if (!is_resource($process)) {
            throw new RuntimeException("Could not start CLI command: {$command}.");
        }
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new RuntimeException("CLI {$command} failed: " . trim((string)$error));
        }
        if (trim((string)$output) === '') {
            throw new RuntimeException("CLI {$command} returned no output.");
        }
    }
} finally {
    putenv('CHEF_DB_CONFIG');
    unlink($configPath);
}

echo "CLI smoke: PASS\n";
