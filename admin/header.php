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

// Few top-level sections. Each section shows its pages as tabs.
$sections = [
    'overview' => ['label' => 'Özet', 'icon' => 'home', 'tabs' => [
        ['href' => 'index.php', 'label' => 'Özet'],
    ]],
    'menu' => ['label' => 'Menü', 'icon' => 'menu', 'tabs' => [
        ['href' => 'products.php', 'label' => 'Ürünler'],
        ['href' => 'product-order.php', 'label' => 'Ürün sırası'],
        ['href' => 'categories.php', 'label' => 'Kategoriler'],
        ['href' => 'category-order.php', 'label' => 'Kategori sırası'],
    ]],
    'prices' => ['label' => 'Fiyatlar', 'icon' => 'tag', 'tabs' => [
        ['href' => 'bulk-prices.php', 'label' => 'Toplu fiyat'],
        ['href' => 'import-products.php', 'label' => 'Ürün aktarımı'],
    ]],
    'venue' => ['label' => 'Mekân', 'icon' => 'store', 'tabs' => [
        ['href' => 'branches.php', 'label' => 'WiFi'],
        ['href' => 'campaign.php', 'label' => 'Kampanya'],
    ]],
    'feedback' => ['label' => 'Yorumlar', 'icon' => 'chat', 'tabs' => [
        ['href' => 'feedbacks.php', 'label' => 'Müşteri yorumları'],
    ]],
];
$activeSection = 'overview';
foreach ($sections as $key => $section) {
    foreach ($section['tabs'] as $tab) {
        if ($tab['href'] === $current) $activeSection = $key;
    }
}
$icons = [
    'home' => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
    'menu' => '<path d="M4 6h16M4 12h16M4 18h10"/>',
    'tag' => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="8" cy="8" r="1.5"/>',
    'store' => '<path d="M4 9h16l-1-5H5zM5 9v11h14V9M9 20v-6h6v6"/>',
    'chat' => '<path d="M4 5h16v11H8l-4 4z"/>',
    'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
    'moon' => '<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/>',
    'logout' => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/>',
];
$icon = static fn(string $name): string => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icons[$name] . '</svg>';
$flash = get_flash();
$pageTitle = $sections[$activeSection]['label'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <title><?php echo sanitize($pageTitle); ?> | Chef yönetimi</title>
    <link rel="icon" href="assets/chef-logo.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/admin.css?v=<?php echo (int)@filemtime(__DIR__ . '/assets/admin.css'); ?>">
    <script src="assets/admin.js?v=<?php echo (int)@filemtime(__DIR__ . '/assets/admin.js'); ?>" defer></script>
</head>
<body class="admin-shell min-h-screen overflow-x-hidden">
<div class="admin-progress" data-admin-progress hidden></div>
<div class="min-h-screen lg:grid lg:grid-cols-[15rem_minmax(0,1fr)]">
    <aside class="admin-sidebar hidden lg:flex lg:flex-col lg:sticky lg:top-0 lg:h-screen">
        <a href="index.php" class="flex items-center gap-3 px-5 py-5 no-underline">
            <span class="logo-badge flex h-12 w-12 shrink-0 items-center justify-center p-1">
                <img src="<?php echo sanitize($chefLogoUrl); ?>" alt="" class="h-10 w-10 object-contain">
            </span>
            <span>
                <span class="block font-serif text-base leading-tight admin-text">Chef À La Carte</span>
                <span class="admin-muted block text-xs">Yönetim</span>
            </span>
        </a>
        <nav class="flex-1 space-y-1 px-3" aria-label="Yönetim bölümleri">
            <?php foreach ($sections as $key => $section): ?>
                <a href="<?php echo sanitize($section['tabs'][0]['href']); ?>" class="nav-link" data-section="<?php echo sanitize($key); ?>" <?php echo $activeSection === $key ? 'aria-current="page"' : ''; ?>>
                    <?php echo $icon($section['icon']); ?><span><?php echo sanitize($section['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="p-3">
            <form method="POST" action="logout.php" data-mutation-form data-no-swap data-testid="logout-form">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize(chef_csrf_token()); ?>">
                <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
                <button type="submit" class="nav-link w-full" data-testid="logout-save"><?php echo $icon('logout'); ?><span>Çıkış yap</span></button>
            </form>
        </div>
    </aside>

    <div class="min-w-0 pb-24 lg:pb-0">
        <header class="admin-topbar sticky top-0 z-20 flex h-16 items-center justify-between gap-3 px-4 sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <span class="logo-badge flex h-10 w-10 shrink-0 items-center justify-center p-1 lg:hidden">
                    <img src="<?php echo sanitize($chefLogoUrl); ?>" alt="Chef À La Carte" class="h-8 w-8 object-contain">
                </span>
                <h1 class="truncate text-xl" data-page-title><?php echo sanitize($pageTitle); ?></h1>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="icon-button" data-theme-toggle aria-label="Koyu temaya geç">
                    <span class="theme-icon-moon"><?php echo $icon('moon'); ?></span>
                    <span class="theme-icon-sun"><?php echo $icon('sun'); ?></span>
                </button>
                <form method="POST" action="logout.php" class="lg:hidden" data-mutation-form data-no-swap>
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize(chef_csrf_token()); ?>">
                    <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
                    <button type="submit" class="icon-button" aria-label="Çıkış yap"><?php echo $icon('logout'); ?></button>
                </form>
            </div>
        </header>

        <main class="mx-auto w-full max-w-[90rem] p-4 sm:p-6" data-admin-main>
            <?php if (count($sections[$activeSection]['tabs']) > 1): ?>
                <nav class="admin-tabs mb-5" aria-label="<?php echo sanitize($pageTitle); ?> sekmeleri">
                    <?php foreach ($sections[$activeSection]['tabs'] as $tab): ?>
                        <a href="<?php echo sanitize($tab['href']); ?>" class="admin-tab" <?php echo $current === $tab['href'] ? 'aria-current="page"' : ''; ?>><?php echo sanitize($tab['label']); ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
            <div role="status" aria-live="polite" class="admin-status mb-5 rounded-lg px-4 py-3 text-sm<?php echo ($flash['type'] ?? '') === 'error' ? ' is-error' : ''; ?>" data-admin-flash <?php echo $flash ? '' : 'hidden'; ?>><?php echo $flash ? sanitize((string)$flash['message']) : ''; ?></div>

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
