<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload_helper.php';

require_admin_login();

$pdo     = db();
$error   = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Güvenlik doğrulaması başarısız.';
    } else {
            if (isset($_POST['save_page'])) {
            $id              = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $slug            = trim($_POST['slug'] ?? '');
            $title           = trim($_POST['title'] ?? '');
            $content         = $_POST['content'] ?? '';
            $metaDesc        = trim($_POST['meta_description'] ?? '');
            $isActive        = isset($_POST['is_active']) ? 1 : 0;
            $bannerOpacity   = max(0, min(100, (int) ($_POST['banner_opacity'] ?? 50)));
            $bannerBlur      = max(0, min(20,  (int) ($_POST['banner_blur'] ?? 0)));
            $bannerTitleColor= trim($_POST['banner_title_color'] ?? '#ffffff');
            $bannerTitleSize = trim($_POST['banner_title_size'] ?? '2rem');
            $bannerTitlePos  = in_array($_POST['banner_title_position'] ?? 'center', ['left','center','right']) ? $_POST['banner_title_position'] : 'center';

            // Slug doğrulaması
            $slug = preg_replace('/[^a-z0-9\-]/', '', mb_strtolower($slug, 'UTF-8'));
            $slug = trim($slug, '-');

            if (!$title) {
                $error = 'Sayfa başlığı zorunludur.';
            } elseif (!$slug) {
                $error = 'Geçerli bir slug girilmesi zorunludur (yalnızca a-z, 0-9, -).';
            } else {
                try {
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
                            $bannerTitle = trim($_POST['banner_title'] ?? '');
                            if ($id > 0) {
                                $imgSql = $bannerImage !== null ? ', banner_image = :bimg' : '';
                                $stmt = $pdo->prepare("UPDATE pages SET slug=:slug, title=:title, content=:content,
                                    meta_description=:meta, banner_title=:btitle{$imgSql},
                                    banner_opacity=:bopacity, banner_blur=:bblur,
                                    banner_title_color=:btcolor, banner_title_size=:btsize,
                                    banner_title_position=:btpos, is_active=:active WHERE id=:id");
                                $params = [
                                    ':slug'     => $slug,   ':title'    => $title,
                                    ':content'  => $content,':meta'     => $metaDesc,
                                    ':btitle'   => $bannerTitle,
                                    ':bopacity' => $bannerOpacity, ':bblur'   => $bannerBlur,
                                    ':btcolor'  => $bannerTitleColor,
                                    ':btsize'   => $bannerTitleSize,
                                    ':btpos'    => $bannerTitlePos,
                                    ':active'   => $isActive, ':id'     => $id,
                                ];
                                if ($bannerImage !== null) $params[':bimg'] = $bannerImage;
                                $stmt->execute($params);
                                $success = 'Sayfa güncellendi.';
                            } else {
                                $sort = (int) $pdo->query('SELECT IFNULL(MAX(sort_order),0)+1 FROM pages')->fetchColumn();
                                $stmt = $pdo->prepare('INSERT INTO pages
                                    (slug,title,content,meta_description,banner_image,banner_title,
                                     banner_opacity,banner_blur,banner_title_color,banner_title_size,
                                     banner_title_position,is_active,sort_order)
                                    VALUES(:slug,:title,:content,:meta,:bimg,:btitle,
                                           :bopacity,:bblur,:btcolor,:btsize,:btpos,:active,:sort)');
                                $stmt->execute([
                                    ':slug'     => $slug,    ':title'   => $title,
                                    ':content'  => $content, ':meta'    => $metaDesc,
                                    ':bimg'     => $bannerImage,
                                    ':btitle'   => $bannerTitle,
                                    ':bopacity' => $bannerOpacity, ':bblur'   => $bannerBlur,
                                    ':btcolor'  => $bannerTitleColor,
                                    ':btsize'   => $bannerTitleSize,
                                    ':btpos'    => $bannerTitlePos,
                                    ':active'   => $isActive, ':sort'   => $sort,
                                ]);
                                $success = 'Sayfa eklendi.';
                            }
                        }
                    }
                } catch (Throwable $e) {
                    error_log('[pages.php save_page] ' . $e->getMessage());
                    $error = 'Sayfa kaydedilemedi. Lütfen <a href="migrate.php">migrasyonu</a> kontrol edin.';
                }
            }
        } elseif (isset($_POST['delete_page'])) {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $pdo->prepare('DELETE FROM pages WHERE id = :id')->execute([':id' => $id]);
                    $success = 'Sayfa silindi.';
                } catch (Throwable $e) {
                    error_log('[pages.php delete_page] ' . $e->getMessage());
                    $error = 'Sayfa silinemedi.';
                }
            }
        } elseif (isset($_POST['save_page_translation'])) {
            require_once __DIR__ . '/../includes/functions.php';
            $pgid = (int) ($_POST['page_id'] ?? 0);
            $lang = trim($_POST['trans_lang'] ?? '');
            if ($pgid > 0 && in_array($lang, ['en','de','it','fr'], true)) {
                $pgTitle = trim($_POST['trans_title'] ?? '');
                $pgSlug  = make_slug($pgTitle ?: trim($_POST['trans_slug'] ?? ''));
                if (!$pgSlug) $pgSlug = 'page-' . $pgid . '-' . $lang;
                save_translation('page_translations', 'page_id', $pgid, $lang, [
                    'slug'             => $pgSlug,
                    'title'            => $pgTitle,
                    'content'          => trim($_POST['trans_content'] ?? '') ?: null,
                    'meta_description' => trim($_POST['trans_meta'] ?? '') ?: null,
                    'banner_title'     => trim($_POST['trans_banner_title'] ?? '') ?: null,
                ]);
                $success = strtoupper($lang) . ' çevirisi kaydedildi.';
            }
        } elseif (isset($_POST['save_order'])) {
            $ids  = $_POST['order'] ?? [];
            $i    = 1;
            try {
                $stmt = $pdo->prepare('UPDATE pages SET sort_order = :sort WHERE id = :id');
                foreach ($ids as $id) {
                    $stmt->execute([':sort' => $i++, ':id' => (int) $id]);
                }
                $success = 'Sıralama güncellendi.';
            } catch (Throwable $e) {
                error_log('[pages.php save_order] ' . $e->getMessage());
                $error = 'Sıralama kaydedilemedi.';
            }
        }
    }
}

try {
    $pages = $pdo->query('SELECT * FROM pages ORDER BY sort_order ASC, id ASC')->fetchAll();
} catch (Throwable $e) {
    $pages = [];
    $error = 'Sayfa listesi alınamadı. Lütfen <a href="migrate.php">migrasyonu</a> çalıştırın.';
}
$token = csrf_token();

$editId           = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editPage         = null;
$pageTranslations = [];
foreach ($pages as $p) {
    if ((int) $p['id'] === $editId) {
        $editPage = $p;
        break;
    }
}
if ($editPage) {
    try {
        $stmtPTr = $pdo->prepare('SELECT * FROM page_translations WHERE page_id = ?');
        $stmtPTr->execute([$editId]);
        foreach ($stmtPTr->fetchAll() as $tr) {
            $pageTranslations[$tr['language']] = $tr;
        }
    } catch (Throwable $e) {
        $pageTranslations = [];
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
                    <div class="alert alert-danger mx-3 mt-3 py-2"><?= $error ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success mx-3 mt-3 py-2"><?= $success ?></div>
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
                                            /<?= e($page['slug']) ?>
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
                <form method="post" enctype="multipart/form-data">
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
                        <div class="form-text">
                            Temiz URL: <code>/<?= e($editPage['slug'] ?? 'slug') ?></code>
                            &nbsp;(Sadece a-z, 0-9 ve - içerebilir)
                        </div>
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

                    <?php $bOpacity = (int)($editPage['banner_opacity'] ?? 50);
                          $bBlur    = (int)($editPage['banner_blur'] ?? 0); ?>
                    <div class="mb-3">
                        <label class="form-label">Banner Opaklık (Karartma) <span class="badge bg-secondary" id="bopacity_val"><?= $bOpacity ?>%</span></label>
                        <input type="range" name="banner_opacity" class="form-range"
                               min="0" max="100" value="<?= $bOpacity ?>"
                               oninput="document.getElementById('bopacity_val').textContent=this.value+'%'">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Banner Bulanıklık (Blur) <span class="badge bg-secondary" id="bblur_val"><?= $bBlur ?>px</span></label>
                        <input type="range" name="banner_blur" class="form-range"
                               min="0" max="20" value="<?= $bBlur ?>"
                               oninput="document.getElementById('bblur_val').textContent=this.value+'px'">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label form-label-sm">Yazı Rengi</label>
                            <input type="color" name="banner_title_color" class="form-control form-control-color w-100"
                                   value="<?= e($editPage['banner_title_color'] ?? '#ffffff') ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label form-label-sm">Yazı Boyutu</label>
                            <select name="banner_title_size" class="form-select form-select-sm">
                                <?php foreach (['1.25rem'=>'Küçük','1.75rem'=>'Orta','2rem'=>'Normal','2.5rem'=>'Büyük','3rem'=>'Çok Büyük'] as $v=>$l): ?>
                                    <option value="<?= $v ?>" <?= ($editPage['banner_title_size'] ?? '2rem') === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label form-label-sm">Yazı Konumu</label>
                            <select name="banner_title_position" class="form-select form-select-sm">
                                <option value="left"   <?= ($editPage['banner_title_position'] ?? 'center') === 'left'   ? 'selected' : '' ?>>Sol</option>
                                <option value="center" <?= ($editPage['banner_title_position'] ?? 'center') === 'center' ? 'selected' : '' ?>>Orta</option>
                                <option value="right"  <?= ($editPage['banner_title_position'] ?? 'center') === 'right'  ? 'selected' : '' ?>>Sağ</option>
                            </select>
                        </div>
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

<?php if ($editPage): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <i class="bi bi-translate text-primary"></i>
                <strong>Sayfa Çevirileri</strong>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs mb-3">
                    <?php foreach (['en','de','it','fr'] as $_ptl): ?>
                        <li class="nav-item">
                            <button class="nav-link <?= $_ptl === 'en' ? 'active' : '' ?>"
                                    data-bs-toggle="tab"
                                    data-bs-target="#pgtrans-<?= $_ptl ?>"
                                    type="button">
                                <?= strtoupper($_ptl) ?>
                                <?php if (!empty($pageTranslations[$_ptl])): ?>
                                    <span class="badge bg-success ms-1" style="font-size:.6rem;">✓</span>
                                <?php endif; ?>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="tab-content">
                    <?php foreach (['en','de','it','fr'] as $_ptl): ?>
                        <?php $_ptr = $pageTranslations[$_ptl] ?? []; ?>
                        <div class="tab-pane <?= $_ptl === 'en' ? 'show active' : '' ?>" id="pgtrans-<?= $_ptl ?>">
                            <form method="post">
                                <input type="hidden" name="csrf_token"             value="<?= e($token) ?>">
                                <input type="hidden" name="save_page_translation"  value="1">
                                <input type="hidden" name="page_id"                value="<?= e((string) $editPage['id']) ?>">
                                <input type="hidden" name="trans_lang"             value="<?= e($_ptl) ?>">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Sayfa Başlığı (<?= strtoupper($_ptl) ?>)</label>
                                        <input type="text" name="trans_title" class="form-control form-control-sm"
                                               value="<?= e($_ptr['title'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold">Slug</label>
                                        <input type="text" name="trans_slug" class="form-control form-control-sm"
                                               value="<?= e($_ptr['slug'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold">Banner Başlığı</label>
                                        <input type="text" name="trans_banner_title" class="form-control form-control-sm"
                                               value="<?= e($_ptr['banner_title'] ?? '') ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Meta Açıklama</label>
                                        <textarea name="trans_meta" class="form-control form-control-sm" rows="2"><?= e($_ptr['meta_description'] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">İçerik</label>
                                        <textarea name="trans_content" class="form-control form-control-sm" rows="5"><?= e($_ptr['content'] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <?= strtoupper($_ptl) ?> Çevirisini Kaydet
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
    document.addEventListener('DOMContentLoaded', function () {
        var list = document.getElementById('pages-list');
        if (list) Sortable.create(list, { handle: '.drag-handle', animation: 150 });
    });
</script>
