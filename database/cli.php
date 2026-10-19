<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/lib.php';

$command = $argv[1] ?? '';
if (!in_array($command, ['migrate', 'seed', 'status', 'verify'], true)) {
    fwrite(STDERR, "Usage: php database/cli.php migrate|seed|status|verify\n");
    exit(64);
}

try {
    $config = chef_load_config();
    $pdo = chef_connect($config, true);

    if ($command === 'migrate') {
        $versions = chef_migrate($pdo);
        echo $versions ? 'Applied: ' . implode(', ', $versions) . PHP_EOL : "No pending migrations.\n";
        exit(0);
    }

    if ($command === 'seed') {
        $counts = chef_seed($pdo, require __DIR__ . '/seed-data.php');
        echo "Seed complete: {$counts['categories']} categories, {$counts['products']} products.\n";
        exit(0);
    }

    if ($command === 'verify') {
        $versions = chef_verify_schema($pdo);
        echo 'Schema valid: ' . implode(', ', $versions) . PHP_EOL;
        exit(0);
    }

    $applied = chef_applied_migrations($pdo);
    foreach (chef_migration_files() as $file) {
        $migration = chef_load_migration($file);
        $version = (string)$migration['version'];
        $state = isset($applied[$version]) ? 'applied' : 'pending';
        echo "{$version} {$state} {$migration['description']}\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Database command failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
