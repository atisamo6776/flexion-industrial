<?php

require_once __DIR__ . '/includes/header.php';

$pdo = db();
$q   = trim($_GET['q'] ?? '');

$resultsProducts = [];
$resultsCats     = [];

if ($q !== '') {
    $like = '%' . $q . '%';

    $stmt = $pdo->prepare('SELECT * FROM products WHERE is_active = 1 AND (name LIKE :q OR code LIKE :q OR short_description LIKE :q) ORDER BY name ASC LIMIT 50');
    $stmt->execute([':q' => $like]);
    $resultsProducts = $stmt->fetchAll();

    $stmt2 = $pdo->prepare('SELECT * FROM categories WHERE is_active = 1 AND (name LIKE :q OR short_description LIKE :q) ORDER BY name ASC LIMIT 20');
    $stmt2->execute([':q' => $like]);
    $resultsCats = $stmt2->fetchAll();
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
            <form action="search.php" method="get" class="mb-4">
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
                            <a href="category.php?id<?= e((string) $cat['id']) ?>" class="card h-100 text-decoration-none text-dark border-0 shadow-sm">
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
                            <a href="product.php?id=<?= e((string) $product['id']) ?>" class="card h-100 text-decoration-none text-dark border-0 shadow-sm">
                                <?php if (!empty($product['main_image'])): ?>
                                    <img src="<?= e($product['main_image']) ?>" class="card-img-top" alt="<?= e($product['name']) ?>">
                                <?php endif; ?>
                                <div class="card-body py-3">
                                    <h3 class="h6 mb-1"><?= e($product['name']) ?></h3>
                                    <?php if (!empty($product['code'])): ?>
                                        <p class="small text-muted mb-1">Kod: <?= e($product['code']) ?></p>
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

