<?php
require_once __DIR__ . '/functions.php';
require_login();
chef_require_method(['GET', 'POST']);

$selectedCategory = chef_canonical_id($_GET['category'] ?? '') ?? 0;
$updated = $unchanged = $invalid = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        flash_message('error', 'Geçersiz istek. Lütfen tekrar deneyin.');
        header('Location: bulk-prices.php' . ($selectedCategory ? '?category=' . $selectedCategory : ''));
        exit;
    }

    $action = $_POST['action'] ?? 'bulk_update_prices';
    if ($action !== 'export_csv' && !chef_consume_submission_token((string)($_POST['submission_token'] ?? ''))) {
        flash_message('error', chef_submission_token_error());
        header('Location: bulk-prices.php' . ($selectedCategory ? '?category=' . $selectedCategory : ''), true, 303);
        exit;
    }

    try {
        if ($action === 'reset_all_prices') {
            $pdo->beginTransaction();
            $resetStmt = $pdo->prepare('UPDATE products SET price = 0.00 WHERE price <> 0.00');
            $resetStmt->execute();
            $resetCount = $resetStmt->rowCount();
            if ($resetCount > 0) {
                bump_menu_version($pdo);
            }
            $pdo->commit();

            if ($resetCount > 0) {
                flash_message('success', sprintf('%d ürünün fiyatı sıfırlandı.', $resetCount));
            } else {
                flash_message('success', 'Tüm fiyatlar zaten sıfır.');
            }
        } elseif ($action === 'bulk_update_prices') {
            $prices = $_POST['price'] ?? [];
            if (!is_array($prices)) {
                $prices = [];
            }

            $pdo->beginTransaction();
            $selectStmt = $pdo->prepare('SELECT price FROM products WHERE id = :id LIMIT 1');
            $updateStmt = $pdo->prepare('UPDATE products SET price = :price WHERE id = :id LIMIT 1');
            $changed = false;

            foreach ($prices as $productId => $rawPrice) {
                $id = chef_canonical_id($productId);
                $raw = trim((string)$rawPrice);

                if ($id === null) {
                    $invalid++;
                    continue;
                }

                if ($raw === '') {
                    $unchanged++;
                    continue;
                }

                $parsed = parse_price_input($raw);
                if ($parsed === null) {
                    $invalid++;
                    continue;
                }

                $selectStmt->execute([':id' => $id]);
                $current = $selectStmt->fetch();
                if (!$current) {
                    $invalid++;
                    continue;
                }

                $currentPrice = number_format((float)$current['price'], 2, '.', '');
                if ($currentPrice === $parsed) {
                    $unchanged++;
                    continue;
                }

                $updateStmt->execute([
                    ':price' => $parsed,
                    ':id' => $id,
                ]);
                $updated++;
                $changed = true;
            }

            if ($changed) {
                bump_menu_version($pdo);
            }
            $pdo->commit();

            $message = sprintf(
                '%d ürün güncellendi, %d ürün değişmedi, %d satır atlandı.',
                $updated,
                $unchanged,
                $invalid
            );
            flash_message('success', $message);
        } elseif ($action === 'export_csv') {
            $stmt = $pdo->prepare('SELECT id, name, price FROM products ORDER BY id ASC');
            $stmt->execute();
            $allProducts = $stmt->fetchAll();

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=fiyatlar_' . date('Y-m-d') . '.csv');
            
            $output = fopen('php://output', 'w');
            // Adding UTF-8 BOM for Excel compatibility
            fputs($output, "\xEF\xBB\xBF");
            fputcsv($output, ['ID', 'Urun_Adi', 'Fiyat']);
            
            foreach ($allProducts as $p) {
                fputcsv($output, [
                    $p['id'],
                    chef_spreadsheet_text((string)$p['name']),
                    number_format((float)$p['price'], 2, '.', '')
                ]);
            }
            fclose($output);
            exit;
        } elseif ($action === 'import_csv_prices') {
            [$tmp] = chef_validate_import_upload($_FILES['csv_file'] ?? [], ['csv']);
            $handle = fopen($tmp, 'r');
            if ($handle === false) throw new DomainException('CSV dosyası okunamadı.');
            $rows = [];
            $errors = [];
            $seenIds = [];
            $line = 0;
            $readRows = 0;
            try {
                while (($row = fgetcsv($handle, 16384, ',')) !== false) {
                    $line++;
                    if ($line === 1 && isset($row[0])) $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$row[0]);
                    if (count($row) === 1 && trim((string)$row[0]) === '') continue;
                    $idRaw = trim((string)($row[0] ?? ''));
                    if ($readRows === 0 && !preg_match('/^[0-9]+$/D', $idRaw)) {
                        $header = array_map(static fn($value) => strtolower(trim((string)$value)), $row);
                        if (in_array($header, [['id', 'fiyat'], ['id', 'price'], ['id', 'urun_adi', 'fiyat'], ['id', 'name', 'price']], true)) {
                            continue;
                        }
                    }
                    $readRows++;
                    if ($readRows > chef_import_limits()['rows']) {
                        throw new DomainException('Dosya en fazla 500 veri satırı içerebilir.');
                    }
                    if (count($row) !== 2 && count($row) !== 3) {
                        $errors[] = 'Satır ' . $line . ': 2 veya 3 kolon gerekir.';
                        continue;
                    }
                    $id = chef_canonical_id($idRaw);
                    $price = parse_price_input(trim((string)$row[count($row) - 1]));
                    if ($id === null || $price === null) {
                        $errors[] = 'Satır ' . $line . ': ürün kimliği veya fiyat geçersiz ya da belirsiz.';
                        continue;
                    }
                    if (isset($seenIds[$id])) {
                        $errors[] = 'Satır ' . $line . ': aynı ürün kimliği birden fazla kez kullanıldı.';
                        continue;
                    }
                    $seenIds[$id] = true;
                    $rows[] = ['id' => $id, 'price' => $price, 'line' => $line];
                }
            } finally {
                fclose($handle);
            }
            if (!$rows && !$errors) throw new DomainException('CSV dosyasında fiyat satırı bulunamadı.');
            if ($errors) {
                flash_message('error', sprintf('CSV uygulanmadı: 0 uygulandı, %d satır reddedildi. %s', $readRows, $errors[0]));
            } else {
                $pdo->beginTransaction();
                $selectStmt = $pdo->prepare('SELECT price FROM products WHERE id = :id LIMIT 1 FOR UPDATE');
                $updateStmt = $pdo->prepare('UPDATE products SET price = :price WHERE id = :id LIMIT 1');
                foreach ($rows as $row) {
                    $selectStmt->execute([':id' => $row['id']]);
                    $current = $selectStmt->fetch();
                    if (!$current) {
                        $errors[] = 'Satır ' . $row['line'] . ': ürün bulunamadı.';
                        continue;
                    }
                    if (number_format((float)$current['price'], 2, '.', '') === $row['price']) {
                        $unchanged++;
                    }
                }
                if ($errors) {
                    $pdo->rollBack();
                    flash_message('error', sprintf('CSV uygulanmadı: 0 uygulandı, %d satır reddedildi. %s', $readRows, $errors[0]));
                } else {
                    foreach ($rows as $row) {
                        $selectStmt->execute([':id' => $row['id']]);
                        $currentPrice = number_format((float)$selectStmt->fetchColumn(), 2, '.', '');
                        if ($currentPrice === $row['price']) continue;
                        $updateStmt->execute([':price' => $row['price'], ':id' => $row['id']]);
                        $updated++;
                    }
                    if ($updated > 0) bump_menu_version($pdo);
                    $pdo->commit();
                    flash_message('success', sprintf('CSV içe aktarıldı: %d uygulandı, %d değişmedi, 0 reddedildi.', $updated, $unchanged));
                }
            }
        } else {
            flash_message('error', 'Geçersiz işlem.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash_message('error', chef_admin_error('bulk_price_failed', $e));
    }

    header('Location: bulk-prices.php' . ($selectedCategory ? '?category=' . $selectedCategory : ''));
    exit;
}

$categoryStmt = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC');
$categories = $categoryStmt->fetchAll();

$params = [];
$sql = 'SELECT p.id, p.name, p.price, p.category_id, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id';

if ($selectedCategory > 0) {
    $sql .= ' WHERE p.category_id = :cat';
    $params[':cat'] = $selectedCategory;
}

$sql .= ' ORDER BY c.name ASC, p.id ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

include __DIR__ . '/header.php';
?>
<div class="space-y-6">
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h2 class="text-xl font-serif tracking-[0.18em] text-hvk-accent">Toplu Fiyat Güncelleme</h2>
            <p class="text-sm text-hvk-muted">Tüm ürünlerin fiyatlarını tek ekranda düzenleyin.</p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <form method="GET" class="flex items-center gap-3" data-testid="bulk-price-filter-form">
                <label for="bulk-price-category" class="text-sm text-hvk-muted">Kategori</label>
                <select id="bulk-price-category" name="category" class="bg-white/5 border border-hvk-accent/30 rounded-lg px-3 py-2 text-sm text-hvk-text focus:border-hvk-accent focus:ring-1 focus:ring-hvk-accent" data-testid="bulk-price-category">
                    <option value="0" <?php echo $selectedCategory === 0 ? 'selected' : ''; ?>>Tümü</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo (int)$cat['id']; ?>" <?php echo $selectedCategory === (int)$cat['id'] ? 'selected' : ''; ?>><?php echo sanitize($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="admin-button admin-button-secondary" type="submit" data-testid="bulk-price-filter-save">Filtrele</button>
            </form>
            <form method="POST" data-mutation-form data-confirm-name="Tüm ürün fiyatları" data-confirm-detail="Menüdeki tüm ürün fiyatları 0,00 ₺ olur." data-testid="bulk-price-reset-form">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
                <input type="hidden" name="action" value="reset_all_prices">
                <button type="submit" class="admin-button admin-button-danger" data-testid="bulk-price-reset">Tüm fiyatları sıfırla</button>
            </form>
        </div>
    </div>

    <!-- CSV Update Section -->
    <div class="glass rounded-2xl border border-hvk-accent/20 p-4 bg-black/50">
        <div class="flex flex-col md:flex-row gap-6 justify-between items-start md:items-center">
            <div class="text-sm text-hvk-muted space-y-2 flex-1">
                <h3 class="text-hvk-accent font-semibold mb-1">CSV ile Toplu Güncelleme</h3>
                <p>Ürün fiyatlarını CSV dosyası ile hızlıca güncelleyebilirsiniz. Örnek CSV formatı:</p>
                <pre class="bg-black/40 border border-hvk-accent/20 p-2 rounded text-xs text-hvk-text inline-block">ID, Urun_Adi, Fiyat
1, "Hamburger", 150.50
2, "Kola", 35.00</pre>
                <p class="text-xs">
                    Not: Sütunlar virgül (,) ile ayrılmalıdır. ID sütunu zorunludur. Fiyat formatı noktalı (150.50) veya virgüllü (150,50) olabilir. 
                    En iyi sonuç için önce mevcut fiyatları dışa aktarın, düzenleyip tekrar yükleyin.
                </p>
            </div>
            
            <div class="flex flex-col gap-3 min-w-[300px]">
                <form method="POST" class="w-full" data-testid="bulk-price-export-form">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="export_csv">
                    <button type="submit" class="admin-button admin-button-secondary w-full" data-testid="bulk-price-export">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        Mevcut Fiyatları İndir (CSV)
                    </button>
                </form>

                <form method="POST" enctype="multipart/form-data" class="flex items-center gap-2 w-full" data-mutation-form data-testid="bulk-price-import-form">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
                    <input type="hidden" name="action" value="import_csv_prices">
                    <label class="sr-only" for="bulk-price-file">Fiyat CSV dosyası</label>
                    <input id="bulk-price-file" type="file" name="csv_file" accept=".csv" required class="flex-1 w-full text-xs text-hvk-text bg-black/40 border border-hvk-accent/20 rounded-lg p-2 cursor-pointer" data-testid="bulk-price-file">
                    <button type="submit" class="admin-button admin-button-primary shrink-0" data-testid="bulk-price-import">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        Yükle
                    </button>
                </form>
            </div>
        </div>
    </div>

    <form method="POST" class="glass rounded-2xl border border-hvk-accent/20 p-4 bg-black/50 overflow-x-auto" data-mutation-form data-warn-unsaved data-testid="bulk-price-form">
        <input type="hidden" name="csrf_token" value="<?php echo sanitize($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
        <input type="hidden" name="action" value="bulk_update_prices">
        <table class="responsive-table min-w-full text-sm text-hvk-text">
            <thead>
                <tr class="text-left border-b border-hvk-accent/20">
                    <th class="py-3 px-2">ID</th>
                    <th class="py-3 px-2">Kategori</th>
                    <th class="py-3 px-2">Ürün</th>
                    <th class="py-3 px-2">Mevcut Fiyat</th>
                    <th class="py-3 px-2">Yeni Fiyat</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hvk-accent/10">
                <?php foreach ($products as $product): ?>
                    <?php $currentPrice = number_format((float)$product['price'], 2, '.', ''); ?>
                    <tr class="hover:bg-white/5">
                        <td data-label="ID" class="py-3 px-2 whitespace-nowrap text-hvk-muted">#<?php echo $product['id']; ?></td>
                        <td data-label="Kategori" class="py-3 px-2 whitespace-nowrap text-hvk-muted"><?php echo $product['category_name'] ? sanitize($product['category_name']) : 'Kategori yok'; ?></td>
                        <td data-label="Ürün" class="py-3 px-2 min-w-[180px] text-hvk-text"><?php echo sanitize($product['name']); ?></td>
                        <td data-label="Mevcut fiyat" class="py-3 px-2 whitespace-nowrap text-hvk-accent font-semibold"><?php echo $currentPrice; ?> ₺</td>
                        <td data-label="Yeni fiyat" class="py-3 px-2">
                            <label class="sr-only" for="price-<?php echo (int)$product['id']; ?>"><?php echo sanitize($product['name']); ?> yeni fiyatı</label>
                            <input
                                id="price-<?php echo (int)$product['id']; ?>"
                                type="text"
                                name="price[<?php echo $product['id']; ?>]"
                                value="<?php echo $currentPrice; ?>"
                                placeholder="<?php echo $currentPrice; ?>"
                                class="w-32 bg-white/5 border border-hvk-accent/30 rounded-lg px-3 py-2 text-sm focus:border-hvk-accent focus:ring-1 focus:ring-hvk-accent"
                                data-testid="bulk-price-<?php echo (int)$product['id']; ?>"
                            >
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="5" class="py-6 text-center text-hvk-muted">Bu filtre ile ürün bulunamadı.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="mt-4 flex items-center justify-end">
            <button type="submit" class="admin-button admin-button-primary" data-testid="bulk-price-save">Değişiklikleri kaydet</button>
        </div>
    </form>
</div>
<?php include __DIR__ . '/footer.php'; ?>
