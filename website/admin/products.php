<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload_helper.php';

require_admin_login();

$pdo     = db();
$error   = null;
$success = null;

$uploadDir  = __DIR__ . '/../assets/uploads/products/';
$uploadBase = 'assets/uploads/products/';

/* ================================================================
   Yardımcı: slug üret
================================================================ */
function prod_slug(string $name): string
{
    $slug = mb_strtolower($name, 'UTF-8');
    $slug = preg_replace('/\s+/', '-', $slug);
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    return trim($slug, '-') ?: bin2hex(random_bytes(4));
}

/* ================================================================
   POST işlemleri
================================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Güvenlik doğrulaması başarısız.';
    } else {
      try {

        // ---- Ürün ekle / güncelle ----
        if (isset($_POST['save_product'])) {
            $id       = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $catId    = (int) ($_POST['category_id'] ?? 0);
            $name     = trim($_POST['name'] ?? '');
            $code     = trim($_POST['code'] ?? '');
            $shortD   = trim($_POST['short_description'] ?? '');
            $desc     = trim($_POST['description'] ?? '');
            $active   = isset($_POST['is_active']) ? 1 : 0;

            if (!$name) {
                $error = 'Ürün adı zorunludur.';
            } elseif (!$catId) {
                $error = 'Kategori seçilmesi zorunludur.';
            } else {
                $slug = prod_slug($name);

                // Ana görsel
                $mainImage = null;
                if (!empty($_FILES['main_image']['name'])) {
                    $fname = upload_file($_FILES['main_image'], $uploadDir);
                    if ($fname) {
                        $mainImage = $uploadBase . $fname;
                    }
                }

                if ($id > 0) {
                    $imgSql = $mainImage ? ', main_image = :img' : '';
                    $params = [
                        ':cat'    => $catId,
                        ':name'   => $name,
                        ':slug'   => $slug,
                        ':code'   => $code ?: null,
                        ':sdesc'  => $shortD ?: null,
                        ':desc'   => $desc ?: null,
                        ':active' => $active,
                        ':id'     => $id,
                    ];
                    if ($mainImage) {
                        $params[':img'] = $mainImage;
                    }
                    $pdo->prepare("UPDATE products SET category_id = :cat, name = :name, slug = :slug,
                                code = :code, short_description = :sdesc, description = :desc,
                                is_active = :active{$imgSql} WHERE id = :id")
                        ->execute($params);
                    $success = 'Ürün güncellendi.';
                } else {
                    $sort = (int) $pdo->query('SELECT IFNULL(MAX(sort_order),0)+1 FROM products')->fetchColumn();
                    $pdo->prepare('INSERT INTO products (category_id, name, slug, code, short_description, description, main_image, sort_order, is_active)
                                   VALUES (:cat, :name, :slug, :code, :sdesc, :desc, :img, :sort, :active)')
                        ->execute([
                            ':cat'    => $catId,
                            ':name'   => $name,
                            ':slug'   => $slug,
                            ':code'   => $code ?: null,
                            ':sdesc'  => $shortD ?: null,
                            ':desc'   => $desc ?: null,
                            ':img'    => $mainImage,
                            ':sort'   => $sort,
                            ':active' => $active,
                        ]);
                    $id = (int) $pdo->lastInsertId();
                    $success = 'Ürün eklendi. Teknik tablo ve regülasyonları aşağıdan ekleyebilirsin.';
                }

                // Ek görseller
                if (!empty($_FILES['extra_images']['name'][0])) {
                    $stmtSortImg = $pdo->prepare('SELECT IFNULL(MAX(sort_order),0)+1 FROM product_images WHERE product_id = ?');
                    $stmtSortImg->execute([$id]);
                    $sortImg = (int) $stmtSortImg->fetchColumn();
                    foreach ($_FILES['extra_images']['name'] as $k => $fname2) {
                        $single = [
                            'name'     => $fname2,
                            'type'     => $_FILES['extra_images']['type'][$k],
                            'tmp_name' => $_FILES['extra_images']['tmp_name'][$k],
                            'error'    => $_FILES['extra_images']['error'][$k],
                            'size'     => $_FILES['extra_images']['size'][$k],
                        ];
                        if ($single['error'] === UPLOAD_ERR_OK) {
                            $fn = upload_file($single, $uploadDir);
                            if ($fn) {
                                $pdo->prepare('INSERT INTO product_images (product_id, image, sort_order) VALUES (?, ?, ?)')
                                    ->execute([$id, $uploadBase . $fn, $sortImg++]);
                            }
                        }
                    }
                }
            }
        }

        // ---- Ürün Çevirisi Kaydet ----
        elseif (isset($_POST['save_product_translation'])) {
            require_once __DIR__ . '/../includes/functions.php';
            $pid  = (int) ($_POST['product_id'] ?? 0);
            $lang = trim($_POST['trans_lang'] ?? '');
            if ($pid > 0 && in_array($lang, ['en','de','it','fr'], true)) {
                $name  = trim($_POST['trans_name'] ?? '');
                $slug  = make_slug($name ?: trim($_POST['trans_slug'] ?? ''));
                if (!$slug) $slug = 'product-' . $pid . '-' . $lang;
                save_translation('product_translations', 'product_id', $pid, $lang, [
                    'name'              => $name,
                    'slug'              => $slug,
                    'short_description' => trim($_POST['trans_short'] ?? '') ?: null,
                    'description'       => trim($_POST['trans_desc']  ?? '') ?: null,
                ]);
                $success = strtoupper($lang) . ' çevirisi kaydedildi.';
            }
        }

        // ---- Ürün sil ----
        elseif (isset($_POST['delete_product'])) {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare('DELETE FROM products WHERE id = :id')->execute([':id' => $id]);
                $pdo->prepare('DELETE FROM product_images WHERE product_id = :id')->execute([':id' => $id]);
                $pdo->prepare('DELETE FROM product_regulations WHERE product_id = :id')->execute([':id' => $id]);
                $stmt = $pdo->prepare('SELECT id FROM product_spec_tables WHERE product_id = :id');
                $stmt->execute([':id' => $id]);
                foreach ($stmt->fetchAll() as $t) {
                    $pdo->prepare('DELETE FROM product_specs WHERE table_id = :tid')->execute([':tid' => $t['id']]);
                }
                $pdo->prepare('DELETE FROM product_spec_tables WHERE product_id = :id')->execute([':id' => $id]);
                $success = 'Ürün ve tüm bağlı veriler silindi.';
            }
        }

        // ---- Spec tablosu ekle ----
        else        if (isset($_POST['add_spec_table'])) {
            $pid   = (int) ($_POST['product_id'] ?? 0);
            $title = trim($_POST['spec_table_title'] ?? '');
            if ($pid > 0) {
                $stmtSort = $pdo->prepare('SELECT IFNULL(MAX(sort_order),0)+1 FROM product_spec_tables WHERE product_id = ?');
                $stmtSort->execute([$pid]);
                $sort = (int) $stmtSort->fetchColumn();
                $pdo->prepare('INSERT INTO product_spec_tables (product_id, title, sort_order) VALUES (?, ?, ?)')
                    ->execute([$pid, $title ?: null, $sort]);
                $success = 'Tablo eklendi.';
            }
        }

        // ---- Spec satırı ekle ----
        elseif (isset($_POST['add_spec_row'])) {
            $tid   = (int) ($_POST['table_id'] ?? 0);
            $label = trim($_POST['spec_label'] ?? '');
            $value = trim($_POST['spec_value'] ?? '');
            if ($tid > 0 && $label !== '' && $value !== '') {
                $stmtSort = $pdo->prepare('SELECT IFNULL(MAX(sort_order),0)+1 FROM product_specs WHERE table_id = ?');
                $stmtSort->execute([$tid]);
                $sort = (int) $stmtSort->fetchColumn();
                $pdo->prepare('INSERT INTO product_specs (table_id, label, value, sort_order) VALUES (?, ?, ?, ?)')
                    ->execute([$tid, $label, $value, $sort]);
                $success = 'Satır eklendi.';
            }
        }

        // ---- Spec tablosunu komple sil ----
        elseif (isset($_POST['delete_spec_table'])) {
            $tid = (int) ($_POST['table_id'] ?? 0);
            if ($tid > 0) {
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM product_specs WHERE table_id = ?')->execute([$tid]);
                $pdo->prepare('DELETE FROM product_spec_tables WHERE id = ?')->execute([$tid]);
                $pdo->commit();
                $success = 'Tablo ve tüm satırları silindi.';
            }
        }

        // ---- Spec satırı sil ----
        elseif (isset($_POST['delete_spec_row'])) {
            $sid = (int) ($_POST['spec_id'] ?? 0);
            if ($sid > 0) {
                $pdo->prepare('DELETE FROM product_specs WHERE id = ?')->execute([$sid]);
                $success = 'Satır silindi.';
            }
        }

        // ---- Spec satırı aktif/pasif ----
        elseif (isset($_POST['toggle_spec_row'])) {
            $sid = (int) ($_POST['spec_id'] ?? 0);
            if ($sid > 0) {
                $pdo->prepare('UPDATE product_specs SET is_active = NOT is_active WHERE id = ?')->execute([$sid]);
                $success = 'Spec satırı durumu güncellendi.';
            }
        }

        // ---- Regülasyon ekle ----
        elseif (isset($_POST['add_regulation'])) {
            $pid   = (int) ($_POST['product_id'] ?? 0);
            $title = trim($_POST['reg_title'] ?? '');
            if ($pid > 0 && $title !== '') {
                $icon = null;
                if (!empty($_FILES['reg_icon']['name'])) {
                    $fn = upload_file($_FILES['reg_icon'], $uploadDir, ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml']);
                    if ($fn) {
                        $icon = $uploadBase . $fn;
                    }
                }
                $stmtSort = $pdo->prepare('SELECT IFNULL(MAX(sort_order),0)+1 FROM product_regulations WHERE product_id = ?');
                $stmtSort->execute([$pid]);
                $sort = (int) $stmtSort->fetchColumn();
                $pdo->prepare('INSERT INTO product_regulations (product_id, title, icon, sort_order) VALUES (?, ?, ?, ?)')
                    ->execute([$pid, $title, $icon, $sort]);
                $success = 'Regülasyon eklendi.';
            }
        }

        // ---- Regülasyon sil ----
        elseif (isset($_POST['delete_regulation'])) {
            $rid = (int) ($_POST['reg_id'] ?? 0);
            if ($rid > 0) {
                $pdo->prepare('DELETE FROM product_regulations WHERE id = ?')->execute([$rid]);
                $success = 'Regülasyon silindi.';
            }
        }

        // ---- Regülasyon aktif/pasif ----
        elseif (isset($_POST['toggle_regulation'])) {
            $rid = (int) ($_POST['reg_id'] ?? 0);
            if ($rid > 0) {
                $pdo->prepare('UPDATE product_regulations SET is_active = NOT is_active WHERE id = ?')->execute([$rid]);
                $success = 'Regülasyon durumu güncellendi.';
            }
        }

        // ---- Doküman ekle ----
        elseif (isset($_POST['add_document'])) {
            $pid      = (int) ($_POST['product_id'] ?? 0);
            $docTitle = trim($_POST['doc_title'] ?? '');
            if ($pid > 0 && $docTitle !== '' && !empty($_FILES['doc_file']['name'])) {
                $docUploadDir  = __DIR__ . '/../assets/uploads/documents/';
                $docUploadBase = 'assets/uploads/documents/';
                $fn = upload_file(
                    $_FILES['doc_file'],
                    $docUploadDir,
                    ['application/pdf', 'application/msword',
                     'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                     'application/vnd.ms-excel',
                     'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                    10 * 1024 * 1024
                );
                if ($fn) {
                    $sortStmt = $pdo->prepare('SELECT IFNULL(MAX(sort_order),0)+1 FROM product_documents WHERE product_id = ?');
                    $sortStmt->execute([$pid]);
                    $sort = (int) $sortStmt->fetchColumn();
                    $pdo->prepare('INSERT INTO product_documents (product_id, title, file_path, sort_order, is_active) VALUES (?, ?, ?, ?, 1)')
                        ->execute([$pid, $docTitle, $docUploadBase . $fn, $sort]);
                    $success = 'Doküman eklendi.';
                } else {
                    $error = 'Dosya yüklenemedi. PDF/Word/Excel, maks 10 MB.';
                }
            } else {
                $error = 'Başlık ve dosya zorunludur.';
            }
        }

        // ---- Doküman sil ----
        elseif (isset($_POST['delete_document'])) {
            $did = (int) ($_POST['doc_id'] ?? 0);
            if ($did > 0) {
                $pdo->prepare('DELETE FROM product_documents WHERE id = ?')->execute([$did]);
                $success = 'Doküman silindi.';
            }
        }

        // ---- Doküman aktif/pasif ----
        elseif (isset($_POST['toggle_document'])) {
            $did = (int) ($_POST['doc_id'] ?? 0);
            if ($did > 0) {
                $pdo->prepare('UPDATE product_documents SET is_active = NOT is_active WHERE id = ?')->execute([$did]);
                $success = 'Doküman durumu güncellendi.';
            }
        }

        // ---- Ürün sıralaması ----
        elseif (isset($_POST['save_order'])) {
            $order = $_POST['order'] ?? [];
            $i     = 1;
            $stmt  = $pdo->prepare('UPDATE products SET sort_order = ? WHERE id = ?');
            foreach ($order as $rid) {
                $stmt->execute([$i++, (int) $rid]);
            }
            $success = 'Ürün sıralaması güncellendi.';
        }

      } catch (Throwable $e) {
          error_log('[products.php POST] ' . $e->getMessage());
          $error = 'İşlem gerçekleştirilemedi. Lütfen <a href="migrate.php">migrasyonu</a> kontrol edin. ' .
                   (defined('APP_ENV') && APP_ENV === 'development' ? htmlspecialchars($e->getMessage()) : '');
      }
    }
}

/* ================================================================
   Sayfayı yükle
================================================================ */
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

try {
    $categories = $pdo->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC')->fetchAll();
} catch (Throwable $e) {
    $categories = [];
    $error = 'Kategori listesi alınamadı. Lütfen <a href="migrate.php">migrasyonu</a> çalıştırın.';
}

$editProduct = null;
$specTables  = [];
$regulations = [];
$extraImages = [];
$productDocs = [];

if ($editId) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$editId]);
        $editProduct = $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        $error = 'Ürün verisi alınamadı: ' . $e->getMessage();
    }

    if ($editProduct) {
        try {
            $stmt2 = $pdo->prepare('SELECT * FROM product_spec_tables WHERE product_id = ? ORDER BY sort_order ASC');
            $stmt2->execute([$editId]);
            $specTables = $stmt2->fetchAll();
            foreach ($specTables as &$t) {
                $stmt3 = $pdo->prepare('SELECT * FROM product_specs WHERE table_id = ? ORDER BY sort_order ASC');
                $stmt3->execute([$t['id']]);
                $t['rows'] = $stmt3->fetchAll();
            }
            unset($t);
        } catch (Throwable $e) {
            $specTables = [];
        }

        try {
            $stmt4 = $pdo->prepare('SELECT * FROM product_regulations WHERE product_id = ? ORDER BY sort_order ASC');
            $stmt4->execute([$editId]);
            $regulations = $stmt4->fetchAll();
        } catch (Throwable $e) {
            $regulations = [];
        }

        try {
            $stmt5 = $pdo->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC');
            $stmt5->execute([$editId]);
            $extraImages = $stmt5->fetchAll();
        } catch (Throwable $e) {
            $extraImages = [];
        }

        try {
            $stmt6 = $pdo->prepare('SELECT * FROM product_documents WHERE product_id = ? ORDER BY sort_order ASC');
            $stmt6->execute([$editId]);
            $productDocs = $stmt6->fetchAll();
        } catch (Throwable $e) {
            $productDocs = [];
        }

        // Mevcut çevirileri yükle
        try {
            $stmtTr = $pdo->prepare('SELECT * FROM product_translations WHERE product_id = ?');
            $stmtTr->execute([$editId]);
            $prodTranslations = [];
            foreach ($stmtTr->fetchAll() as $tr) {
                $prodTranslations[$tr['language']] = $tr;
            }
        } catch (Throwable $e) {
            $prodTranslations = [];
        }
    }
}
$prodTranslations = $prodTranslations ?? [];

// Filtre & arama
$filterCat    = isset($_GET['cat'])    ? (int)$_GET['cat']        : 0;
$filterSearch = isset($_GET['search']) ? trim($_GET['search'])     : '';
$filterStatus = $_GET['status'] ?? '';

try {
    $whereParts = [];
    $whereParams = [];
    if ($filterCat > 0) {
        $whereParts[] = 'p.category_id = :fcat';
        $whereParams[':fcat'] = $filterCat;
    }
    if ($filterSearch !== '') {
        $whereParts[] = '(p.name LIKE :fsearch OR p.code LIKE :fsearch2)';
        $whereParams[':fsearch']  = '%' . $filterSearch . '%';
        $whereParams[':fsearch2'] = '%' . $filterSearch . '%';
    }
    if ($filterStatus === 'active') {
        $whereParts[] = 'p.is_active = 1';
    } elseif ($filterStatus === 'passive') {
        $whereParts[] = 'p.is_active = 0';
    }
    $whereSQL = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';
    $stmt = $pdo->prepare("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id $whereSQL ORDER BY p.sort_order ASC, p.name ASC");
    $stmt->execute($whereParams);
    $products = $stmt->fetchAll();
} catch (Throwable $e) {
    $products = [];
    $error = 'Ürün listesi alınamadı. Lütfen <a href="migrate.php">migrasyonu</a> çalıştırın. Hata: ' . htmlspecialchars($e->getMessage());
}

$token = csrf_token();

include __DIR__ . '/partials_header.php';
?>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<div class="row">
    <!-- SOL: Ürün Listesi -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Ürünler (<?= count($products) ?>)</strong>
                    <a href="products.php" class="btn btn-sm btn-primary">+ Yeni Ürün</a>
                </div>
                <!-- Filtre satırı -->
                <form method="get" class="row g-2">
                    <div class="col-sm-4">
                        <input type="text" name="search" class="form-control form-control-sm"
                               placeholder="Ürün adı veya kodu..."
                               value="<?= e($filterSearch) ?>">
                    </div>
                    <div class="col-sm-3">
                        <select name="cat" class="form-select form-select-sm">
                            <option value="">Tüm Kategoriler</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= e((string)$c['id']) ?>"
                                    <?= $filterCat === (int)$c['id'] ? 'selected' : '' ?>>
                                    <?= e($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Tüm Durumlar</option>
                            <option value="active"  <?= $filterStatus === 'active'  ? 'selected' : '' ?>>Aktif</option>
                            <option value="passive" <?= $filterStatus === 'passive' ? 'selected' : '' ?>>Pasif</option>
                        </select>
                    </div>
                    <div class="col-sm-2">
                        <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Filtrele</button>
                    </div>
                    <?php if ($filterCat || $filterSearch || $filterStatus): ?>
                        <div class="col-12">
                            <a href="products.php" class="btn btn-sm btn-link p-0 text-danger">
                                <i class="bi bi-x-circle me-1"></i>Filtreyi Temizle
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
            <div class="card-body p-0">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                    <input type="hidden" name="save_order" value="1">
                    <ul class="list-group list-group-flush" id="prod-list">
                        <?php foreach ($products as $product): ?>
                            <li class="list-group-item d-flex align-items-center justify-content-between py-2">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="drag-handle text-muted" style="cursor:grab;">
                                        <i class="bi bi-grip-vertical"></i>
                                    </span>
                                    <?php if (!empty($product['main_image'])): ?>
                                        <img src="<?= e('../' . $product['main_image']) ?>" height="36" class="rounded border">
                                    <?php endif; ?>
                                    <div>
                                        <div><?= e($product['name']) ?></div>
                                        <div class="small text-muted">
                                            <?= e($product['cat_name'] ?? '') ?>
                                            <?php if ($product['code']): ?> · <?= e($product['code']) ?><?php endif; ?>
                                        </div>
                                        <?php if (!(bool)$product['is_active']): ?>
                                            <span class="badge bg-secondary-subtle text-secondary border small">Pasif</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="?edit=<?= e((string) $product['id']) ?>" class="btn btn-sm btn-outline-secondary">Düzenle</a>
                                    <button type="submit" name="delete_product" value="1" class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Ürünü silmek istediğinize emin misiniz?')">
                                        <input type="hidden" name="id" value="<?= e((string) $product['id']) ?>">
                                        Sil
                                    </button>
                                    <input type="hidden" name="order[]" value="<?= e((string) $product['id']) ?>">
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="p-3 text-end border-top">
                        <button type="submit" class="btn btn-primary btn-sm">Sıralamayı Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SAĞ: Ürün Ekle/Düzenle Formu -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <strong><?= isset($editProduct) && $editProduct ? 'Ürün Düzenle' : 'Yeni Ürün Ekle' ?></strong>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                    <input type="hidden" name="save_product" value="1">
                    <?php if (isset($editProduct) && $editProduct): ?>
                        <input type="hidden" name="id" value="<?= e((string) $editProduct['id']) ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Ürün Adı <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required
                               value="<?= e(isset($editProduct) ? ($editProduct['name'] ?? '') : '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ürün Kodu</label>
                        <input type="text" name="code" class="form-control"
                               value="<?= e(isset($editProduct) ? ($editProduct['code'] ?? '') : '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kategori <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Seç...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= e((string) $cat['id']) ?>"
                                    <?= (isset($editProduct) && isset($editProduct['category_id']) && (int)$editProduct['category_id'] === (int)$cat['id']) ? 'selected' : '' ?>>
                                    <?= e($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kısa Açıklama</label>
                        <textarea name="short_description" class="form-control" rows="2"><?= e(isset($editProduct) ? ($editProduct['short_description'] ?? '') : '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Uzun Açıklama</label>
                        <textarea name="description" class="form-control tinymce" rows="5"><?= e(isset($editProduct) ? ($editProduct['description'] ?? '') : '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ana Görsel</label>
                        <?php if (!empty($editProduct) && !empty($editProduct['main_image'])): ?>
                            <div class="mb-2">
                                <img src="<?= e('../' . $editProduct['main_image']) ?>" height="60" class="rounded border">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="main_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ek Görseller</label>
                        <input type="file" name="extra_images[]" class="form-control" multiple accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">Birden fazla seçebilirsin.</div>
                        <?php if (!empty($extraImages)): ?>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <?php foreach ($extraImages as $img): ?>
                                    <img src="<?= e('../' . $img['image']) ?>" height="48" class="rounded border">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="prod_active"
                            <?= (!isset($editProduct) || !isset($editProduct['is_active']) || (int)$editProduct['is_active'] === 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="prod_active">Aktif</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <?= isset($editProduct) && $editProduct ? 'Güncelle' : 'Ekle' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (isset($editProduct) && $editProduct): ?>
<!-- Çeviri Yönetimi -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <i class="bi bi-translate text-primary"></i>
                <strong>İçerik Çevirileri</strong>
                <small class="text-muted ms-2">— EN (varsayılan), DE, IT, FR</small>
            </div>
            <div class="card-body">
                <!-- Dil sekmeleri -->
                <ul class="nav nav-tabs mb-3" id="transLangTabs">
                    <?php foreach (['en','de','it','fr'] as $_tl): ?>
                        <li class="nav-item">
                            <button class="nav-link <?= $_tl === 'en' ? 'active' : '' ?>"
                                    data-bs-toggle="tab"
                                    data-bs-target="#ptrans-<?= $_tl ?>"
                                    type="button">
                                <?= strtoupper($_tl) ?>
                                <?php if (!empty($prodTranslations[$_tl])): ?>
                                    <span class="badge bg-success ms-1" style="font-size:.6rem;">✓</span>
                                <?php endif; ?>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="tab-content">
                    <?php foreach (['en','de','it','fr'] as $_tl): ?>
                        <?php $_tr = $prodTranslations[$_tl] ?? []; ?>
                        <div class="tab-pane <?= $_tl === 'en' ? 'show active' : '' ?>" id="ptrans-<?= $_tl ?>">
                            <form method="post">
                                <input type="hidden" name="csrf_token"           value="<?= e($token) ?>">
                                <input type="hidden" name="save_product_translation" value="1">
                                <input type="hidden" name="product_id"           value="<?= e((string) $editProduct['id']) ?>">
                                <input type="hidden" name="trans_lang"           value="<?= e($_tl) ?>">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Ürün Adı (<?= strtoupper($_tl) ?>)</label>
                                        <input type="text" name="trans_name" class="form-control form-control-sm"
                                               value="<?= e($_tr['name'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Slug</label>
                                        <input type="text" name="trans_slug" class="form-control form-control-sm"
                                               value="<?= e($_tr['slug'] ?? '') ?>" placeholder="Boş bırakılırsa addan otomatik üretilir">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Kısa Açıklama</label>
                                        <textarea name="trans_short" class="form-control form-control-sm" rows="2"><?= e($_tr['short_description'] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Uzun Açıklama</label>
                                        <textarea name="trans_desc" class="form-control form-control-sm" rows="4"><?= e($_tr['description'] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <?= strtoupper($_tl) ?> Çevirisini Kaydet
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Teknik Spec Tabloları -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><strong>Teknik Özellikler Tabloları</strong></div>
            <div class="card-body">
                <!-- Tablo ekle -->
                <form method="post" class="mb-3">
                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                    <input type="hidden" name="add_spec_table" value="1">
                    <input type="hidden" name="product_id" value="<?= e((string) $editProduct['id']) ?>">
                    <div class="input-group">
                        <input type="text" name="spec_table_title" class="form-control form-control-sm" placeholder="Tablo başlığı (opsiyonel)">
                        <button type="submit" class="btn btn-sm btn-outline-primary">+ Tablo Ekle</button>
                    </div>
                </form>

                <?php if (!empty($specTables)): ?>
                    <?php foreach ($specTables as $table): ?>
                        <div class="border rounded p-2 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-semibold small">
                                    <?= $table['title'] ? e($table['title']) : '(Başlıksız tablo)' ?>
                                    <span class="badge bg-secondary-subtle text-secondary ms-1">#<?= e((string) $table['id']) ?></span>
                                </div>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                    <input type="hidden" name="delete_spec_table" value="1">
                                    <input type="hidden" name="table_id" value="<?= e((string) $table['id']) ?>">
                                    <input type="hidden" name="product_id" value="<?= e((string) $editProduct['id']) ?>">
                                    <button type="submit" class="btn btn-xs btn-sm btn-danger py-0 px-2"
                                            onclick="return confirm('Tabloyu ve tüm satırlarını silmek istediğinize emin misiniz?')">
                                        <i class="bi bi-trash3"></i> Tabloyu Sil
                                    </button>
                                </form>
                            </div>
                            <table class="table table-sm mb-2">
                                <thead><tr><th>Özellik</th><th>Değer</th><th></th></tr></thead>
                                <tbody>
                                    <?php foreach ($table['rows'] as $row): ?>
                                        <tr class="<?= !(bool)$row['is_active'] ? 'table-secondary' : '' ?>">
                                            <td><?= e($row['label']) ?></td>
                                            <td><?= e($row['value']) ?></td>
                                            <td class="text-end">
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                                    <input type="hidden" name="toggle_spec_row" value="1">
                                                    <input type="hidden" name="spec_id" value="<?= e((string) $row['id']) ?>">
                                                    <button class="btn btn-sm <?= (bool)$row['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?> py-0">
                                                        <?= (bool)$row['is_active'] ? 'Pasif' : 'Aktif' ?>
                                                    </button>
                                                </form>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                                    <input type="hidden" name="delete_spec_row" value="1">
                                                    <input type="hidden" name="spec_id" value="<?= e((string) $row['id']) ?>">
                                                    <button class="btn btn-sm btn-outline-danger py-0"
                                                            onclick="return confirm('Satırı silmek istediğinize emin misiniz?')">
                                                        Sil
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <!-- Satır ekle -->
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                <input type="hidden" name="add_spec_row" value="1">
                                <input type="hidden" name="table_id" value="<?= e((string) $table['id']) ?>">
                                <div class="row g-1">
                                    <div class="col-5">
                                        <input type="text" name="spec_label" class="form-control form-control-sm" placeholder="Özellik" required>
                                    </div>
                                    <div class="col-5">
                                        <input type="text" name="spec_value" class="form-control form-control-sm" placeholder="Değer" required>
                                    </div>
                                    <div class="col-2">
                                        <button type="submit" class="btn btn-sm btn-primary w-100">+</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted small">Henüz tablo yok. Yukarıdan ekle.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Regülasyonlar -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><strong>Regülasyonlar &amp; Sertifikalar</strong></div>
            <div class="card-body">
                <!-- Regülasyon ekle -->
                <form method="post" enctype="multipart/form-data" class="mb-3">
                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                    <input type="hidden" name="add_regulation" value="1">
                    <input type="hidden" name="product_id" value="<?= e((string) $editProduct['id']) ?>">
                    <div class="mb-2">
                        <input type="text" name="reg_title" class="form-control form-control-sm" placeholder="Sertifika adı (zorunlu)" required>
                    </div>
                    <div class="mb-2">
                        <input type="file" name="reg_icon" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp,image/svg+xml">
                        <div class="form-text">İkon görseli (opsiyonel). PNG/SVG önerilir.</div>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-primary w-100">+ Regülasyon Ekle</button>
                </form>

                <?php if (!empty($regulations)): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($regulations as $reg): ?>
                            <li class="list-group-item d-flex align-items-center justify-content-between py-2 <?= !(bool)$reg['is_active'] ? 'list-group-item-secondary' : '' ?>">
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (!empty($reg['icon'])): ?>
                                        <img src="<?= e('../' . $reg['icon']) ?>" height="24" class="rounded">
                                    <?php endif; ?>
                                    <div>
                                        <div class="small"><?= e($reg['title']) ?></div>
                                        <?php if (!(bool)$reg['is_active']): ?>
                                            <span class="badge bg-secondary-subtle text-secondary border small">Pasif</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="d-flex gap-1">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                        <input type="hidden" name="toggle_regulation" value="1">
                                        <input type="hidden" name="reg_id" value="<?= e((string) $reg['id']) ?>">
                                        <button class="btn btn-sm <?= (bool)$reg['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?> py-0">
                                            <?= (bool)$reg['is_active'] ? 'Pasif Yap' : 'Aktif Yap' ?>
                                        </button>
                                    </form>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                        <input type="hidden" name="delete_regulation" value="1">
                                        <input type="hidden" name="reg_id" value="<?= e((string) $reg['id']) ?>">
                                        <button class="btn btn-sm btn-outline-danger py-0"
                                                onclick="return confirm('Bu regülasyonu silmek istediğinize emin misiniz?')">Sil</button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted small">Henüz regülasyon yok. Yukarıdan ekle.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (isset($editProduct) && $editProduct): ?>
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><strong>Dokümanlar / PDF</strong></div>
            <div class="card-body">
                <!-- Doküman ekle formu -->
                <form method="post" enctype="multipart/form-data" class="row g-2 mb-3 align-items-end">
                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                    <input type="hidden" name="add_document" value="1">
                    <input type="hidden" name="product_id" value="<?= e((string) $editProduct['id']) ?>">
                    <div class="col-md-4">
                        <label class="form-label form-label-sm">Başlık <span class="text-danger">*</span></label>
                        <input type="text" name="doc_title" class="form-control form-control-sm" placeholder="Ör: Teknik Katalog" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label form-label-sm">Dosya (PDF/Word/Excel, maks 10 MB) <span class="text-danger">*</span></label>
                        <input type="file" name="doc_file" class="form-control form-control-sm"
                               accept=".pdf,.doc,.docx,.xls,.xlsx" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-sm btn-outline-primary w-100">+ Doküman Ekle</button>
                    </div>
                </form>

                <?php if (!empty($productDocs)): ?>
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Başlık</th>
                                <th>Dosya</th>
                                <th>Durum</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productDocs as $doc): ?>
                                <tr class="<?= !(bool)$doc['is_active'] ? 'table-secondary' : '' ?>">
                                    <td><?= e($doc['title']) ?></td>
                                    <td>
                                        <a href="<?= e('../' . $doc['file_path']) ?>" target="_blank" class="small text-decoration-none">
                                            <i class="bi bi-file-earmark-arrow-down me-1"></i>
                                            <?= e(basename($doc['file_path'])) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ((bool)$doc['is_active']): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle small">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border small">Pasif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                            <input type="hidden" name="toggle_document" value="1">
                                            <input type="hidden" name="doc_id" value="<?= e((string) $doc['id']) ?>">
                                            <button class="btn btn-sm <?= (bool)$doc['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?> py-0">
                                                <?= (bool)$doc['is_active'] ? 'Pasif Yap' : 'Aktif Yap' ?>
                                            </button>
                                        </form>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                            <input type="hidden" name="delete_document" value="1">
                                            <input type="hidden" name="doc_id" value="<?= e((string) $doc['id']) ?>">
                                            <button class="btn btn-sm btn-outline-danger py-0"
                                                    onclick="return confirm('Bu dokümanı silmek istediğinize emin misiniz?')">Sil</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-muted small">Henüz doküman eklenmemiş.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/partials_footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
    var prodList = document.getElementById('prod-list');
    if (prodList) {
        Sortable.create(prodList, { handle: '.drag-handle', animation: 150 });
    }
</script>
