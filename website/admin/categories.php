<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload_helper.php';

require_admin_login();

$pdo     = db();
$error   = null;
$success = null;

$uploadDir  = __DIR__ . '/../assets/uploads/categories/';
$uploadBase = 'assets/uploads/categories/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($token)) {
        $error = 'Güvenlik doğrulaması başarısız.';
    } else {
        if (isset($_POST['save_category'])) {
            $id               = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $name             = trim($_POST['name'] ?? '');
            $shortDesc        = trim($_POST['short_description'] ?? '');
            $desc             = trim($_POST['description'] ?? '');
            $parentId         = (int) ($_POST['parent_id'] ?? 0);
            $active           = isset($_POST['is_active']) ? 1 : 0;

            if ($name === '') {
                $error = 'Kategori adı zorunludur.';
            } else {
                // Slug
                $slug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($name));
                $slug = trim($slug, '-');

                // Görsel
                $imagePath = null;
                if (!empty($_FILES['image']['name'])) {
                    $fname = upload_file($_FILES['image'], $uploadDir, ['image/jpeg', 'image/png', 'image/webp']);
                    if ($fname) {
                        $imagePath = $uploadBase . $fname;
                    } else {
                        $error = 'Görsel yüklenemedi. JPG/PNG/WEBP maks 5 MB.';
                    }
                }

                if (!$error) {
                    try {
                        if ($id > 0) {
                            $sql = 'UPDATE categories SET name = :name, slug = :slug, short_description = :sdesc, description = :desc,
                                    parent_id = :parent, is_active = :active' . ($imagePath ? ', image = :image' : '') . ' WHERE id = :id';
                            $params = [
                                ':name'   => $name,
                                ':slug'   => $slug,
                                ':sdesc'  => $shortDesc ?: null,
                                ':desc'   => $desc ?: null,
                                ':parent' => $parentId ?: null,
                                ':active' => $active,
                                ':id'     => $id,
                            ];
                            if ($imagePath) {
                                $params[':image'] = $imagePath;
                            }
                            $pdo->prepare($sql)->execute($params);
                            $success = 'Kategori güncellendi.';
                        } else {
                            $sort = (int) $pdo->query('SELECT IFNULL(MAX(sort_order),0)+1 FROM categories')->fetchColumn();
                            $stmt = $pdo->prepare('INSERT INTO categories (name, slug, short_description, description, image, parent_id, sort_order, is_active)
                                                   VALUES (:name, :slug, :sdesc, :desc, :image, :parent, :sort, :active)');
                            $stmt->execute([
                                ':name'   => $name,
                                ':slug'   => $slug,
                                ':sdesc'  => $shortDesc ?: null,
                                ':desc'   => $desc ?: null,
                                ':image'  => $imagePath,
                                ':parent' => $parentId ?: null,
                                ':sort'   => $sort,
                                ':active' => $active,
                            ]);
                            $success = 'Yeni kategori eklendi.';
                        }
                    } catch (Throwable $e) {
                        error_log('[categories.php save_category] ' . $e->getMessage());
                        $error = 'Kategori kaydedilemedi. Lütfen <a href="migrate.php">migrasyonu</a> kontrol edin.';
                    }
                }
            }
        } elseif (isset($_POST['delete_category'])) {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $pdo->prepare('DELETE FROM categories WHERE id = :id')->execute([':id' => $id]);
                    $success = 'Kategori silindi.';
                } catch (Throwable $e) {
                    error_log('[categories.php delete_category] ' . $e->getMessage());
                    $error = 'Kategori silinemedi.';
                }
            }
        } elseif (isset($_POST['save_category_translation'])) {
            require_once __DIR__ . '/../includes/functions.php';
            $cid  = (int) ($_POST['category_id'] ?? 0);
            $lang = trim($_POST['trans_lang'] ?? '');
            if ($cid > 0 && in_array($lang, ['en','de','it','fr'], true)) {
                $name = trim($_POST['trans_name'] ?? '');
                $slug = make_slug($name ?: trim($_POST['trans_slug'] ?? ''));
                if (!$slug) $slug = 'cat-' . $cid . '-' . $lang;
                save_translation('category_translations', 'category_id', $cid, $lang, [
                    'name'              => $name,
                    'slug'              => $slug,
                    'short_description' => trim($_POST['trans_short'] ?? '') ?: null,
                    'description'       => trim($_POST['trans_desc']  ?? '') ?: null,
                ]);
                $success = strtoupper($lang) . ' çevirisi kaydedildi.';
            }
        } elseif (isset($_POST['save_order'])) {
            $order = $_POST['order'] ?? [];
            $i     = 1;
            try {
                $stmt  = $pdo->prepare('UPDATE categories SET sort_order = :sort WHERE id = :id');
                foreach ($order as $rid) {
                    $stmt->execute([':sort' => $i++, ':id' => (int) $rid]);
                }
                $success = 'Sıralama güncellendi.';
            } catch (Throwable $e) {
                error_log('[categories.php save_order] ' . $e->getMessage());
                $error = 'Sıralama kaydedilemedi.';
            }
        }
    }
}

try {
    $categories = $pdo->query('SELECT * FROM categories ORDER BY sort_order ASC, name ASC')->fetchAll();
} catch (Throwable $e) {
    $categories = [];
    $error = 'Kategori listesi alınamadı. Lütfen <a href="migrate.php">migrasyonu</a> çalıştırın.';
}
$token = csrf_token();

include __DIR__ . '/partials_header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Kategoriler</strong>
                <small class="text-muted">Sürükle-bırak ile sıralayabilirsin.</small>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><?= $error ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success py-2"><?= $success ?></div>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                    <input type="hidden" name="save_order" value="1">
                    <ul class="list-group" id="cat-list">
                        <?php foreach ($categories as $cat): ?>
                            <li class="list-group-item d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="drag-handle text-muted" style="cursor:grab;">
                                        <i class="bi bi-grip-vertical"></i>
                                    </span>
                                    <?php if (!empty($cat['image'])): ?>
                                        <img src="<?= e('../' . $cat['image']) ?>" alt="" height="36" class="rounded">
                                    <?php endif; ?>
                                    <div>
                                        <div><?= e($cat['name']) ?></div>
                                        <div class="small text-muted"><?= e($cat['slug']) ?></div>
                                        <?php if (!(bool)$cat['is_active']): ?>
                                            <span class="badge bg-secondary-subtle text-secondary border small">Pasif</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="?edit=<?= e((string) $cat['id']) ?>" class="btn btn-sm btn-outline-secondary">Düzenle</a>
                                    <button type="submit" name="delete_category" value="1" class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Bu kategoriyi silmek istediğinize emin misiniz?')">
                                        <input type="hidden" name="id" value="<?= e((string) $cat['id']) ?>">
                                        Sil
                                    </button>
                                    <input type="hidden" name="order[]" value="<?= e((string) $cat['id']) ?>">
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="mt-3 text-end">
                        <button type="submit" class="btn btn-primary btn-sm">Sıralamayı Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <?php
        $editId  = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        $editCat = null;
        $catTranslations = [];
        if ($editId) {
            foreach ($categories as $cat) {
                if ((int) $cat['id'] === $editId) {
                    $editCat = $cat;
                    break;
                }
            }
            if ($editCat) {
                try {
                    $stmtCTr = $pdo->prepare('SELECT * FROM category_translations WHERE category_id = ?');
                    $stmtCTr->execute([$editId]);
                    foreach ($stmtCTr->fetchAll() as $tr) {
                        $catTranslations[$tr['language']] = $tr;
                    }
                } catch (Throwable $e) {
                    $catTranslations = [];
                }
            }
        }
        ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <strong><?= $editCat ? 'Kategori Düzenle' : 'Yeni Kategori' ?></strong>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                    <input type="hidden" name="save_category" value="1">
                    <?php if ($editCat): ?>
                        <input type="hidden" name="id" value="<?= e((string) $editCat['id']) ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Kategori Adı <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control"
                               value="<?= e($editCat['name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kısa Açıklama</label>
                        <input type="text" name="short_description" class="form-control"
                               value="<?= e($editCat['short_description'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Uzun Açıklama</label>
                        <textarea name="description" class="form-control" rows="3"><?= e($editCat['description'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Üst Kategori</label>
                        <select name="parent_id" class="form-select">
                            <option value="0">Yok</option>
                            <?php foreach ($categories as $cat): ?>
                                <?php if (!$editCat || (int)$cat['id'] !== $editId): ?>
                                    <option value="<?= e((string) $cat['id']) ?>"
                                        <?= isset($editCat['parent_id']) && (int)$editCat['parent_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Görsel</label>
                        <?php if (!empty($editCat['image'])): ?>
                            <div class="mb-2">
                                <img src="<?= e('../' . $editCat['image']) ?>" alt="" height="60" class="rounded border">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">JPG, PNG veya WEBP. Maks 5 MB.</div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                            <?= !isset($editCat['is_active']) || (int)$editCat['is_active'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Aktif</label>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <?= $editCat ? 'Güncelle' : 'Ekle' ?>
                    </button>
                    <?php if ($editCat): ?>
                        <a href="categories.php" class="btn btn-link">İptal</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($editCat): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <i class="bi bi-translate text-primary"></i>
                <strong>İçerik Çevirileri</strong>
                <small class="text-muted ms-2">— EN, DE, IT, FR</small>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs mb-3">
                    <?php foreach (['en','de','it','fr'] as $_ctl): ?>
                        <li class="nav-item">
                            <button class="nav-link <?= $_ctl === 'en' ? 'active' : '' ?>"
                                    data-bs-toggle="tab"
                                    data-bs-target="#ctrans-<?= $_ctl ?>"
                                    type="button">
                                <?= strtoupper($_ctl) ?>
                                <?php if (!empty($catTranslations[$_ctl])): ?>
                                    <span class="badge bg-success ms-1" style="font-size:.6rem;">✓</span>
                                <?php endif; ?>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="tab-content">
                    <?php foreach (['en','de','it','fr'] as $_ctl): ?>
                        <?php $_ctr = $catTranslations[$_ctl] ?? []; ?>
                        <div class="tab-pane <?= $_ctl === 'en' ? 'show active' : '' ?>" id="ctrans-<?= $_ctl ?>">
                            <form method="post">
                                <input type="hidden" name="csrf_token"                    value="<?= e($token) ?>">
                                <input type="hidden" name="save_category_translation"     value="1">
                                <input type="hidden" name="category_id"                   value="<?= e((string) $editCat['id']) ?>">
                                <input type="hidden" name="trans_lang"                    value="<?= e($_ctl) ?>">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Kategori Adı (<?= strtoupper($_ctl) ?>)</label>
                                        <input type="text" name="trans_name" class="form-control form-control-sm"
                                               value="<?= e($_ctr['name'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Slug</label>
                                        <input type="text" name="trans_slug" class="form-control form-control-sm"
                                               value="<?= e($_ctr['slug'] ?? '') ?>" placeholder="Boş bırakılırsa addan üretilir">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Kısa Açıklama</label>
                                        <input type="text" name="trans_short" class="form-control form-control-sm"
                                               value="<?= e($_ctr['short_description'] ?? '') ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Uzun Açıklama</label>
                                        <textarea name="trans_desc" class="form-control form-control-sm" rows="3"><?= e($_ctr['description'] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <?= strtoupper($_ctl) ?> Çevirisini Kaydet
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
<?php endif; ?>

<?php include __DIR__ . '/partials_footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
    Sortable.create(document.getElementById('cat-list'), {
        handle: '.drag-handle',
        animation: 150,
    });
</script>
