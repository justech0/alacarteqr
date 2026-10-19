<?php
declare(strict_types=1);

return [
    'version' => '2026101402',
    'description' => 'Add auth invalidation, throttling, upload ownership, and feedback idempotency.',
    'up' => static function (PDO $pdo): void {
        chef_add_column($pdo, 'admins', 'auth_version', 'INT UNSIGNED NOT NULL DEFAULT 1 AFTER `password_hash`');

        chef_add_column($pdo, 'feedbacks', 'idempotency_key_hash', 'BINARY(32) DEFAULT NULL AFTER `language`');
        chef_add_column($pdo, 'feedbacks', 'request_fingerprint', 'BINARY(32) DEFAULT NULL AFTER `idempotency_key_hash`');
        chef_add_index($pdo, 'feedbacks', 'uq_feedbacks_idempotency_key', '`idempotency_key_hash`', true);

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_login_throttles (
            signal_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            signal_hash BINARY(32) NOT NULL,
            window_started_at DATETIME NOT NULL,
            failure_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            blocked_until DATETIME DEFAULT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (signal_type, signal_hash),
            INDEX idx_admin_throttle_cleanup (updated_at),
            INDEX idx_admin_throttle_blocked (blocked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS managed_uploads (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            owner_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            owner_id BIGINT UNSIGNED NOT NULL,
            purpose VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            storage_path VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            state VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
            delete_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            last_error_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE INDEX uq_managed_uploads_path (storage_path),
            INDEX idx_managed_uploads_owner (owner_type, owner_id),
            INDEX idx_managed_uploads_cleanup (state, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'verify' => static function (PDO $pdo): void {
        foreach (['admin_login_throttles', 'managed_uploads'] as $table) {
            if (!chef_table_exists($pdo, $table)) {
                throw new RuntimeException("Required table is missing: {$table}.");
            }
        }
        foreach ([
            ['admins', 'auth_version'],
            ['feedbacks', 'idempotency_key_hash'],
            ['feedbacks', 'request_fingerprint'],
        ] as [$table, $column]) {
            if (!chef_column_exists($pdo, $table, $column)) {
                throw new RuntimeException("Required column is missing: {$table}.{$column}.");
            }
        }
        if (!chef_index_exists($pdo, 'feedbacks', 'uq_feedbacks_idempotency_key')) {
            throw new RuntimeException('The feedback idempotency index is missing.');
        }
    },
];
