<?php

require_once __DIR__ . '/includes/header.php';

$pdo = db();

$categoryId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$sort       = $_GET['sort'] ?? 'relevance';

if (!in_array($sort, ['relevance', 'az', 'za'], true)) {
    $sort = 'relevance';
}

try {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute([':id' => $categoryId]);
    $category = $stmt->fetch();
} catch (Throwable $e) {
    $category = null;
}

if (!$category) {
    http_response_code(404);
    ?>
    <div class="container py-5">
        <h1 class="h3 mb-3">Kategori bulunamadı</h1>
        <p class="text-muted">Aradığınız kategori sistemde yer almıyor veya pasif durumda.</p>
        <a href="sectors.php" class="btn btn-outline-secondary btn-sm">Tüm sektörlere dön</a>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$categories = get_active_categories();

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
    $products = [];
}
?>

<section class="py-5">
    <div class="container">
        <div class="row">
            <aside class="col-lg-3 mb-4 mb-lg-0">
                <h2 class="h6 text-uppercase text-muted mb-3">Sektörler</h2>
                <ul class="list-group small">
                    <?php foreach ($categories as $cat): ?>
                        <?php $active = ((int)$cat['id'] === $categoryId); ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center <?= $active ? 'active' : '' ?>">
                            <a href="category.php?id=<?= e((string) $cat['id']) ?>"
                               class="text-decoration-none <?= $active ? 'text-white' : 'text-dark' ?>">
                                <?= e($cat['name']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
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
                        <div class="btn-group btn-group-sm" role="group" aria-label="Sıralama">
                            <a href="category.php?id=<?= e((string)$categoryId) ?>&sort=relevance"
                               class="btn btn-outline-secondary <?= $sort === 'relevance' ? 'active' : '' ?>">
                                Varsayılan
                            </a>
                            <a href="category.php?id=<?= e((string)$categoryId) ?>&sort=az"
                               class="btn btn-outline-secondary <?= $sort === 'az' ? 'active' : '' ?>">
                                İsim A-Z
                            </a>
                            <a href="category.php?id=<?= e((string)$categoryId) ?>&sort=za"
                               class="btn btn-outline-secondary <?= $sort === 'za' ? 'active' : '' ?>">
                                İsim Z-A
                            </a>
                        </div>
                        <a href="sectors.php" class="btn btn-outline-secondary btn-sm">
                            Tüm sektörler
                        </a>
                    </div>
                </div>
                <div class="row g-3">
                    <?php foreach ($products as $product): ?>
                        <div class="col-md-4">
                            <a href="product.php?id=<?= e((string) $product['id']) ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                                <?php if (!empty($product['main_image'])): ?>
                                    <img src="<?= e($product['main_image']) ?>" class="card-img-top" alt="<?= e($product['name']) ?>">
                                <?php endif; ?>
                                <div class="card-body py-3">
                                    <h2 class="h6 mb-1"><?= e($product['name']) ?></h2>
                                    <?php if (!empty($product['code'])): ?>
                                        <p class="small text-muted mb-1">Kod: <?= e($product['code']) ?></p>
                                    <?php endif; ?>
                                    <p class="small text-muted mb-0"><?= e($product['short_description'] ?? '') ?></p>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($products)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                Bu kategoriye henüz ürün eklenmemiş.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($maxPage > 1): ?>
                    <nav class="mt-4" aria-label="Sayfalama">
                        <ul class="pagination pagination-sm justify-content-center">
                            <?php
                            $baseUrl = 'category.php?id=' . urlencode((string) $categoryId) . '&sort=' . urlencode($sort) . '&page=';
                            ?>
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $page <= 1 ? '#' : $baseUrl . ($page - 1) ?>">Önceki</a>
                            </li>
                            <?php for ($p = 1; $p <= $maxPage; $p++): ?>
                                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $baseUrl . $p ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $maxPage ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $page >= $maxPage ? '#' : $baseUrl . ($page + 1) ?>">Sonraki</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

