<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(404);
    exit;
}
?>
        </main>
    </div>
</div>
<nav class="admin-bottom-nav lg:hidden" aria-label="Yönetim bölümleri">
    <?php foreach ($sections as $key => $section): ?>
        <a href="<?php echo sanitize($section['tabs'][0]['href']); ?>" class="bottom-link" data-section="<?php echo sanitize($key); ?>" <?php echo $activeSection === $key ? 'aria-current="page"' : ''; ?>>
            <?php echo $icon($section['icon']); ?><span><?php echo sanitize($section['label']); ?></span>
        </a>
    <?php endforeach; ?>
</nav>
<dialog class="image-viewer" data-image-viewer aria-label="Görsel">
    <img src="" alt="" data-image-viewer-img>
    <div class="image-viewer-bar">
        <a href="" class="admin-button admin-button-secondary" data-image-viewer-download download>İndir</a>
        <button type="button" class="admin-button admin-button-secondary" data-image-viewer-share hidden>Paylaş</button>
        <button type="button" class="admin-button admin-button-primary" data-image-viewer-close>Kapat</button>
    </div>
</dialog>
</body>
</html>
