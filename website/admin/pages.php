<?php

require_once __DIR__ . '/../includes/auth.php';

require_admin_login();

$pdo     = db();
$error   = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Güvenlik doğrulaması başarısız.';
    } else {
            if (isset($_POST['save_page'])) {
            $id         = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $slug       = trim($_POST['slug'] ?? '');
            $title      = trim($_POST['title'] ?? '');
            $content    = $_POST['content'] ?? '';
            $metaDesc   = trim($_POST['meta_description'] ?? '');
            $isActive   = isset($_POST['is_active']) ? 1 : 0;

            // Slug doğrulaması
            $slug = preg_replace('/[^a-z0-9\-]/', '', mb_strtolower($slug, 'UTF-8'));
            $slug = trim($slug, '-');

            if (!$title) {
                $error = 'Sayfa başlığı zorunludur.';
            } elseif (!$slug) {
                $error = 'Geçerli bir slug girilmesi zorunludur (yalnızca a-z, 0-9, -).';
            } else {
                // Slug çakışma kontrolü
                $checkStmt = $pdo->prepare('SELECT id FROM pages WHERE slug = :slug AND id <> :id LIMIT 1');
                $checkStmt->execute([':slug' => $slug, ':id' => $id]);
                if ($checkStmt->fetch()) {
                    $error = "Bu slug ($slug) zaten kullanımda. Farklı bir slug seçin.";
                } else {
                    $bannerImage = null;
                    if (!empty($_FILES['banner_image']['name'])) {
                        $uploadDir = __DIR__ . '/../assets/uploads/';
                        $fn        = upload_file(
                            $_FILES['banner_image'],
                            $uploadDir,
                            ['image/jpeg', 'image/png', 'image/webp'],
                            5 * 1024 * 1024
                        );
                        if ($fn) {
                            $bannerImage = 'assets/uploads/' . $fn;
                        } else {
                            $error = 'Banner görseli yüklenemedi. JPG/PNG/WEBP en fazla 5 MB olmalı.';
                        }
                    }

                    if (!$error) {
                        if ($id > 0) {
                            if ($bannerImage !== null) {
                                $stmt = $pdo->prepare('UPDATE pages SET slug = :slug, title = :title, content = :content, meta_description = :meta, banner_image = :bimg, banner_title = :btitle, is_active = :active WHERE id = :id');
                            } else {
                                $stmt = $pdo->prepare('UPDATE pages SET slug = :slug, title = :title, content = :content, meta_description = :meta, banner_title = :btitle, is_active = :active WHERE id = :id');
                            }
                            $params = [
                                ':slug'   => $slug,
                                ':title'  => $title,
                                ':content'=> $content,
                                ':meta'   => $metaDesc,
                                ':btitle' => trim($_POST['banner_title'] ?? ''),
                                ':active' => $isActive,
                                ':id'     => $id,
                            ];
                            if ($bannerImage !== null) {
                                $params[':bimg'] = $bannerImage;
                            }
                            $stmt->execute($params);
                            $success = 'Sayfa güncellendi.';
                        } else {
                            $sort   = (int) $pdo->query('SELECT IFNULL(MAX(sort_order),0)+1 FROM pages')->fetchColumn();
                            $stmt   = $pdo->prepare('INSERT INTO pages (slug, title, content, meta_description, banner_image, banner_title, is_active, sort_order) VALUES (:slug, :title, :content, :meta, :bimg, :btitle, :active, :sort)');
                            $stmt->execute([
                                ':slug'   => $slug,
                                ':title'  => $title,
                                ':content'=> $content,
                                ':meta'   => $metaDesc,
                                ':bimg'   => $bannerImage,
                                ':btitle' => trim($_POST['banner_title'] ?? ''),
                                ':active' => $isActive,
                                ':sort'   => $sort,
                            ]);
                            $success = 'Sayfa eklendi.';
                        }
                    }
                }
            }
        } elseif (isset($_POST['delete_page'])) {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare('DELETE FROM pages WHERE id = :id')->execute([':id' => $id]);
                $success = 'Sayfa silindi.';
            }
        } elseif (isset($_POST['save_order'])) {
            $ids  = $_POST['order'] ?? [];
            $i    = 1;
            $stmt = $pdo->prepare('UPDATE pages SET sort_order = :sort WHERE id = :id');
            foreach ($ids as $id) {
                $stmt->execute([':sort' => $i++, ':id' => (int) $id]);
            }
            $success = 'Sıralama güncellendi.';
        }
    }
}

$pages  = $pdo->query('SELECT * FROM pages ORDER BY sort_order ASC, id ASC')->fetchAll();
$token  = csrf_token();

$editId   = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editPage = null;
foreach ($pages as $p) {
    if ((int) $p['id'] === $editId) {
        $editPage = $p;
        break;
    }
}

include __DIR__ . '/partials_header.php';
?>

<div class="row">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Kurumsal Sayfalar</strong>
                <a href="pages.php" class="btn btn-sm btn-primary">+ Yeni Sayfa</a>
            </div>
            <div class="card-body p-0">
                <?php if ($error): ?>
                    <div class="alert alert-danger mx-3 mt-3 py-2"><?= e($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success mx-3 mt-3 py-2"><?= e($success) ?></div>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                    <input type="hidden" name="save_order" value="1">
                    <ul class="list-group list-group-flush" id="pages-list">
                        <?php if (empty($pages)): ?>
                            <li class="list-group-item text-muted small">Henüz sayfa eklenmemiş.</li>
                        <?php endif; ?>
                        <?php foreach ($pages as $page): ?>
                            <li class="list-group-item d-flex align-items-center justify-content-between" data-id="<?= e((string) $page['id']) ?>">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="drag-handle text-muted" style="cursor:grab;">
                                        <i class="bi bi-grip-vertical"></i>
                                    </span>
                                    <div>
                                        <div><?= e($page['title']) ?></div>
                                        <div class="small text-muted">
                                            /page.php?slug=<?= e($page['slug']) ?>
                                            <?php if (!(bool)$page['is_active']): ?>
                                                &nbsp;<span class="badge bg-secondary-subtle text-secondary border small">Pasif</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="?edit=<?= e((string) $page['id']) ?>" class="btn btn-sm btn-outline-secondary">Düzenle</a>
                                    <a href="../page.php?slug=<?= e($page['slug']) ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                    <button type="submit" name="delete_page" value="1" class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Bu sayfayı silmek istediğinize emin misiniz?')">
                                        <input type="hidden" name="id" value="<?= e((string) $page['id']) ?>">
                                        Sil
                                    </button>
                                    <input type="hidden" name="order[]" value="<?= e((string) $page['id']) ?>">
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (!empty($pages)): ?>
                        <div class="p-3 text-end">
                            <button type="submit" class="btn btn-sm btn-primary">Sıralamayı Kaydet</button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <strong><?= $editPage ? 'Sayfayı Düzenle' : 'Yeni Sayfa Ekle' ?></strong>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                    <input type="hidden" name="save_page" value="1">
                    <?php if ($editPage): ?>
                        <input type="hidden" name="id" value="<?= e((string) $editPage['id']) ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Başlık <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required
                               value="<?= e($editPage['title'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Slug <span class="text-danger">*</span></label>
                        <input type="text" name="slug" class="form-control" required
                               value="<?= e($editPage['slug'] ?? '') ?>"
                               placeholder="hakkimizda, iletisim, kariyer...">
                        <div class="form-text">URL'de kullanılır. Sadece a-z, 0-9 ve - içerebilir.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">İçerik</label>
                        <textarea name="content" id="page_content" class="form-control" rows="10"><?= htmlspecialchars($editPage['content'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        <div class="form-text">HTML içerik yazabilirsiniz.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Banner Görseli</label>
                        <?php if (!empty($editPage['banner_image'])): ?>
                            <div class="mb-2">
                                <img src="<?= e('../' . $editPage['banner_image']) ?>" alt="" class="img-fluid rounded border">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="banner_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">Üstte görünecek geniş banner. JPG/PNG/WEBP, maks 5 MB.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Banner Başlığı</label>
                        <input type="text" name="banner_title" class="form-control"
                               value="<?= e($editPage['banner_title'] ?? '') ?>">
                        <div class="form-text">Banner üzerinde büyük başlık olarak gösterilir (boş bırakılabilir).</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Meta Açıklama</label>
                        <textarea name="meta_description" class="form-control" rows="2" maxlength="300"><?= e($editPage['meta_description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                            <?= !isset($editPage['is_active']) || (int)($editPage['is_active']) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Bu sayfa aktif olsun</label>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <?= $editPage ? 'Güncelle' : 'Ekle' ?>
                    </button>
                    <?php if ($editPage): ?>
                        <a href="pages.php" class="btn btn-link">İptal</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials_footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var list = document.getElementById('pages-list');
        if (list) Sortable.create(list, { handle: '.drag-handle', animation: 150 });
    });
</script>
