<?php
// ════════════════════════════════════════════════════════════════════
//  FORM İŞLEME — HTML output'tan ÖNCE (PRG pattern: 500 + double-submit engeli)
// ════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/functions.php';

$pdo         = db();
$productId   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$productSlug = $_GET['slug'] ?? '';
$catSlug     = $_GET['cat_slug'] ?? '';

// GET ile gelen başarı bayrağı (PRG redirect sonrası)
$inquirySent  = isset($_GET['sent']) && $_GET['sent'] === '1';
$inquiryError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['inquiry_submit'])) {
    // Honeypot: bot doldurursa sessizce geç
    if (!empty($_POST['website_url'])) {
        header('Location: product?id=' . $productId . '&sent=1');
        exit;
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $inquiryError = 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyin ve tekrar deneyin.';
    } else {
        $iName    = trim($_POST['inq_name']    ?? '');
        $iSurname = trim($_POST['inq_surname'] ?? '');
        $iEmail   = trim($_POST['inq_email']   ?? '');
        $iPhone   = trim($_POST['inq_phone']   ?? '');
        $iCompany = trim($_POST['inq_company'] ?? '');
        $iCountry = trim($_POST['inq_country'] ?? '');
        $iMsg     = trim($_POST['inq_message'] ?? '');
        $iPid     = (int) ($_POST['inq_product_id'] ?? 0);

        if (!$iName || !$iEmail || !$iMsg) {
            $inquiryError = 'Ad, e-posta ve mesaj zorunludur.';
        } elseif (!filter_var($iEmail, FILTER_VALIDATE_EMAIL)) {
            $inquiryError = 'Geçerli bir e-posta adresi girin.';
        } else {
            $fullName = trim($iName . ' ' . $iSurname);
            try {
                $ins = $pdo->prepare('INSERT INTO contact_submissions
                    (type,product_id,name,email,phone,company,country,message)
                    VALUES(:type,:pid,:name,:email,:phone,:company,:country,:msg)');
                $ins->execute([
                    ':type'    => 'inquiry',
                    ':pid'     => $iPid ?: null,
                    ':name'    => $fullName,
                    ':email'   => $iEmail,
                    ':phone'   => $iPhone  ?: null,
                    ':company' => $iCompany ?: null,
                    ':country' => $iCountry ?: null,
                    ':msg'     => $iMsg,
                ]);
            } catch (Throwable $e2) {
                error_log('[flexion] inquiry form DB insert failed: ' . $e2->getMessage());
            }
            $toMail = get_setting('contact_email', '');
            if ($toMail) {
                $subj = 'Flexion Bilgi Talebi - ' . ($iCompany ?: $fullName);
                $body = "Ürün ID: $iPid\nAd: $fullName\nE-posta: $iEmail\nTelefon: $iPhone\nŞirket: $iCompany\nÜlke: $iCountry\n\nMesaj:\n$iMsg";
                send_notification_mail($toMail, $subj, $body);
            }
            // PRG redirect → sayfayı yenileme double-submit yapmaz, 500 hatası ortadan kalkar
            header('Location: product.php?id=' . $productId . '&sent=1');
            exit;
        }
    }
}

// ════════ HTML çıktısı burada başlıyor ═══════════════════════════════════════
require_once __DIR__ . '/includes/header.php';

// ---- Ürün verisi (id veya slug ile) ----
$product = null;
try {
    if ($productId > 0) {
        $stmt = $pdo->prepare('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = :id AND p.is_active = 1 LIMIT 1');
        $stmt->execute([':id' => $productId]);
        $product = $stmt->fetch() ?: null;
    } elseif ($productSlug !== '') {
        // Çeviri tablosunda slug ara
        $stmt = $pdo->prepare(
            'SELECT p.*, c.name AS category_name
             FROM products p
             JOIN categories c ON c.id = p.category_id
             JOIN product_translations pt ON pt.product_id = p.id
             WHERE pt.slug = :slug AND pt.language = :lang AND p.is_active = 1 LIMIT 1'
        );
        $stmt->execute([':slug' => $productSlug, ':lang' => CURRENT_LANG]);
        $product = $stmt->fetch() ?: null;
        if (!$product) {
            $stmt2 = $pdo->prepare('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.slug = :slug AND p.is_active = 1 LIMIT 1');
            $stmt2->execute([':slug' => $productSlug]);
            $product = $stmt2->fetch() ?: null;
        }
    }
    if ($product) {
        $productId = (int) $product['id'];
        // Çeviriyi uygula
        $prodTr = get_translation('product_translations', 'product_id', $productId);
        if ($prodTr) {
            $product['name']              = $prodTr['name'] ?: $product['name'];
            $product['short_description'] = $prodTr['short_description'] ?? $product['short_description'];
            $product['description']       = $prodTr['description'] ?? $product['description'];
        }
        // Kategori adını çevir
        $catTr = get_translation('category_translations', 'category_id', (int)$product['category_id']);
        if ($catTr && $catTr['name']) $product['category_name'] = $catTr['name'];
    }
} catch (Throwable $e) {
    error_log('[flexion] product query failed: ' . $e->getMessage());
    $product = null;
}

if (!$product) {
    http_response_code(404);
    ?>
    <div class="container py-5">
        <h1 class="h3 mb-3"><?= e(t('prod_not_found', 'Product not found')) ?></h1>
        <p class="text-muted"><?= e(t('prod_not_found_desc', 'The product you are looking for does not exist or is inactive.')) ?></p>
        <a href="sectors" class="btn btn-outline-secondary btn-sm"><?= e(t('prod_back', 'Back to products')) ?></a>
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
} catch (Throwable $e) {
    error_log('[flexion] product regulations query failed: ' . $e->getMessage());
}

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
} catch (Throwable $e) {
    error_log('[flexion] product spec tables query failed: ' . $e->getMessage());
}

// Ek görseller
$extraImages = [];
try {
    $imgStmt = $pdo->prepare('SELECT * FROM product_images WHERE product_id = :pid ORDER BY sort_order ASC, id ASC');
    $imgStmt->execute([':pid' => $productId]);
    $extraImages = $imgStmt->fetchAll();
} catch (Throwable $e) {
    error_log('[flexion] product images query failed: ' . $e->getMessage());
}

// Dokümanlar
$documents = [];
try {
    $docStmt = $pdo->prepare('SELECT * FROM product_documents WHERE product_id = :pid AND is_active = 1 ORDER BY sort_order ASC, id ASC');
    $docStmt->execute([':pid' => $productId]);
    $documents = $docStmt->fetchAll();
} catch (Throwable $e) {
    error_log('[flexion] product documents query failed: ' . $e->getMessage());
}

// Benzer ürünler
$relatedProducts = [];
try {
    $relatedStmt = $pdo->prepare('SELECT id, name, main_image, code FROM products WHERE category_id = :cid AND id <> :id AND is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 3');
    $relatedStmt->execute([':cid' => $product['category_id'], ':id' => $productId]);
    $relatedProducts = $relatedStmt->fetchAll();
} catch (Throwable $e) {
    error_log('[flexion] related products query failed: ' . $e->getMessage());
}
?>

<section class="py-5">
    <div class="container">

        <!-- Breadcrumb -->
        <nav class="mb-4" aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="/"><?= e(t('nav_home', 'Home')) ?></a></li>
                <li class="breadcrumb-item"><a href="sectors"><?= e(t('nav_products', 'Products')) ?></a></li>
                <li class="breadcrumb-item">
                    <a href="category?id=<?= e((string)$product['category_id']) ?>"><?= e($product['category_name']) ?></a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($product['name']) ?></li>
            </ol>
        </nav>

        <div class="row g-4 mb-5">
            <!-- ═══════════ SOL: Görsel + Küçük resimler + Regülasyonlar ═══════════ -->
            <div class="col-md-5 fx-animate">

                <!-- Ana görsel -->
                <?php if (!empty($product['main_image'])): ?>
                    <img id="main-product-img"
                         src="<?= e($product['main_image']) ?>"
                         alt="<?= e($product['name']) ?>"
                         class="img-fluid rounded-3 shadow-sm w-100"
                         style="max-height:380px;object-fit:contain;background:#f8f9fa;">
                <?php else: ?>
                    <div class="bg-light border rounded-3 d-flex align-items-center justify-content-center" style="min-height:280px;">
                        <span class="text-muted small"><i class="bi bi-image fs-1 d-block mb-2 opacity-50"></i>Görsel eklenmemiş</span>
                    </div>
                <?php endif; ?>

                <!-- Küçük resim galerisi -->
                <?php if (!empty($extraImages) || !empty($product['main_image'])): ?>
                    <div class="d-flex gap-2 mt-3 flex-wrap">
                        <?php if (!empty($product['main_image'])): ?>
                            <img src="<?= e($product['main_image']) ?>"
                                 height="56" class="rounded border gallery-thumb"
                                 style="cursor:pointer;object-fit:cover;width:56px;"
                                 onclick="document.getElementById('main-product-img').src=this.src">
                        <?php endif; ?>
                        <?php foreach ($extraImages as $eImg): ?>
                            <img src="<?= e($eImg['image']) ?>"
                                 height="56" class="rounded border gallery-thumb"
                                 style="cursor:pointer;object-fit:cover;width:56px;"
                                 loading="lazy"
                                 onclick="document.getElementById('main-product-img').src=this.src">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Regülasyonlar & Sertifikalar (referans: sol sütun altı) -->
                <?php if ($regulations): ?>
                    <div class="mt-4 pt-3 border-top">
                        <p class="small text-uppercase text-muted fw-semibold mb-2" style="font-size:.72rem;letter-spacing:.06em;">
                            <?= e(t('prod_regs_title', 'Regulations &amp; Certifications')) ?>
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($regulations as $reg): ?>
                                <span class="fx-regulation-badge">
                                    <?php if (!empty($reg['icon'])): ?>
                                        <img src="<?= e($reg['icon']) ?>" alt="<?= e($reg['title']) ?>">
                                    <?php endif; ?>
                                    <?= e($reg['title']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ═══════════ SAĞ: Bilgi, doküman, buton ═══════════ -->
            <div class="col-md-7 fx-animate" data-delay="80">

                <!-- Kategori yolu (referans: / Food /) -->
                <p class="small text-muted mb-1">
                    / <a href="category?id=<?= e((string)$product['category_id']) ?>"
                         class="text-muted text-decoration-none"><?= e($product['category_name']) ?></a> /
                </p>

                <h1 class="h2 fw-bold mb-1"><?= e($product['name']) ?></h1>

                <?php if (!empty($product['code'])): ?>
                    <p class="small text-muted mb-3"><?= e(t('prod_code_label', 'Product code')) ?>: <strong><?= e($product['code']) ?></strong></p>
                <?php endif; ?>

                <?php if (!empty($product['short_description'])): ?>
                    <p class="mb-3 text-secondary"><?= e($product['short_description']) ?></p>
                <?php endif; ?>

                <!-- Uzun açıklama: kısa açıklama ile butonlar arasında (referans görseli gibi) -->
                <?php if (!empty($product['description'])): ?>
                    <div class="product-description mb-4">
                        <?= sanitize_html($product['description']) ?>
                    </div>
                <?php endif; ?>

                <!-- Doküman Butonları (referans: Technical sheet ↓, Other documents ↓) -->
                <?php if (!empty($documents)): ?>
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <?php foreach ($documents as $doc): ?>
                            <a href="<?= e($doc['file_path']) ?>" target="_blank" rel="noopener"
                               class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2">
                                <i class="bi bi-file-earmark-arrow-down"></i>
                                <?= e($doc['title']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Bilgi Al Butonu -->
                <button type="button" class="btn btn-primary w-100 py-2 mt-2"
                        data-bs-toggle="modal" data-bs-target="#inquiryModal">
                    <i class="bi bi-envelope me-2"></i><?= e(t('prod_inquiry_title', 'Request Information')) ?>
                </button>
            </div>
        </div>

        <!-- Teknik Özellikler Tabloları -->
        <?php if ($specTables): ?>
        <div class="row mb-5">
            <div class="col-12">
                <h2 class="h5 mb-3 pb-2 border-bottom"><?= e(t('prod_spec_title', 'Technical Specifications')) ?></h2>
            </div>
            <?php foreach ($specTables as $table): ?>
                <div class="col-12 mb-4">
                    <div class="table-responsive">
                        <table class="product-spec-table">
                            <thead>
                                <tr>
                                    <th><?= !empty($table['title']) ? e($table['title']) : 'Özellik' ?></th>
                                    <th>Değer</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php $rows = $specRowsByTable[$table['id']] ?? []; ?>
                            <?php if (empty($rows)): ?>
                                <tr><td colspan="2" class="text-muted small py-3">Tablo boş</td></tr>
                            <?php else: ?>
                                <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?= e($row['label']) ?></td>
                                    <td><?= e($row['value']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Benzer Ürünler -->
        <?php if ($relatedProducts): ?>
        <div class="row mb-4">
            <div class="col-12 mb-3">
                <h2 class="h5"><?= e(t('prod_related', 'Related Products')) ?></h2>
            </div>
            <?php foreach ($relatedProducts as $rp): ?>
                <div class="col-md-4 fx-animate">
                    <a href="product?id=<?= e((string) $rp['id']) ?>"
                       class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                        <?php if (!empty($rp['main_image'])): ?>
                            <img src="<?= e($rp['main_image']) ?>" class="card-img-top fx-card-img" alt="<?= e($rp['name']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="fx-card-img bg-light d-flex align-items-center justify-content-center text-muted">
                                <i class="bi bi-box-seam fs-2"></i>
                            </div>
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

<!-- ================================================
     Bilgi Al Modal
================================================ -->
<div class="modal fade" id="inquiryModal" tabindex="-1" aria-labelledby="inquiryModalLabel">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title" id="inquiryModalLabel">
                    <i class="bi bi-envelope me-2"></i><?= e(t('prod_inquiry_title', 'Request Information')) ?> — <?= e($product['name']) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <?php if ($inquirySent): ?>
                    <div class="fx-success-anim text-center py-4">
                        <span style="font-size:3.5rem;color:#e61421;"><i class="bi bi-check-circle-fill"></i></span>
                        <h2 class="h5 mt-3 mb-2"><?= e(t('form_success_title', 'Request received!')) ?></h2>
                        <p class="text-muted"><?= e(t('prod_inquiry_sent', 'Your request has been received. We will contact you shortly.')) ?></p>
                        <button class="btn btn-outline-secondary btn-sm mt-2" data-bs-dismiss="modal"><?= e(t('btn_close', 'Close')) ?></button>
                    </div>
                <?php elseif ($inquiryError): ?>
                    <div class="alert alert-danger py-2 small"><?= e($inquiryError) ?></div>
                    <?php // Form aşağıda render edilecek ?>
                <?php endif; ?>

                <?php if (!$inquirySent): ?>
                <form method="post">
                    <input type="hidden" name="inquiry_submit" value="1">
                    <input type="hidden" name="inq_product_id" value="<?= e((string)$productId) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <!-- Honeypot: botlar doldurur, gerçek kullanıcılar görmez -->
                    <div style="display:none;" aria-hidden="true">
                        <input type="text" name="website_url" tabindex="-1" autocomplete="off" value="">
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label"><?= e(t('form_name', 'Name')) ?> <span class="text-danger">*</span></label>
                            <input type="text" name="inq_name" class="form-control" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><?= e(t('form_surname', 'Surname')) ?></label>
                            <input type="text" name="inq_surname" class="form-control">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><?= e(t('form_email', 'E-mail')) ?> <span class="text-danger">*</span></label>
                            <input type="email" name="inq_email" class="form-control" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><?= e(t('form_phone', 'Phone')) ?></label>
                            <input type="tel" name="inq_phone" class="form-control">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><?= e(t('form_company', 'Company')) ?></label>
                            <input type="text" name="inq_company" class="form-control">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><?= e(t('form_country', 'Country')) ?></label>
                            <input type="text" name="inq_country" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= e(t('form_message', 'Message')) ?> <span class="text-danger">*</span></label>
                            <textarea name="inq_message" class="form-control" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer px-0 pb-0 mt-3">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('btn_cancel', 'Cancel')) ?></button>
                        <button type="submit" class="btn btn-primary px-5">
                            <i class="bi bi-send me-2"></i><?= e(t('btn_submit', 'Send')) ?>
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($inquirySent): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = new bootstrap.Modal(document.getElementById('inquiryModal'));
        modal.show();
    });
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
