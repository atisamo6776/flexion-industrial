<?php

require_once __DIR__ . '/includes/header.php';

$pdo = db();
$q   = trim($_GET['q'] ?? '');

$resultsProducts = [];
$resultsCats     = [];

if ($q !== '') {
    $like = '%' . $q . '%';

    try {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE is_active = 1 AND (name LIKE :q1 OR code LIKE :q2 OR short_description LIKE :q3) ORDER BY name ASC LIMIT 50');
        $stmt->execute([':q1' => $like, ':q2' => $like, ':q3' => $like]);
        $resultsProducts = $stmt->fetchAll();
    } catch (Throwable $e) {
        $resultsProducts = [];
    }

    try {
        $stmt2 = $pdo->prepare('SELECT * FROM categories WHERE is_active = 1 AND (name LIKE :q1 OR short_description LIKE :q2) ORDER BY name ASC LIMIT 20');
        $stmt2->execute([':q1' => $like, ':q2' => $like]);
        $resultsCats = $stmt2->fetchAll();
    } catch (Throwable $e) {
        $resultsCats = [];
    }
}
?>

<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h1 class="h4 mb-1">Arama Sonuçları</h1>
                <?php if ($q !== ''): ?>
                    <p class="text-muted small mb-0">Aranan ifade: \"<?= e($q) ?>\"</p>
                <?php else: ?>
                    <p class="text-muted small mb-0">Arama kutusuna bir ifade yazın.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($q === ''): ?>
            <form action="search" method="get" class="mb-4">
                <div class="input-group">
                    <input type="search" name="q" class="form-control" placeholder="Ürün veya kategori ara...">
                    <button class="btn btn-primary" type="submit">Ara</button>
                </div>
            </form>
        <?php else: ?>
            <?php if (empty($resultsProducts) && empty($resultsCats)): ?>
                <p class="text-muted">\"<?= e($q) ?>\" için sonuç bulunamadı.</p>
            <?php endif; ?>

            <?php if (!empty($resultsCats)): ?>
                <h2 class="h5 mt-3 mb-3">Kategoriler</h2>
                <div class="row g-3 mb-4">
                    <?php foreach ($resultsCats as $cat): ?>
                        <div class="col-md-4">
                            <a href="category?id=<?= e((string) $cat['id']) ?>" class="card h-100 text-decoration-none text-dark border-0 shadow-sm">
                                <?php if (!empty($cat['image'])): ?>
                                    <img src="<?= e($cat['image']) ?>" class="card-img-top" alt="<?= e($cat['name']) ?>">
                                <?php endif; ?>
                                <div class="card-body py-3">
                                    <h3 class="h6 mb-1"><?= e($cat['name']) ?></h3>
                                    <p class="small text-muted mb-0"><?= e($cat['short_description'] ?? '') ?></p>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($resultsProducts)): ?>
                <h2 class="h5 mt-4 mb-3">Ürünler</h2>
                <div class="row g-3">
                    <?php foreach ($resultsProducts as $product): ?>
                        <div class="col-md-3 col-sm-6">
                            <a href="<?= e(product_url($product['slug'])) ?>" class="card h-100 text-decoration-none text-dark border-0 shadow-sm">
                                <?php if (!empty($product['main_image'])): ?>
                                    <div class="fx-product-thumb">
                                        <img src="<?= e($product['main_image']) ?>" class="fx-product-thumb-img" alt="<?= e($product['name']) ?>" loading="lazy">
                                    </div>
                                <?php else: ?>
                                    <div class="fx-product-thumb-placeholder">
                                        <i class="bi bi-box-seam fs-1"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="card-body py-3">
                                    <h3 class="h6 fw-semibold mb-1"><?= e($product['name']) ?></h3>
                                    <?php if (!empty($product['code'])): ?>
                                        <p class="small text-muted mb-1"><?= e(t('prod_code_label', 'Code')) ?>: <?= e($product['code']) ?></p>
                                    <?php endif; ?>
                                    <p class="small text-muted mb-0"><?= e($product['short_description'] ?? '') ?></p>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

