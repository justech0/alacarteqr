<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/functions.php';
require_login();

$chefLogoUrl = $LOGO_URL;
$current = basename($_SERVER['PHP_SELF']);
$submissionToken = chef_submission_token();
$navGroups = [
    'Katalog' => [
        ['href' => 'index.php', 'label' => 'Özet'],
        ['href' => 'categories.php', 'label' => 'Kategoriler'],
        ['href' => 'category-order.php', 'label' => 'Kategori sırası'],
        ['href' => 'products.php', 'label' => 'Ürünler'],
        ['href' => 'product-order.php', 'label' => 'Ürün sırası'],
        ['href' => 'bulk-prices.php', 'label' => 'Toplu fiyatlar'],
        ['href' => 'import-products.php', 'label' => 'Ürün aktarımı'],
    ],
    'Mekân' => [
        ['href' => 'branches.php', 'label' => 'WiFi ayarları'],
    ],
    'Kampanya' => [
        ['href' => 'campaign.php', 'label' => 'Kampanya görseli'],
    ],
    'Geri bildirim' => [
        ['href' => 'feedbacks.php', 'label' => 'Müşteri yorumları'],
    ],
];
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <title>Chef À La Carte | Yönetim</title>
    <link rel="icon" href="assets/chef-logo.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/admin.css">
    <script src="assets/admin.js" defer></script>
</head>
<body class="admin-shell min-h-screen overflow-x-hidden">
<div class="min-h-screen lg:grid lg:grid-cols-[17rem_minmax(0,1fr)]">
    <aside class="admin-panel hidden min-h-screen border-y-0 border-l-0 lg:flex lg:flex-col lg:sticky lg:top-0 lg:h-screen">
        <div class="flex items-center gap-3 border-b p-5">
            <span class="logo-badge flex h-14 w-14 shrink-0 items-center justify-center p-1">
                <img src="<?php echo sanitize($chefLogoUrl); ?>" alt="Chef À La Carte" class="h-12 w-12 object-contain">
            </span>
            <div>
                <p class="font-serif text-lg leading-tight">Chef À La Carte</p>
                <p class="admin-muted text-xs">Yönetim paneli</p>
            </div>
        </div>
        <nav class="flex-1 space-y-5 overflow-y-auto p-4" aria-label="Yönetim bölümleri">
            <?php foreach ($navGroups as $group => $items): ?>
                <section aria-labelledby="nav-<?php echo sanitize(strtolower(str_replace(['â', 'ı', 'ş', 'ğ', 'ü', 'ö', 'ç', ' '], ['a', 'i', 's', 'g', 'u', 'o', 'c', '-'], $group))); ?>">
                    <h2 id="nav-<?php echo sanitize(strtolower(str_replace(['â', 'ı', 'ş', 'ğ', 'ü', 'ö', 'ç', ' '], ['a', 'i', 's', 'g', 'u', 'o', 'c', '-'], $group))); ?>" class="admin-muted mb-1 px-3 text-xs font-semibold"><?php echo sanitize($group); ?></h2>
                    <div class="space-y-1">
                        <?php foreach ($items as $item): ?>
                            <a href="<?php echo sanitize($item['href']); ?>" class="nav-link" <?php echo $current === $item['href'] ? 'aria-current="page"' : ''; ?>><?php echo sanitize($item['label']); ?></a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </nav>
        <div class="border-t p-4">
            <form method="POST" action="logout.php" data-mutation-form data-testid="logout-form">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize(chef_csrf_token()); ?>">
                <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
                <button type="submit" class="admin-button admin-button-secondary w-full" data-testid="logout-save">Çıkış yap</button>
            </form>
        </div>
    </aside>

    <div class="min-w-0">
        <header class="admin-panel sticky top-0 z-20 flex min-h-20 items-center justify-between border-x-0 border-t-0 px-4 sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <span class="logo-badge flex h-12 w-12 shrink-0 items-center justify-center p-1 lg:hidden">
                    <img src="<?php echo sanitize($chefLogoUrl); ?>" alt="Chef À La Carte" class="h-10 w-10 object-contain">
                </span>
                <div class="min-w-0">
                    <h1 class="truncate text-xl">Chef yönetimi</h1>
                    <p class="admin-muted truncate text-xs">Katalog ve mekân ayarları</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="admin-button admin-button-secondary" data-theme-toggle aria-label="Renk temasını değiştir">Tema</button>
                <button type="button" class="admin-button admin-button-secondary lg:hidden" data-mobile-menu-open aria-label="Yönetim menüsünü aç">Menü</button>
            </div>
        </header>

        <main class="mx-auto w-full max-w-[96rem] p-4 sm:p-6">
            <div role="status" aria-live="polite" class="admin-status mb-5 rounded-lg px-4 py-3 text-sm" <?php echo $flash ? '' : 'hidden'; ?>><?php echo $flash ? sanitize((string)$flash['message']) : ''; ?></div>

            <dialog class="w-[min(92vw,22rem)] rounded-xl border p-0 shadow-2xl" data-mobile-menu>
                <div class="flex items-center justify-between border-b p-4">
                    <h2 class="text-lg">Yönetim menüsü</h2>
                    <button type="button" class="admin-button admin-button-secondary" data-mobile-menu-close aria-label="Yönetim menüsünü kapat">Kapat</button>
                </div>
                <nav class="max-h-[70vh] space-y-5 overflow-y-auto p-4" aria-label="Mobil yönetim bölümleri">
                    <?php foreach ($navGroups as $group => $items): ?>
                        <section>
                            <h3 class="admin-muted mb-1 px-3 text-xs font-semibold"><?php echo sanitize($group); ?></h3>
                            <?php foreach ($items as $item): ?>
                                <a href="<?php echo sanitize($item['href']); ?>" class="nav-link" <?php echo $current === $item['href'] ? 'aria-current="page"' : ''; ?>><?php echo sanitize($item['label']); ?></a>
                            <?php endforeach; ?>
                        </section>
                    <?php endforeach; ?>
                </nav>
            </dialog>

            <dialog class="w-[min(92vw,30rem)] rounded-xl border p-0 shadow-2xl" data-testid="confirm-dialog" aria-labelledby="confirm-dialog-title">
                <div class="space-y-4 p-5">
                    <div>
                        <h2 id="confirm-dialog-title" class="text-xl">İşlemi onaylayın</h2>
                        <p class="mt-2 font-semibold" data-testid="confirm-dialog-name"></p>
                        <p class="admin-muted mt-1 text-sm" data-confirm-detail></p>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="admin-button admin-button-secondary" data-testid="confirm-dialog-cancel">Vazgeç</button>
                        <button type="button" class="admin-button admin-button-danger" data-testid="confirm-dialog-accept">Onayla</button>
                    </div>
                </div>
            </dialog>
