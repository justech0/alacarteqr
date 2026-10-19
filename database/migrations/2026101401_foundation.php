<?php
declare(strict_types=1);

return [
    'version' => '2026101401',
    'description' => 'Create the legacy-compatible Chef catalog foundation.',
    'up' => static function (PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(64) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE INDEX uq_admins_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS branches (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) COLLATE utf8mb4_turkish_ci NOT NULL,
            wifi_name VARCHAR(150) DEFAULT NULL,
            wifi_password VARCHAR(150) DEFAULT NULL,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS campaigns (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            image_path VARCHAR(255) DEFAULT NULL,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            public_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
            name VARCHAR(150) COLLATE utf8mb4_turkish_ci NOT NULL,
            name_en VARCHAR(200) DEFAULT NULL,
            name_ar VARCHAR(200) DEFAULT NULL,
            description TEXT COLLATE utf8mb4_turkish_ci DEFAULT NULL,
            description_en TEXT DEFAULT NULL,
            description_ar TEXT DEFAULT NULL,
            image_path VARCHAR(255) DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE INDEX uq_categories_public_id (public_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS products (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            public_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
            category_id INT DEFAULT NULL,
            name VARCHAR(200) COLLATE utf8mb4_turkish_ci NOT NULL,
            name_en VARCHAR(200) DEFAULT NULL,
            name_ar VARCHAR(200) DEFAULT NULL,
            description TEXT COLLATE utf8mb4_turkish_ci DEFAULT NULL,
            description_en TEXT DEFAULT NULL,
            description_ar TEXT DEFAULT NULL,
            ingredients TEXT COLLATE utf8mb4_turkish_ci DEFAULT NULL,
            ingredients_en TEXT DEFAULT NULL,
            ingredients_ar TEXT DEFAULT NULL,
            calories SMALLINT UNSIGNED DEFAULT NULL,
            ingredients_verified_at DATETIME DEFAULT NULL,
            calories_verified_at DATETIME DEFAULT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            sort_order INT NOT NULL DEFAULT 0,
            image_path VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE INDEX uq_products_public_id (public_id),
            INDEX idx_products_category (category_id),
            CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS feedbacks (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            customer_name VARCHAR(120) COLLATE utf8mb4_turkish_ci DEFAULT NULL,
            rating INT NOT NULL,
            comment TEXT COLLATE utf8mb4_turkish_ci DEFAULT NULL,
            branch_id INT DEFAULT NULL,
            topic VARCHAR(50) DEFAULT NULL,
            contact VARCHAR(150) DEFAULT NULL,
            image_path VARCHAR(255) DEFAULT NULL,
            language VARCHAR(5) CHARACTER SET ascii COLLATE ascii_bin DEFAULT 'tr',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_feedbacks_branch (branch_id),
            CONSTRAINT fk_feedbacks_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
            CONSTRAINT chk_feedbacks_rating CHECK (rating BETWEEN 1 AND 5)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS meta (
            meta_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
            meta_value TEXT DEFAULT NULL,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        foreach (['admins', 'branches', 'campaigns', 'categories', 'products', 'feedbacks', 'meta'] as $table) {
            chef_ensure_innodb($pdo, $table);
        }

        chef_add_column($pdo, 'categories', 'public_id', 'VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL AFTER `id`');
        chef_add_column($pdo, 'categories', 'name_en', 'VARCHAR(200) DEFAULT NULL AFTER `name`');
        chef_add_column($pdo, 'categories', 'name_ar', 'VARCHAR(200) DEFAULT NULL AFTER `name_en`');
        chef_add_column($pdo, 'categories', 'description_en', 'TEXT DEFAULT NULL AFTER `description`');
        chef_add_column($pdo, 'categories', 'description_ar', 'TEXT DEFAULT NULL AFTER `description_en`');
        chef_add_column($pdo, 'categories', 'sort_order', 'INT NOT NULL DEFAULT 0 AFTER `image_path`');
        chef_add_index($pdo, 'categories', 'uq_categories_public_id', '`public_id`', true);

        chef_add_column($pdo, 'products', 'public_id', 'VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL AFTER `id`');
        chef_add_column($pdo, 'products', 'name_en', 'VARCHAR(200) DEFAULT NULL AFTER `name`');
        chef_add_column($pdo, 'products', 'name_ar', 'VARCHAR(200) DEFAULT NULL AFTER `name_en`');
        chef_add_column($pdo, 'products', 'description_en', 'TEXT DEFAULT NULL AFTER `description`');
        chef_add_column($pdo, 'products', 'description_ar', 'TEXT DEFAULT NULL AFTER `description_en`');
        chef_add_column($pdo, 'products', 'ingredients', 'TEXT COLLATE utf8mb4_turkish_ci DEFAULT NULL AFTER `description_ar`');
        chef_add_column($pdo, 'products', 'ingredients_en', 'TEXT DEFAULT NULL AFTER `ingredients`');
        chef_add_column($pdo, 'products', 'ingredients_ar', 'TEXT DEFAULT NULL AFTER `ingredients_en`');
        chef_add_column($pdo, 'products', 'calories', 'SMALLINT UNSIGNED DEFAULT NULL AFTER `ingredients_ar`');
        chef_add_column($pdo, 'products', 'ingredients_verified_at', 'DATETIME DEFAULT NULL AFTER `calories`');
        chef_add_column($pdo, 'products', 'calories_verified_at', 'DATETIME DEFAULT NULL AFTER `ingredients_verified_at`');
        chef_add_column($pdo, 'products', 'sort_order', 'INT NOT NULL DEFAULT 0 AFTER `price`');
        chef_add_index($pdo, 'products', 'uq_products_public_id', '`public_id`', true);
        chef_add_index($pdo, 'products', 'idx_products_category', '`category_id`');

        chef_add_column($pdo, 'feedbacks', 'customer_name', 'VARCHAR(120) COLLATE utf8mb4_turkish_ci DEFAULT NULL AFTER `id`');
        chef_add_column($pdo, 'feedbacks', 'branch_id', 'INT DEFAULT NULL AFTER `comment`');
        chef_add_column($pdo, 'feedbacks', 'topic', 'VARCHAR(50) DEFAULT NULL AFTER `branch_id`');
        chef_add_column($pdo, 'feedbacks', 'contact', 'VARCHAR(150) DEFAULT NULL AFTER `topic`');
        chef_add_column($pdo, 'feedbacks', 'image_path', 'VARCHAR(255) DEFAULT NULL AFTER `contact`');
        chef_add_column($pdo, 'feedbacks', 'language', "VARCHAR(5) CHARACTER SET ascii COLLATE ascii_bin DEFAULT 'tr' AFTER `image_path`");
        chef_add_column($pdo, 'feedbacks', 'created_at', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER `language`');
        $pdo->exec('ALTER TABLE feedbacks MODIFY customer_name VARCHAR(120) COLLATE utf8mb4_turkish_ci DEFAULT NULL');
        $pdo->exec('ALTER TABLE feedbacks MODIFY branch_id INT DEFAULT NULL');
        chef_add_index($pdo, 'feedbacks', 'idx_feedbacks_branch', '`branch_id`');

        chef_add_foreign_key($pdo, 'products', 'category_id', 'categories', 'id', 'fk_products_category');
        chef_add_foreign_key($pdo, 'feedbacks', 'branch_id', 'branches', 'id', 'fk_feedbacks_branch');
    },
    'verify' => static function (PDO $pdo): void {
        $required = [
            'admins' => ['id', 'username', 'password_hash'],
            'branches' => ['id', 'name', 'wifi_name', 'wifi_password'],
            'campaigns' => ['id', 'is_active', 'image_path'],
            'categories' => ['id', 'public_id', 'name', 'name_en', 'name_ar', 'sort_order'],
            'products' => ['id', 'public_id', 'category_id', 'name', 'price', 'ingredients', 'calories', 'ingredients_verified_at', 'calories_verified_at'],
            'feedbacks' => ['id', 'customer_name', 'rating', 'comment', 'branch_id', 'topic', 'contact', 'image_path', 'language'],
            'meta' => ['meta_key', 'meta_value'],
        ];
        foreach ($required as $table => $columns) {
            if (!chef_table_exists($pdo, $table)) {
                throw new RuntimeException("Required table is missing: {$table}.");
            }
            foreach ($columns as $column) {
                if (!chef_column_exists($pdo, $table, $column)) {
                    throw new RuntimeException("Required column is missing: {$table}.{$column}.");
                }
            }
        }
        if (!chef_foreign_key_exists($pdo, 'products', 'category_id', 'categories', 'id')) {
            throw new RuntimeException('The product category foreign key is missing.');
        }
        if (!chef_foreign_key_exists($pdo, 'feedbacks', 'branch_id', 'branches', 'id')) {
            throw new RuntimeException('The feedback branch foreign key is missing.');
        }
    },
];
