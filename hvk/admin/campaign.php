<?php
require_once __DIR__ . '/functions.php';
require_login();
chef_require_method(['GET', 'POST']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        flash_message('error', 'Geçersiz oturum.');
        header('Location: campaign.php');
        exit;
    }
    if (!chef_consume_submission_token((string)($_POST['submission_token'] ?? ''))) {
        flash_message('error', chef_submission_token_error());
        header('Location: campaign.php', true, 303);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $mediaStage = null;
    try {
        chef_media_assert_request_size();
        if ($action === 'save') {
            $mediaStage = chef_media_stage_upload($pdo, 'image', 'campaign');
        }
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO campaigns (id, is_active, image_path) VALUES (1, 0, NULL) ON DUPLICATE KEY UPDATE id = VALUES(id)')->execute();
        if ($action === 'save') {
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $currentImage = null;
            if ($mediaStage !== null) {
                $currentImage = chef_media_activate($pdo, $mediaStage, 'campaign', 1);
            }
            $sql = $currentImage === null
                ? 'UPDATE campaigns SET is_active = :active WHERE id = 1'
                : 'UPDATE campaigns SET is_active = :active, image_path = :image WHERE id = 1';
            $params = [':active' => $isActive];
            if ($currentImage !== null) $params[':image'] = $currentImage;
            $pdo->prepare($sql)->execute($params);
            bump_menu_version($pdo);

            flash_message('success', 'Kampanya güncellendi.');
        } elseif ($action === 'delete_image') {
            chef_media_mark_owner_deletion($pdo, 'campaign', 1);
            $pdo->prepare('UPDATE campaigns SET image_path = NULL, is_active = 0 WHERE id = 1')->execute();
            bump_menu_version($pdo);
            flash_message('success', 'Kampanya pasif edildi. Dosya temizliği doğrulanıyor.');
        }
        $pdo->commit();
        if ($mediaStage !== null) {
            chef_media_finalize_operation($pdo, $mediaStage['operation_id']);
        }
        chef_media_cleanup($pdo);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($mediaStage !== null) {
            chef_media_abandon_operation($pdo, $mediaStage['operation_id']);
        }
        flash_message('error', chef_admin_error('campaign_save_failed', $e));
    }

    header('Location: campaign.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM campaigns WHERE id = 1');
$stmt->execute();
$campaign = $stmt->fetch();
$isActive = (int)($campaign['is_active'] ?? 0) === 1;
$imagePath = $campaign['image_path'] ?? null;

require_once __DIR__ . '/header.php';
?>
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-serif text-hvk-accent tracking-[0.12em]">Kampanya Pop-up</h1>
            <p class="text-sm text-hvk-muted">Görseli ve görünürlüğü buradan yönetin.</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-3">
                <span class="text-sm text-hvk-muted">Durum:</span>
                <label class="relative inline-flex items-center cursor-pointer select-none">
                    <input id="is_active" name="is_active" type="checkbox" form="campaignForm" class="sr-only peer" <?php echo $isActive ? 'checked' : ''; ?> data-testid="campaign-active">
                    <span class="sr-only">Kampanyayı göster</span>
                    <div class="w-16 h-9 bg-white/10 peer-checked:bg-hvk-accent/70 rounded-full border border-hvk-accent/40 transition-colors relative">
                        <div class="absolute top-1 left-1 w-7 h-7 bg-white rounded-full shadow transform transition-transform duration-300 peer-checked:translate-x-7"></div>
                    </div>
                </label>
            </div>
        </div>
    </div>

    <form id="campaignForm" method="POST" enctype="multipart/form-data" class="admin-panel rounded-xl p-6 space-y-6" data-mutation-form data-warn-unsaved data-testid="campaign-form">
        <input type="hidden" name="csrf_token" value="<?php echo sanitize($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
        <input type="hidden" name="action" value="save">

        <div class="space-y-2">
            <label for="campaign-image" class="text-sm text-hvk-muted">Kampanya görseli</label>
            <input id="campaign-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" class="block w-full rounded-lg p-3 text-sm" data-testid="campaign-image" />
            <p class="text-xs text-hvk-muted">WebP/JPG/PNG yükleyebilirsiniz. Yeni yükleme mevcut görseli değiştirir.</p>
        </div>

        <div class="border border-dashed border-hvk-accent/30 rounded-xl p-4 bg-white/5">
            <?php if ($imagePath): ?>
                <div class="flex items-center gap-4">
                    <img src="<?php echo sanitize((string)chef_media_image_url($imagePath)); ?>" alt="Kampanya" class="h-32 w-auto rounded-lg object-contain bg-black/40 border border-hvk-accent/20">
                    <div class="space-y-2">
                        <p class="text-sm text-hvk-text">Güncel görsel</p>
                        <button form="deleteImageForm" type="submit" class="admin-button admin-button-danger" data-testid="campaign-delete">Görseli sil</button>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-sm text-hvk-muted">Henüz bir görsel eklenmedi.</p>
            <?php endif; ?>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="admin-button admin-button-primary" data-testid="campaign-save">Kaydet</button>
            <a href="index.php" class="px-4 py-3 rounded-lg border border-hvk-accent/30 text-hvk-text hover:bg-white/5">İptal</a>
        </div>
    </form>

    <form id="deleteImageForm" method="POST" class="hidden" data-mutation-form data-confirm-name="Kampanya görseli" data-confirm-detail="Görsel silinir ve kampanya pasif olur." data-testid="campaign-delete-form">
        <input type="hidden" name="csrf_token" value="<?php echo sanitize($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="submission_token" value="<?php echo sanitize($submissionToken); ?>">
        <input type="hidden" name="action" value="delete_image">
    </form>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
