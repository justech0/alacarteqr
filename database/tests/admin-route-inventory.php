<?php
declare(strict_types=1);

return [
    'public' => [
        'api/menu.php' => ['GET'],
        'api/feedback.php' => ['POST', 'OPTIONS'],
        'login.php' => ['GET', 'POST'],
    ],
    'protected' => [
        'index.php' => ['GET', 'POST'],
        'branches.php' => ['GET', 'POST'],
        'bulk-prices.php' => ['GET', 'POST'],
        'campaign.php' => ['GET', 'POST'],
        'categories.php' => ['GET', 'POST'],
        'category-order.php' => ['GET', 'POST'],
        'feedbacks.php' => ['GET', 'POST'],
        'import-products.php' => ['GET', 'POST'],
        'product-order.php' => ['GET', 'POST'],
        'products.php' => ['GET', 'POST'],
        'private-image.php' => ['GET'],
        'logout.php' => ['POST'],
    ],
    'includes' => ['config.php', 'functions.php', 'header.php', 'footer.php', 'media-service.php', 'security.php'],
    'actions' => [
        'index.php' => ['invalidate'],
        'branches.php' => ['save'],
        'bulk-prices.php' => ['reset_all_prices', 'bulk_update_prices', 'export_csv', 'import_csv_prices'],
        'campaign.php' => ['save', 'delete_image'],
        'categories.php' => ['create', 'update', 'delete'],
        'category-order.php' => ['reorder'],
        'feedbacks.php' => ['delete'],
        'import-products.php' => ['import'],
        'product-order.php' => ['reorder'],
        'products.php' => ['create', 'update', 'move_category', 'delete'],
        'logout.php' => ['logout'],
    ],
];
