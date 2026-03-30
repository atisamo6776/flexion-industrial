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
        $_redir = $productSlug !== '' ? product_url($productSlug) : 'product?id=' . $productId;
        header('Location: ' . $_redir . '?sent=1');
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
            $_redir = $productSlug !== '' ? product_url($productSlug) : 'product?id=' . $productId;
            header('Location: ' . $_redir . '?sent=1');
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
        <a href="<?= e(categories_list_url()) ?>" class="btn btn-outline-secondary btn-sm"><?= e(t('prod_back', 'Back to products')) ?></a>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// İkonlar (kütüphaneden seçilenler)
$pickedIcons = [];
try {
    $stmt = $pdo->prepare(
        'SELECT ci.* FROM catalog_product_icons ci
         JOIN product_icon_picks pip ON pip.icon_id = ci.id
         WHERE pip.product_id = :pid AND ci.is_active = 1
         ORDER BY pip.sort_order ASC, pip.id ASC'
    );
    $stmt->execute([':pid' => $productId]);
    $pickedIcons = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('[flexion] product icons query failed: ' . $e->getMessage());
}

// Regülasyon görselleri (kütüphaneden seçilenler)
$pickedRegs = [];
try {
    $stmt = $pdo->prepare(
        'SELECT cri.* FROM catalog_regulation_images cri
         JOIN product_regulation_picks prp ON prp.regulation_image_id = cri.id
         WHERE prp.product_id = :pid AND cri.is_active = 1
         ORDER BY prp.sort_order ASC, prp.id ASC'
    );
    $stmt->execute([':pid' => $productId]);
    $pickedRegs = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('[flexion] product regulation images query failed: ' . $e->getMessage());
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
    $relatedStmt = $pdo->prepare('SELECT id, slug, name, main_image, code FROM products WHERE category_id = :cid AND id <> :id AND is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 3');
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
                <li class="breadcrumb-item"><a href="<?= e(home_url()) ?>"><?= e(t('nav_home', 'Home')) ?></a></li>
                <li class="breadcrumb-item"><a href="<?= e(categories_list_url()) ?>"><?= e(t('nav_products', 'Products')) ?></a></li>
                <li class="breadcrumb-item">
                    <a href="category?id=<?= e((string)$product['category_id']) ?>"><?= e($product['category_name']) ?></a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($product['name']) ?></li>
            </ol>
        </nav>

        <div class="row g-4 mb-5">
            <!-- ═══════════ SOL: Ana görsel + Regülasyon görselleri ═══════════ -->
            <div class="col-12 col-md-5 fx-animate">

                <!-- Ana görsel (tek; galeri yok) -->
                <?php if (!empty($product['main_image'])): ?>
                    <div class="fx-product-main">
                        <img src="<?= e(asset_url($product['main_image'])) ?>"
                             alt="<?= e($product['name']) ?>"
                             class="fx-product-main-img">
                    </div>
                <?php else: ?>
                    <div class="fx-product-main">
                        <span class="text-muted small text-center">
                            <i class="bi bi-image fs-1 d-block mb-2 opacity-50"></i>
                            <?= e(t('prod_no_image', 'No image uploaded')) ?>
                        </span>
                    </div>
                <?php endif; ?>

                <!-- Regülasyon / Sertifika görselleri -->
                <?php if ($pickedRegs): ?>
                    <div class="mt-4 pt-3 border-top">
                        <p class="small text-uppercase text-muted fw-semibold mb-2" style="font-size:.72rem;letter-spacing:.06em;">
                            <?= e(t('prod_regs_title', 'Regulations')) ?>
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($pickedRegs as $reg): ?>
                                <div class="fx-reg-badge">
                                    <img src="<?= e(asset_url($reg['image_path'])) ?>"
                                         alt="<?= e($reg['admin_label']) ?>"
                                         loading="lazy">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ═══════════ SAĞ: İkonlar + Bilgi, doküman, buton ═══════════ -->
            <div class="col-12 col-md-7 fx-animate" data-delay="80">
                <div class="d-flex gap-3">

                    <!-- İçerik sütunu -->
                    <div class="flex-grow-1 min-w-0">

                        <!-- Kategori yolu -->
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

                        <!-- Uzun açıklama -->
                        <?php if (!empty($product['description'])): ?>
                            <div class="product-description mb-4">
                                <?= sanitize_html($product['description']) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Kapsül butonlar: bilgi al + doküman indirme -->
                        <div class="fx-product-pill-actions mb-4">
                            <button type="button"
                                    class="btn btn-primary fx-btn-pill-inquiry"
                                    data-bs-toggle="modal" data-bs-target="#inquiryModal">
                                <?= e(t('prod_inquiry_title', 'Request Information')) ?>
                            </button>
                            <?php if (!empty($documents)): ?>
                                <?php foreach ($documents as $doc): ?>
                                    <a href="<?= e(asset_url($doc['file_path'])) ?>" target="_blank" rel="noopener"
                                       class="fx-btn-pill-doc">
                                        <span><?= e($doc['title']) ?></span>
                                        <i class="bi bi-download" aria-hidden="true"></i>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Dikey ikon sütunu (varsa) -->
                    <?php if ($pickedIcons): ?>
                        <div class="fx-icon-column flex-shrink-0">
                            <?php foreach ($pickedIcons as $icon): ?>
                                <div class="fx-icon-cell">
                                    <img src="<?= e(asset_url($icon['image_path'])) ?>"
                                         alt="<?= e($icon['admin_label']) ?>"
                                         loading="lazy">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Regülasyon açıklaması (tam genişlik, üründe doldurulmuşsa) -->
        <?php if (!empty($product['regulation_description'])): ?>
        <div class="row mb-5">
            <div class="col-12">
                <div class="fx-reg-desc-block">
                    <?= sanitize_html($product['regulation_description']) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

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
                    <?php $rpHref = !empty($rp['slug']) ? product_url((string)$rp['slug']) : 'product?id=' . (int)($rp['id'] ?? 0); ?>
                    <a href="<?= e($rpHref) ?>"
                       class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                        <?php if (!empty($rp['main_image'])): ?>
                            <div class="fx-product-thumb">
                                <img src="<?= e(asset_url($rp['main_image'])) ?>" class="fx-product-thumb-img" alt="<?= e($rp['name']) ?>" loading="lazy">
                            </div>
                        <?php else: ?>
                            <div class="fx-product-thumb-placeholder">
                                <i class="bi bi-box-seam fs-1"></i>
                            </div>
                        <?php endif; ?>
                        <div class="card-body py-3">
                            <h3 class="h6 fw-semibold mb-1"><?= e($rp['name']) ?></h3>
                            <?php if (!empty($rp['code'])): ?>
                                <p class="small text-muted mb-0"><?= e(t('prod_code_label', 'Code')) ?>: <?= e($rp['code']) ?></p>
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
