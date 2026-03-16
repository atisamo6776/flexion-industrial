<?php

require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/header.php';

$pdo = db();

$categoryId   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$categorySlug = $_GET['slug'] ?? '';
$sort         = $_GET['sort'] ?? 'relevance';

if (!in_array($sort, ['relevance', 'az', 'za'], true)) {
    $sort = 'relevance';
}

$category = null;
try {
    if ($categoryId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute([':id' => $categoryId]);
        $category = $stmt->fetch() ?: null;
    } elseif ($categorySlug !== '') {
        // Önce çeviri tablosunda slug ara
        $stmt = $pdo->prepare(
            'SELECT c.* FROM categories c
             JOIN category_translations ct ON ct.category_id = c.id
             WHERE ct.slug = :slug AND ct.language = :lang AND c.is_active = 1 LIMIT 1'
        );
        $stmt->execute([':slug' => $categorySlug, ':lang' => CURRENT_LANG]);
        $category = $stmt->fetch() ?: null;
        if (!$category) {
            // Ana tabloda dene
            $stmt2 = $pdo->prepare('SELECT * FROM categories WHERE slug = :slug AND is_active = 1 LIMIT 1');
            $stmt2->execute([':slug' => $categorySlug]);
            $category = $stmt2->fetch() ?: null;
        }
    }
    if ($category) {
        $categoryId = (int) $category['id'];
        // Çeviriyi uygula
        $catTr = get_translation('category_translations', 'category_id', $categoryId);
        if ($catTr) {
            $category['name']              = $catTr['name'] ?: $category['name'];
            $category['short_description'] = $catTr['short_description'] ?? $category['short_description'];
            $category['description']       = $catTr['description'] ?? $category['description'];
        }
    }
} catch (Throwable $e) {
    error_log('[flexion] category query failed: ' . $e->getMessage());
    $category = null;
}

if (!$category) {
    http_response_code(404);
    ?>
    <div class="container py-5">
        <h1 class="h3 mb-3"><?= e(t('cat_not_found', 'Category not found')) ?></h1>
        <p class="text-muted"><?= e(t('cat_not_found_desc', 'The category you are looking for does not exist or is inactive.')) ?></p>
        <a href="<?= e(categories_list_url()) ?>" class="btn btn-outline-secondary btn-sm"><?= e(t('cat_back_categories', 'Back to all categories')) ?></a>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$categoriesTree   = get_categories_tree();
$activeCategoryId = $categoryId;

$orderSql = 'ORDER BY sort_order ASC, name ASC';
if ($sort === 'az') {
    $orderSql = 'ORDER BY name ASC';
} elseif ($sort === 'za') {
    $orderSql = 'ORDER BY name DESC';
}

$totalProducts = 0;
$products      = [];
try {
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = :cid AND is_active = 1');
    $countStmt->execute([':cid' => $categoryId]);
    $totalProducts = (int) $countStmt->fetchColumn();
} catch (Throwable $e) {
    error_log('[flexion] category product count query failed: ' . $e->getMessage());
    $totalProducts = 0;
}

$perPage = 16;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$maxPage = max(1, (int) ceil($totalProducts / $perPage));
if ($page > $maxPage) {
    $page = $maxPage;
}
$offset = ($page - 1) * $perPage;

try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE category_id = :cid AND is_active = 1 $orderSql LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':cid', $categoryId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('[flexion] category products query failed: ' . $e->getMessage());
    $products = [];
}
?>

<section class="py-5">
    <div class="container">
        <div class="row">
            <aside class="col-lg-3 mb-4 mb-lg-0">
                <?php require __DIR__ . '/includes/categories_sidebar.php'; ?>
            </aside>
            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-end mb-3 flex-wrap gap-3">
                    <div>
                        <h1 class="h3 mb-1"><?= e($category['name']) ?></h1>
                        <?php if (!empty($category['short_description'])): ?>
                            <p class="text-muted mb-0 small"><?= e($category['short_description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-2">
                        <div class="btn-group btn-group-sm" role="group">
                            <a href="category?id=<?= e((string)$categoryId) ?>&sort=relevance"
                               class="btn btn-outline-secondary <?= $sort === 'relevance' ? 'active' : '' ?>">
                                <?= e(t('cat_sort_relevance', 'Relevance')) ?>
                            </a>
                            <a href="category?id=<?= e((string)$categoryId) ?>&sort=az"
                               class="btn btn-outline-secondary <?= $sort === 'az' ? 'active' : '' ?>">
                                <?= e(t('cat_sort_az', 'A–Z')) ?>
                            </a>
                            <a href="category?id=<?= e((string)$categoryId) ?>&sort=za"
                               class="btn btn-outline-secondary <?= $sort === 'za' ? 'active' : '' ?>">
                                <?= e(t('cat_sort_za', 'Z–A')) ?>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="row g-3">
                    <?php foreach ($products as $product): ?>
                        <div class="col-md-4 fx-animate">
                            <a href="<?= e(product_url($product['slug'])) ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                                <?php if (!empty($product['main_image'])): ?>
                                    <div class="fx-product-thumb">
                                        <img src="<?= e(asset_url($product['main_image'])) ?>" class="fx-product-thumb-img" alt="<?= e($product['name']) ?>" loading="lazy">
                                    </div>
                                <?php else: ?>
                                    <div class="fx-product-thumb-placeholder">
                                        <i class="bi bi-box-seam fs-1"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="card-body py-3">
                                    <h2 class="h6 fw-semibold mb-1"><?= e($product['name']) ?></h2>
                                    <?php if (!empty($product['code'])): ?>
                                        <p class="small text-muted mb-1"><?= e(t('prod_code_label', 'Code')) ?>: <?= e($product['code']) ?></p>
                                    <?php endif; ?>
                                    <p class="small text-muted mb-0"><?= e($product['short_description'] ?? '') ?></p>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($products)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <?= e(t('cat_no_products', 'No products found in this category.')) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($maxPage > 1): ?>
                    <nav class="mt-4" aria-label="Sayfalama">
                        <ul class="pagination pagination-sm justify-content-center">
                            <?php
                            $baseUrl = 'category?id=' . urlencode((string) $categoryId) . '&sort=' . urlencode($sort) . '&page=';
                            ?>
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $page <= 1 ? '#' : $baseUrl . ($page - 1) ?>"><?= e(t('pagination_prev', 'Previous')) ?></a>
                            </li>
                            <?php for ($p = 1; $p <= $maxPage; $p++): ?>
                                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $baseUrl . $p ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $maxPage ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $page >= $maxPage ? '#' : $baseUrl . ($page + 1) ?>"><?= e(t('pagination_next', 'Next')) ?></a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

