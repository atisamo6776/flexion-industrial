<?php

require_once __DIR__ . '/includes/header.php';

$pdo = db();
$productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

try {
    $stmt = $pdo->prepare('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = :id AND p.is_active = 1 LIMIT 1');
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();
} catch (Throwable $e) {
    $product = null;
}

if (!$product) {
    http_response_code(404);
    ?>
    <div class="container py-5">
        <h1 class="h3 mb-3">Ürün bulunamadı</h1>
        <p class="text-muted">Aradığınız ürün sistemde yer almıyor veya pasif durumda.</p>
        <a href="sectors.php" class="btn btn-outline-secondary btn-sm">Ürünlere dön</a>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Regulations
$regulations = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM product_regulations WHERE product_id = :pid AND is_active = 1 ORDER BY sort_order ASC, id ASC');
    $stmt->execute([':pid' => $productId]);
    $regulations = $stmt->fetchAll();
} catch (Throwable $e) { /* tablo yoksa boş */ }

// Spec tables + rows
$specTables      = [];
$specRowsByTable = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM product_spec_tables WHERE product_id = :pid AND is_active = 1 ORDER BY sort_order ASC, id ASC');
    $stmt->execute([':pid' => $productId]);
    $specTables = $stmt->fetchAll();

    if ($specTables) {
        $tableIds = array_column($specTables, 'id');
        $in       = implode(',', array_fill(0, count($tableIds), '?'));
        $rowsStmt = $pdo->prepare("SELECT * FROM product_specs WHERE table_id IN ($in) AND is_active = 1 ORDER BY sort_order ASC, id ASC");
        $rowsStmt->execute($tableIds);
        foreach ($rowsStmt as $row) {
            $specRowsByTable[$row['table_id']][] = $row;
        }
    }
} catch (Throwable $e) { /* tablo yoksa boş */ }

// Ek görseller
$extraImages = [];
try {
    $imgStmt = $pdo->prepare('SELECT * FROM product_images WHERE product_id = :pid ORDER BY sort_order ASC, id ASC');
    $imgStmt->execute([':pid' => $productId]);
    $extraImages = $imgStmt->fetchAll();
} catch (Throwable $e) { /* tablo yoksa boş */ }

// Dokümanlar
$documents = [];
try {
    $docStmt = $pdo->prepare('SELECT * FROM product_documents WHERE product_id = :pid AND is_active = 1 ORDER BY sort_order ASC, id ASC');
    $docStmt->execute([':pid' => $productId]);
    $documents = $docStmt->fetchAll();
} catch (Throwable $e) { /* tablo yoksa boş */ }

// Benzer ürünler (aynı kategoriden, kendisi hariç)
$relatedProducts = [];
try {
    $relatedStmt = $pdo->prepare('SELECT id, name, main_image, code FROM products WHERE category_id = :cid AND id <> :id AND is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 3');
    $relatedStmt->execute([':cid' => $product['category_id'], ':id' => $productId]);
    $relatedProducts = $relatedStmt->fetchAll();
} catch (Throwable $e) { /* tablo yoksa boş */ }
?>

<section class="py-5">
    <div class="container">
        <div class="row mb-4">
            <div class="col-md-6 mb-3 mb-md-0">
                <?php if (!empty($product['main_image'])): ?>
                    <img id="main-product-img" src="<?= e($product['main_image']) ?>" alt="<?= e($product['name']) ?>" class="img-fluid rounded-3 shadow-sm w-100" style="max-height:380px;object-fit:contain;">
                <?php else: ?>
                    <div class="bg-light border rounded-3 d-flex align-items-center justify-content-center" style="min-height:260px;">
                        <span class="text-muted">Ürün görseli henüz eklenmemiş</span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($extraImages)): ?>
                    <div class="d-flex gap-2 mt-3 flex-wrap">
                        <?php if (!empty($product['main_image'])): ?>
                            <img src="<?= e($product['main_image']) ?>"
                                 alt="Ana görsel"
                                 height="60"
                                 class="rounded border border-primary gallery-thumb"
                                 style="cursor:pointer;object-fit:cover;width:60px;"
                                 onclick="document.getElementById('main-product-img').src=this.src">
                        <?php endif; ?>
                        <?php foreach ($extraImages as $eImg): ?>
                            <img src="<?= e($eImg['image']) ?>"
                                 alt=""
                                 height="60"
                                 class="rounded border gallery-thumb"
                                 style="cursor:pointer;object-fit:cover;width:60px;"
                                 onclick="document.getElementById('main-product-img').src=this.src">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <p class="small text-muted mb-1">
                    <?= e($product['category_name']) ?>
                </p>
                <h1 class="h3 mb-2"><?= e($product['name']) ?></h1>
                <?php if (!empty($product['code'])): ?>
                    <p class="small text-muted mb-3">Ürün kodu: <strong><?= e($product['code']) ?></strong></p>
                <?php endif; ?>
                <?php if (!empty($product['short_description'])): ?>
                    <p class="mb-3"><?= e($product['short_description']) ?></p>
                <?php endif; ?>

                <?php if ($regulations): ?>
                    <div class="mb-3">
                        <h2 class="h6 mb-2">Regülasyonlar & Sertifikalar</h2>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($regulations as $reg): ?>
                                <div class="border rounded-pill px-3 py-1 small d-flex align-items-center bg-light">
                                    <?php if (!empty($reg['icon'])): ?>
                                        <img src="<?= e($reg['icon']) ?>" alt="<?= e($reg['title']) ?>" height="20" class="me-2">
                                    <?php endif; ?>
                                    <span><?= e($reg['title']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($product['description'])): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="h5 mb-3">Ürün Açıklaması</h2>
                    <div class="text-muted small">
                        <?= $product['description'] ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($specTables): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="h5 mb-3">Teknik Özellikler</h2>
                </div>
                <?php foreach ($specTables as $table): ?>
                    <div class="col-12 mb-3">
                        <?php if (!empty($table['title'])): ?>
                            <h3 class="h6 mb-2"><?= e($table['title']) ?></h3>
                        <?php endif; ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <tbody>
                                <?php foreach ($specRowsByTable[$table['id']] ?? [] as $row): ?>
                                    <tr>
                                        <th style="width:40%;"><?= e($row['label']) ?></th>
                                        <td><?= e($row['value']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($documents)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="h5 mb-3">Dokümanlar</h2>
                    <div class="list-group">
                        <?php foreach ($documents as $doc): ?>
                            <a href="<?= e($doc['file_path']) ?>" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                                <i class="bi bi-file-earmark-arrow-down fs-5 text-primary"></i>
                                <span><?= e($doc['title']) ?></span>
                                <span class="ms-auto small text-muted">İndir</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($relatedProducts): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="h5 mb-3">Benzer Ürünler</h2>
                </div>
                <?php foreach ($relatedProducts as $rp): ?>
                    <div class="col-md-4 mb-3">
                        <a href="product.php?id=<?= e((string) $rp['id']) ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                            <?php if (!empty($rp['main_image'])): ?>
                                <img src="<?= e($rp['main_image']) ?>" class="card-img-top" alt="<?= e($rp['name']) ?>">
                            <?php endif; ?>
                            <div class="card-body py-3">
                                <h3 class="h6 mb-1"><?= e($rp['name']) ?></h3>
                                <?php if (!empty($rp['code'])): ?>
                                    <p class="small text-muted mb-0">Kod: <?= e($rp['code']) ?></p>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

