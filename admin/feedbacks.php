<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
require_login();
chef_require_method(['GET', 'POST']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf((string)($_POST['csrf_token'] ?? ''))) {
        flash_message('error', 'İstek doğrulanamadı.');
        header('Location: feedbacks.php', true, 303);
        exit;
    }
    if (!chef_consume_submission_token((string)($_POST['submission_token'] ?? ''))) {
        flash_message('error', chef_submission_token_error());
        header('Location: feedbacks.php', true, 303);
        exit;
    }

    if (($_POST['action'] ?? '') === 'retry_cleanup') {
        chef_media_cleanup($pdo);
        $pending = (int)$pdo->query("SELECT COUNT(*) FROM managed_uploads WHERE owner_type = 'feedback' AND state = 'cleanup_pending'")->fetchColumn();
        flash_message($pending === 0 ? 'success' : 'error', $pending === 0
            ? 'Görsel temizliği başarıyla tamamlandı.'
            : "Temizlik çalıştırıldı ancak {$pending} adet dosya hâlâ temizlenemedi.");
        header('Location: feedbacks.php', true, 303);
        exit;
    }

    $id = chef_canonical_id($_POST['id'] ?? '');
    try {
        if ($id === null) throw new DomainException('Geri bildirim kimliği geçersiz.');
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id FROM feedbacks WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $id]);
        if (!$stmt->fetchColumn()) {
            $pdo->commit();
            flash_message('success', 'Geri bildirim zaten silinmiş.');
            header('Location: feedbacks.php', true, 303);
            exit;
        }
        chef_media_mark_owner_deletion($pdo, 'feedback', $id);
        $pdo->prepare('DELETE FROM feedbacks WHERE id = :id')->execute([':id' => $id]);
        $pdo->commit();
        chef_media_cleanup($pdo);
        $remaining = chef_media_owner_files_remaining($pdo, 'feedback', $id);
        flash_message($remaining === 0 ? 'success' : 'error', $remaining === 0
            ? 'Geri bildirim ve ilişkili görsel silindi.'
            : 'Geri bildirim silindi. Görsel temizliği yeniden denenecek.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_message('error', $e instanceof DomainException ? $e->getMessage() : chef_admin_error('feedback_delete_failed', $e));
    }
    header('Location: feedbacks.php', true, 303);
    exit;
}

$topicFilter = trim(is_scalar($_GET['topic'] ?? null) ? (string)$_GET['topic'] : '');
$ratingFilter = trim(is_scalar($_GET['rating'] ?? null) ? (string)$_GET['rating'] : '');
$searchQuery = trim(is_scalar($_GET['search'] ?? null) ? (string)$_GET['search'] : '');
$page = max(1, is_scalar($_GET['page'] ?? null) ? (int)$_GET['page'] : 1);
// Pagination links repeat only the known filters, never arbitrary query keys.
$pageQuery = static fn(int $target): string => sanitize(http_build_query(array_filter(
    ['topic' => $topicFilter, 'rating' => $ratingFilter, 'search' => $searchQuery, 'page' => $target],
    static fn($value) => $value !== ''
)));
$limit = 12;

$conditions = [];
$params = [];

if ($topicFilter !== '' && in_array($topicFilter, ['taste', 'service', 'staff', 'hygiene', 'other'], true)) {
    $conditions[] = 'topic = :topic';
    $params[':topic'] = $topicFilter;
}
if ($ratingFilter !== '' && ctype_digit($ratingFilter) && (int)$ratingFilter >= 1 && (int)$ratingFilter <= 5) {
    $conditions[] = 'rating = :rating';
    $params[':rating'] = (int)$ratingFilter;
}
if ($searchQuery !== '') {
    $conditions[] = '(comment LIKE :search OR contact LIKE :search OR customer_name LIKE :search)';
    $params[':search'] = '%' . $searchQuery . '%';
}

$whereClause = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM feedbacks' . $whereClause);
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalCount / $limit));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $limit;

$listSql = 'SELECT * FROM feedbacks' . $whereClause . ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
$listStmt = $pdo->prepare($listSql);
foreach ($params as $key => $val) {
    $listStmt->bindValue($key, $val);
}
$listStmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$listStmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$listStmt->execute();
$feedbacks = $listStmt->fetchAll();

$pendingCleanupCount = (int)$pdo->query("SELECT COUNT(*) FROM managed_uploads WHERE owner_type = 'feedback' AND state = 'cleanup_pending'")->fetchColumn();

$topicLabels = ['taste' => 'Lezzet ve kalite', 'service' => 'Hizmet hızı', 'staff' => 'Personel', 'hygiene' => 'Hijyen', 'other' => 'Diğer'];
include __DIR__ . '/header.php';
?>
<section class="space-y-4" aria-labelledby="feedback-title">
    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
            <h2 id="feedback-title" class="text-2xl">Geri bildirimler</h2>
            <p class="admin-muted text-sm">Müşteri puanlarını, yorumlarını ve yüklenen görselleri inceleyin.</p>
        </div>
        <form method="GET" class="flex flex-wrap items-end gap-2" data-testid="feedback-filter-form">
            <div>
                <label for="feedback-topic-filter" class="admin-muted block text-xs">Konu</label>
                <select id="feedback-topic-filter" name="topic" class="rounded-lg px-3 py-2 text-sm">
                    <option value="">Tümü</option>
                    <?php foreach ($topicLabels as $key => $lbl): ?>
                        <option value="<?php echo sanitize($key); ?>"<?php echo $topicFilter === $key ? ' selected' : ''; ?>><?php echo sanitize($lbl); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="feedback-rating-filter" class="admin-muted block text-xs">Puan</label>
                <select id="feedback-rating-filter" name="rating" class="rounded-lg px-3 py-2 text-sm">
                    <option value="">Tümü</option>
                    <?php for ($r = 5; $r >= 1; $r--): ?>
                        <option value="<?php echo $r; ?>"<?php echo $ratingFilter === (string)$r ? ' selected' : ''; ?>><?php echo $r; ?> Yıldız</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label for="feedback-search" class="admin-muted block text-xs">Ara</label>
                <input id="feedback-search" name="search" value="<?php echo sanitize($searchQuery); ?>" placeholder="Yorum veya iletişim..." class="rounded-lg px-3 py-2 text-sm">
            </div>
            <button type="submit" class="admin-button admin-button-secondary text-sm">Filtrele</button>
            <?php if ($topicFilter !== '' || $ratingFilter !== '' || $searchQuery !== ''): ?>
                <a href="feedbacks.php" class="admin-muted self-center text-xs hover:underline">Temizle</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($pendingCleanupCount > 0): ?>
        <div class="admin-panel flex flex-col gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm sm:flex-row sm:items-center sm:justify-between" data-testid="pending-cleanup-alert">
            <div>
                <p class="font-semibold text-amber-500">Tamamlanmamış Görsel Temizliği</p>
                <p class="admin-muted text-xs">Silinmesi bekleyen <?php echo $pendingCleanupCount; ?> adet görsel dosyası var.</p>
            </div>
            <form method="POST" data-mutation-form>
                <input type="hidden" name="csrf_token" value="<?php echo sanitize(chef_csrf_token()); ?>">
                <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
                <input type="hidden" name="action" value="retry_cleanup">
                <button type="submit" class="admin-button admin-button-secondary text-xs" data-testid="retry-cleanup-button">Temizliği Yeniden Dene</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if (!$feedbacks): ?>
        <p class="admin-panel rounded-xl p-5 text-sm" data-testid="no-feedbacks">Geri bildirim bulunamadı.</p>
    <?php else: ?>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($feedbacks as $feedback):
                $imageUrl = chef_media_image_url($feedback['image_path'] ?? null);
                $name = trim((string)($feedback['customer_name'] ?? '')) ?: 'İsimsiz müşteri';
                $testId = (int)$feedback['id'];
            ?>
                <article class="admin-panel flex flex-col gap-3 rounded-xl p-4" data-testid="feedback-row-<?php echo $testId; ?>">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg"><?php echo sanitize($name); ?></h3>
                            <p class="admin-muted text-xs"><?php echo date('d.m.Y H:i', strtotime($feedback['created_at'])); ?></p>
                        </div>
                        <p class="font-semibold"><?php echo (int)$feedback['rating']; ?>/5</p>
                    </div>
                    <?php if (!empty($feedback['topic'])): ?><p class="admin-muted text-xs">Konu: <?php echo sanitize($topicLabels[$feedback['topic']] ?? $feedback['topic']); ?></p><?php endif; ?>
                    <?php if (!empty($feedback['contact'])): ?><p class="admin-muted text-xs">İletişim: <?php echo sanitize($feedback['contact']); ?></p><?php endif; ?>
                    <p class="admin-muted line-clamp-4 flex-1 border-t pt-3 text-sm"><?php echo sanitize((string)($feedback['comment'] ?? '')); ?></p>
                    <?php if ($imageUrl): ?><img src="<?php echo sanitize($imageUrl); ?>" alt="<?php echo sanitize($name); ?> geri bildirim görseli" class="h-24 w-full rounded-lg object-cover" data-testid="feedback-image-<?php echo $testId; ?>"><?php endif; ?>
                    <div class="flex gap-2">
                        <button type="button" class="admin-button admin-button-secondary flex-1" data-feedback-open data-feedback-comment="<?php echo sanitize((string)($feedback['comment'] ?? '')); ?>" data-feedback-image="<?php echo $imageUrl ? sanitize($imageUrl) : ''; ?>" data-testid="feedback-view-<?php echo $testId; ?>">Görüntüle</button>
                        <form method="POST" class="flex-1" data-mutation-form data-confirm-name="<?php echo sanitize($name); ?>" data-confirm-detail="Geri bildirim kaydı ve ilişkili görsel silinir." data-testid="feedback-delete-form-<?php echo $testId; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize(chef_csrf_token()); ?>">
                            <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
                            <input type="hidden" name="id" value="<?php echo $testId; ?>">
                            <button type="submit" class="admin-button admin-button-danger w-full" data-testid="feedback-delete-<?php echo $testId; ?>">Sil</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="flex items-center justify-between border-t pt-4 text-sm" aria-label="Sayfalama">
                <div>
                    <span class="admin-muted text-xs">Toplam <?php echo $totalCount; ?> kayıt (Sayfa <?php echo $page; ?> / <?php echo $totalPages; ?>)</span>
                </div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="feedbacks.php?<?php echo $pageQuery($page - 1); ?>" class="admin-button admin-button-secondary text-xs">Önceki</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="feedbacks.php?<?php echo $pageQuery($page + 1); ?>" class="admin-button admin-button-secondary text-xs">Sonraki</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
<dialog class="w-[min(92vw,38rem)] rounded-xl border p-0 shadow-2xl" data-feedback-dialog aria-labelledby="feedback-dialog-title">
    <div class="space-y-4 p-5">
        <div class="flex items-center justify-between gap-3"><h2 id="feedback-dialog-title" class="text-xl">Geri bildirim</h2><button type="button" class="admin-button admin-button-secondary" data-feedback-close>Kapat</button></div>
        <p class="whitespace-pre-wrap text-sm" data-feedback-comment></p>
        <img src="" alt="Geri bildirim görseli" class="max-h-[60vh] w-full rounded-lg object-contain" data-feedback-image hidden>
    </div>
</dialog>
<?php include __DIR__ . '/footer.php'; ?>
