<?php
declare(strict_types=1);

return [
    'version' => '2026101501',
    'description' => 'Add durable media lifecycle and cleanup metadata.',
    'up' => static function (PDO $pdo): void {
        $pdo->exec('ALTER TABLE managed_uploads MODIFY owner_id BIGINT UNSIGNED NULL');
        $pdo->exec("ALTER TABLE managed_uploads MODIFY state VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active'");
        chef_add_column($pdo, 'managed_uploads', 'operation_id', 'CHAR(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL AFTER `id`');
        chef_add_column($pdo, 'managed_uploads', 'file_role', "VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'main' AFTER `purpose`");
        chef_add_column($pdo, 'managed_uploads', 'storage_scope', "VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'public' AFTER `file_role`");
        chef_add_column($pdo, 'managed_uploads', 'temporary_path', 'VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL AFTER `storage_path`');
        chef_add_column($pdo, 'managed_uploads', 'pending_path', 'VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL AFTER `temporary_path`');
        chef_add_column($pdo, 'managed_uploads', 'legacy_reference', 'VARCHAR(255) DEFAULT NULL AFTER `pending_path`');
        chef_add_column($pdo, 'managed_uploads', 'mime_type', 'VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL AFTER `legacy_reference`');
        chef_add_column($pdo, 'managed_uploads', 'byte_size', 'BIGINT UNSIGNED DEFAULT NULL AFTER `mime_type`');
        chef_add_column($pdo, 'managed_uploads', 'width', 'INT UNSIGNED DEFAULT NULL AFTER `byte_size`');
        chef_add_column($pdo, 'managed_uploads', 'height', 'INT UNSIGNED DEFAULT NULL AFTER `width`');
        chef_add_column($pdo, 'managed_uploads', 'parent_upload_id', 'BIGINT UNSIGNED DEFAULT NULL AFTER `height`');
        chef_add_column($pdo, 'managed_uploads', 'last_error_code', 'VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL AFTER `delete_attempts`');
        chef_add_column($pdo, 'managed_uploads', 'expires_at', 'DATETIME DEFAULT NULL AFTER `last_error_at`');
        chef_add_column($pdo, 'managed_uploads', 'lock_token', 'CHAR(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL AFTER `expires_at`');
        chef_add_column($pdo, 'managed_uploads', 'locked_at', 'DATETIME DEFAULT NULL AFTER `lock_token`');
        chef_add_index($pdo, 'managed_uploads', 'idx_managed_uploads_operation', '`operation_id`, `id`');
        chef_add_index($pdo, 'managed_uploads', 'idx_managed_uploads_expiry', '`state`, `expires_at`');
    },
    'verify' => static function (PDO $pdo): void {
        foreach ([
            'operation_id', 'file_role', 'storage_scope', 'temporary_path', 'pending_path', 'legacy_reference',
            'mime_type', 'byte_size', 'width', 'height', 'parent_upload_id',
            'last_error_code', 'expires_at', 'lock_token', 'locked_at',
        ] as $column) {
            if (!chef_column_exists($pdo, 'managed_uploads', $column)) {
                throw new RuntimeException("Required media column is missing: {$column}.");
            }
        }
        foreach (['idx_managed_uploads_operation', 'idx_managed_uploads_expiry'] as $index) {
            if (!chef_index_exists($pdo, 'managed_uploads', $index)) {
                throw new RuntimeException("Required media index is missing: {$index}.");
            }
        }
    },
];
