<?php
declare(strict_types=1);

return [
    'database' => [
        'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=chef_alacarte_dev;charset=utf8mb4',
        'username' => 'chef_app',
        'password' => 'replace-with-a-private-password',
    ],
    'migration_database' => [
        'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=chef_alacarte_dev;charset=utf8mb4',
        'username' => 'chef_migrator',
        'password' => 'replace-with-a-private-migration-password',
    ],
    'application' => [
        'logo_url' => 'assets/chef-logo.svg',
    ],
    'security' => [
        'environment' => 'production',
        'trusted_proxies' => [],
        'session' => [
            'name' => 'CHEFADMIN',
            'path' => '/admin',
            'domain' => '',
            'secure' => true,
            'idle_seconds' => 1800,
            'absolute_seconds' => 28800,
        ],
        'throttle' => [
            'hmac_key' => 'replace-with-at-least-32-random-characters',
            'dummy_password_hash' => 'replace-with-a-password_hash-output',
            'window_seconds' => 900,
            'account_limit' => 5,
            'address_limit' => 10,
            'block_seconds' => 900,
            'stale_seconds' => 172800,
        ],
    ],
    'logging' => [
        'file' => '/home/account/private/chef/logs/app.log',
    ],
    'media' => [
        'public_document_root' => '/home/account/public_html',
        'public_root' => '/home/account/public_html/media',
        'private_root' => '/home/account/private/chef/media',
        'temporary_root' => '/home/account/private/chef/tmp/media',
        'legacy_public_root' => '/home/account/public_html/admin/uploads',
        'public_base_url' => '/media',
        'max_bytes' => 4194304,
        'request_overhead_bytes' => 1048576,
        'max_pixels' => 12000000,
        'main_max_dimension' => 1200,
        'thumbnail_max_dimension' => 320,
        'webp_quality' => 80,
        'operation_ttl_seconds' => 3600,
    ],
];
