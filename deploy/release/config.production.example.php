<?php
declare(strict_types=1);

// Chef production configuration template.
// Copy this file to <PRIVATE_ROOT>/config.php. Set its mode to 0600.
// Replace every <...> placeholder. Never place this file under the public document root.
// <ACCOUNT_HOME>  example: /home/u000000000
// <DOCUMENT_ROOT> example: /home/u000000000/domains/example.com/public_html
// <PRIVATE_ROOT>  example: /home/u000000000/chef-private
// <BASE_PATH>     empty for a site at the domain root, or a subdirectory such as /menu

return [
    'database' => [
        'dsn' => 'mysql:host=localhost;port=3306;dbname=<DATABASE_NAME>;charset=utf8mb4',
        'username' => '<DATABASE_USER>',
        'password' => '<DATABASE_PASSWORD>',
    ],
    // Optional. Remove this block to run migrations with the database user above.
    'migration_database' => [
        'dsn' => 'mysql:host=localhost;port=3306;dbname=<DATABASE_NAME>;charset=utf8mb4',
        'username' => '<DATABASE_USER>',
        'password' => '<DATABASE_PASSWORD>',
    ],
    'application' => [
        'logo_url' => 'assets/chef-logo.svg',
    ],
    'security' => [
        'environment' => 'production',
        // Add proxy IP addresses only when the host terminates TLS before PHP.
        'trusted_proxies' => [],
        'session' => [
            'name' => 'CHEFADMIN',
            'path' => '<BASE_PATH>/hvk/admin',
            'domain' => '',
            'secure' => true,
            'idle_seconds' => 1800,
            'absolute_seconds' => 28800,
        ],
        'throttle' => [
            // 64 hex characters from: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
            'hmac_key' => '<RANDOM_HMAC_KEY>',
            // Output of: php database/recover-owner.php --hash-only (use a random throwaway password)
            'dummy_password_hash' => '<DUMMY_PASSWORD_HASH>',
            'window_seconds' => 900,
            'account_limit' => 5,
            'address_limit' => 10,
            'block_seconds' => 900,
            'stale_seconds' => 172800,
        ],
    ],
    'logging' => [
        'file' => '<PRIVATE_ROOT>/logs/app.log',
    ],
    'media' => [
        'public_document_root' => '<DOCUMENT_ROOT>',
        'public_root' => '<DOCUMENT_ROOT>/media',
        'private_root' => '<PRIVATE_ROOT>/media',
        'temporary_root' => '<PRIVATE_ROOT>/tmp/media',
        // A fresh Chef installation has no legacy uploads. Keep this empty private directory.
        'legacy_public_root' => '<PRIVATE_ROOT>/legacy-uploads',
        'public_base_url' => '<BASE_PATH>/media',
        'max_bytes' => 4194304,
        'request_overhead_bytes' => 1048576,
        'max_pixels' => 12000000,
        'main_max_dimension' => 1200,
        'thumbnail_max_dimension' => 320,
        'webp_quality' => 80,
        'operation_ttl_seconds' => 3600,
    ],
];
