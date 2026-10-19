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
    $id = chef_canonical_id($_POST['id'] ?? '');
    try {
        if ($id === null) throw new DomainException('Geri bildirim kimliği geçersiz.');
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id FROM feedbacks WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $id]);
        if (!$stmt->fetchColumn()) throw new DomainException('Geri bildirim bulunamadı.');
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

$feedbacks = $pdo->query('SELECT * FROM feedbacks ORDER BY created_at DESC')->fetchAll();
$topicLabels = ['taste' => 'Lezzet ve kalite', 'service' => 'Hizmet hızı', 'staff' => 'Personel', 'hygiene' => 'Hijyen', 'other' => 'Diğer'];
include __DIR__ . '/header.php';
?>
<section class="space-y-4" aria-labelledby="feedback-title">
    <div>
        <h2 id="feedback-title" class="text-2xl">Geri bildirimler</h2>
        <p class="admin-muted text-sm">Müşteri puanlarını, yorumlarını ve yüklenen görselleri inceleyin.</p>
    </div>
    <?php if (!$feedbacks): ?>
        <p class="admin-panel rounded-xl p-5 text-sm">Henüz geri bildirim yok.</p>
    <?php else: ?>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($feedbacks as $feedback):
                $imageUrl = chef_media_image_url($feedback['image_path'] ?? null);
                $name = trim((string)($feedback['customer_name'] ?? '')) ?: 'İsimsiz müşteri';
                $testId = (int)$feedback['id'];
            ?>
                <article class="admin-panel flex flex-col gap-3 rounded-xl p-4" data-testid="feedback-row-<?php echo $testId; ?>">
                    <div class="flex items-start justify-between gap-3">
                        <div><h3 class="text-lg"><?php echo sanitize($name); ?></h3><p class="admin-muted text-xs"><?php echo date('d.m.Y H:i', strtotime($feedback['created_at'])); ?></p></div>
                        <p class="font-semibold"><?php echo (int)$feedback['rating']; ?>/5</p>
                    </div>
                    <?php if (!empty($feedback['topic'])): ?><p class="admin-muted text-xs">Konu: <?php echo sanitize($topicLabels[$feedback['topic']] ?? $feedback['topic']); ?></p><?php endif; ?>
                    <?php if (!empty($feedback['contact'])): ?><p class="admin-muted text-xs">İletişim: <?php echo sanitize($feedback['contact']); ?></p><?php endif; ?>
                    <p class="admin-muted line-clamp-4 flex-1 border-t pt-3 text-sm"><?php echo sanitize((string)($feedback['comment'] ?? '')); ?></p>
                    <?php if ($imageUrl): ?><img src="<?php echo sanitize($imageUrl); ?>" alt="<?php echo sanitize($name); ?> geri bildirim görseli" class="h-24 w-full rounded-lg object-cover"><?php endif; ?>
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
