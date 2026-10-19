<?php
require_once __DIR__ . '/functions.php';
require_login();
chef_require_method(['GET', 'POST']);

$results = is_array($_SESSION['product_import_results'] ?? null) ? $_SESSION['product_import_results'] : null;
unset($_SESSION['product_import_results']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        flash_message('error', 'Geçersiz oturum.');
        header('Location: import-products.php');
        exit;
    }
    if (!chef_consume_submission_token((string)($_POST['submission_token'] ?? ''))) {
        flash_message('error', chef_submission_token_error());
        header('Location: import-products.php', true, 303);
        exit;
    }

    try {
        [$tmp, $extension] = chef_validate_import_upload($_FILES['file'] ?? [], ['csv', 'json']);
        $supportedFields = ['category_name', 'product_name', 'description', 'price', 'image_url', 'sort_order'];
        $dataRows = [];
        if ($extension === 'csv') {
            $handle = fopen($tmp, 'r');
            if ($handle === false) throw new DomainException('CSV dosyası okunamadı.');
            $line = 0;
            try {
                while (($row = fgetcsv($handle, 16384, ',')) !== false) {
                    $line++;
                    if ($line === 1 && isset($row[0])) $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$row[0]);
                    if (count($row) === 1 && trim((string)$row[0]) === '') continue;
                    $normalizedHeader = array_map(static fn($value) => strtolower(trim((string)$value)), $row);
                    if (!$dataRows && $normalizedHeader === $supportedFields) continue;
                    if (count($row) !== count($supportedFields)) {
                        $dataRows[] = ['_line' => $line, '_error' => 'Tam olarak 6 CSV kolonu gerekir.'];
                    } else {
                        $dataRows[] = array_merge(array_combine($supportedFields, $row), ['_line' => $line]);
                    }
                    if (count($dataRows) > chef_import_limits()['rows']) {
                        throw new DomainException('Dosya en fazla 500 veri satırı içerebilir.');
                    }
                }
            } finally {
                fclose($handle);
            }
        } else {
            try {
                $decoded = json_decode((string)file_get_contents($tmp), true, 4, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new DomainException('JSON geçersiz veya dört düzeyden daha derin.');
            }
            if (!is_array($decoded) || !array_is_list($decoded)) {
                throw new DomainException('JSON üst düzeyde bir ürün listesi olmalıdır.');
            }
            if (count($decoded) > chef_import_limits()['rows']) {
                throw new DomainException('Dosya en fazla 500 veri satırı içerebilir.');
            }
            foreach ($decoded as $index => $row) {
                $line = $index + 1;
                if (!is_array($row) || array_is_list($row)) {
                    $dataRows[] = ['_line' => $line, '_error' => 'JSON satırı bir alan nesnesi olmalıdır.'];
                    continue;
                }
                $unknown = array_diff(array_keys($row), $supportedFields);
                $nested = array_filter($row, static fn($value) => !is_scalar($value) && $value !== null);
                if ($unknown || $nested) {
                    $dataRows[] = ['_line' => $line, '_error' => 'JSON yalnızca belgelenen düz metin alanlarını içerebilir.'];
                    continue;
                }
                $dataRows[] = array_merge(array_fill_keys($supportedFields, ''), $row, ['_line' => $line]);
            }
        }

        if (!$dataRows) throw new DomainException('Dosyada ürün satırı bulunamadı.');
        $validRows = [];
        $errors = [];
        foreach ($dataRows as $row) {
            $line = (int)($row['_line'] ?? 0);
            if (isset($row['_error'])) {
                $errors[] = 'Satır ' . $line . ': ' . $row['_error'];
                continue;
            }
            $rowErrors = [];
            $categoryName = chef_validate_required_text($row['category_name'] ?? '', 150, 'Kategori adı', $rowErrors, 'category_name');
            $productName = chef_validate_required_text($row['product_name'] ?? '', 200, 'Ürün adı', $rowErrors, 'product_name');
            $description = chef_validate_optional_text($row['description'] ?? '', 5000, 'Açıklama', $rowErrors, 'description');
            $price = trim((string)($row['price'] ?? '')) === '' ? '0.00' : parse_price_input((string)$row['price']);
            if ($price === null) $rowErrors['price'] = 'Fiyat biçimi geçersiz veya belirsiz.';
            $imageUrl = trim((string)($row['image_url'] ?? ''));
            if ($imageUrl !== '') $rowErrors['image_url'] = 'image_url desteklenmiyor. Görseli ürün formundan yükleyin.';
            $sortRaw = trim((string)($row['sort_order'] ?? ''));
            $sortOrder = $sortRaw === '' ? null : chef_canonical_id($sortRaw);
            if ($sortRaw !== '' && ($sortOrder === null || $sortOrder > 2147483647)) {
                $rowErrors['sort_order'] = 'Sıra pozitif bir tam sayı olmalıdır.';
            }
            if ($rowErrors) {
                $errors[] = 'Satır ' . $line . ': ' . implode(' ', array_values($rowErrors));
                continue;
            }
            $validRows[] = compact('categoryName', 'productName', 'description', 'price', 'sortOrder');
        }

        if ($errors) {
            $_SESSION['product_import_results'] = [
                'read' => count($dataRows), 'applied' => 0, 'rejected' => count($dataRows),
                'errors' => array_slice($errors, 0, 20), 'createdCategories' => [],
            ];
            flash_message('error', sprintf(
                'İçe aktarma uygulanmadı: 0 uygulandı, %d satır reddedildi; %d doğrulama hatası bulundu.',
                count($dataRows),
                count($errors)
            ));
            header('Location: import-products.php', true, 303);
            exit;
        }

        $pdo->beginTransaction();
        $findCategory = $pdo->prepare('SELECT id FROM categories WHERE name = :name LIMIT 1');
        $createCategory = $pdo->prepare('INSERT INTO categories (name, description) VALUES (:name, NULL)');
        $insertProduct = $pdo->prepare('INSERT INTO products (category_id, name, description, price, sort_order, image_path, created_at) VALUES (:category, :name, :description, :price, :sort_order, NULL, NOW())');
        $categoryIds = [];
        $createdCategories = [];
        foreach ($validRows as $row) {
            $categoryKey = mb_strtolower($row['categoryName'], 'UTF-8');
            if (!isset($categoryIds[$categoryKey])) {
                $findCategory->execute([':name' => $row['categoryName']]);
                $categoryId = $findCategory->fetchColumn();
                if (!$categoryId) {
                    $createCategory->execute([':name' => $row['categoryName']]);
                    $categoryId = (int)$pdo->lastInsertId();
                    $createdCategories[] = $row['categoryName'];
                }
                $categoryIds[$categoryKey] = (int)$categoryId;
            }
            $categoryId = $categoryIds[$categoryKey];
            $insertProduct->execute([
                ':category' => $categoryId,
                ':name' => $row['productName'],
                ':description' => $row['description'],
                ':price' => $row['price'],
                ':sort_order' => $row['sortOrder'] ?? next_product_sort_order($pdo, $categoryId),
            ]);
        }
        bump_menu_version($pdo);
        $pdo->commit();
        $_SESSION['product_import_results'] = [
            'read' => count($dataRows), 'applied' => count($validRows), 'rejected' => 0,
            'errors' => [], 'createdCategories' => $createdCategories,
        ];
        flash_message('success', sprintf('İçe aktarma tamamlandı: %d uygulandı, 0 reddedildi.', count($validRows)));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['product_import_results'] = [
            'read' => 0, 'applied' => 0, 'rejected' => 0,
            'errors' => [$e instanceof DomainException ? $e->getMessage() : 'İçe aktarma tamamlanamadı.'],
            'createdCategories' => [],
        ];
        flash_message('error', $e instanceof DomainException ? $e->getMessage() : chef_admin_error('product_import_failed', $e));
    }

    header('Location: import-products.php', true, 303);
    exit;
}
require_once __DIR__ . '/header.php';
?>
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-serif text-hvk-accent tracking-[0.12em]">Toplu Ürün Yükle</h1>
        <p class="text-sm text-hvk-muted">CSV veya JSON ile hızlı ürün aktarımı yapın.</p>
    </div>

    <form method="POST" enctype="multipart/form-data" class="admin-panel rounded-xl p-6 space-y-6" data-mutation-form data-testid="product-import-form">
        <input type="hidden" name="csrf_token" value="<?php echo sanitize($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
        <div class="space-y-2">
            <label for="product-import-file" class="text-sm text-hvk-muted">Dosya seç (.csv veya .json)</label>
            <input id="product-import-file" type="file" name="file" accept=".csv,.json" class="block w-full rounded-lg p-3 text-sm" required data-testid="product-import-file">
        </div>
        <div class="text-xs text-hvk-muted space-y-1">
            <p>CSV kolonları: category_name, product_name, description, price, image_url, sort_order</p>
            <p>En fazla 2 MB ve 500 satır. JSON, aynı alanları içeren düz bir liste olmalıdır.</p>
            <p>Aktarım atomiktir. Hatalı satır varsa hiçbir ürün eklenmez.</p>
            <p>image_url desteklenmez. Görselleri ürün formundan yükleyin.</p>
        </div>
        <button type="submit" class="admin-button admin-button-primary" data-testid="product-import-save">İçe aktar</button>
    </form>

    <?php if ($results): ?>
        <div class="bg-black/50 border border-hvk-accent/20 rounded-xl p-4 space-y-2">
            <p class="text-sm text-hvk-text">Okunan satır: <?php echo $results['read']; ?></p>
            <p class="text-sm text-green-300">Uygulanan: <?php echo $results['applied']; ?></p>
            <p class="text-sm text-red-200">Reddedilen: <?php echo $results['rejected']; ?></p>
            <p class="text-sm text-hvk-muted">Yeni kategoriler: <?php echo implode(', ', $results['createdCategories']); ?></p>
            <?php if (!empty($results['errors'])): ?>
                <div class="text-sm text-red-200 space-y-1">
                    <?php foreach ($results['errors'] as $error): ?>
                        <div>• <?php echo sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
