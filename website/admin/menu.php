<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_login();

$pdo = db();
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($token)) {
        $error = 'Güvenlik doğrulaması başarısız. Lütfen formu tekrar deneyin.';
    } else {
        if (isset($_POST['save_item'])) {
            $id       = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $title    = trim($_POST['title'] ?? '');
            $url      = trim($_POST['url'] ?? '');
            $parentId = (int) ($_POST['parent_id'] ?? 0);
            $active   = isset($_POST['is_active']) ? 1 : 0;

            if ($title === '' || $url === '') {
                $error = 'Başlık ve URL zorunludur.';
            } else {
                try {
                    if ($id > 0) {
                        $stmt = $pdo->prepare('UPDATE menu_items SET title = :title, url = :url, parent_id = :parent, is_active = :active WHERE id = :id');
                        $stmt->execute([
                            ':title'  => $title,
                            ':url'    => $url,
                            ':parent' => $parentId ?: null,
                            ':active' => $active,
                            ':id'     => $id,
                        ]);
                        $success = 'Menü öğesi güncellendi.';
                    } else {
                        $sort = (int) $pdo->query('SELECT IFNULL(MAX(sort_order),0)+1 FROM menu_items')->fetchColumn();
                        $stmt = $pdo->prepare('INSERT INTO menu_items (title, url, parent_id, sort_order, is_active) VALUES (:title, :url, :parent, :sort, :active)');
                        $stmt->execute([
                            ':title'  => $title,
                            ':url'    => $url,
                            ':parent' => $parentId ?: null,
                            ':sort'   => $sort,
                            ':active' => $active,
                        ]);
                        $success = 'Yeni menü öğesi eklendi.';
                    }
                } catch (Throwable $e) {
                    error_log('[menu.php save_item] ' . $e->getMessage());
                    $error = 'Menü öğesi kaydedilemedi. Lütfen <a href="migrate.php">migrasyonu</a> kontrol edin.';
                }
            }
        } elseif (isset($_POST['save_item_translation'])) {
            $menuItemId = (int) ($_POST['menu_item_id'] ?? 0);
            $lang       = trim($_POST['translation_lang'] ?? '');
            $SUPPORTED  = ['en', 'de', 'it', 'fr'];
            if ($menuItemId > 0 && in_array($lang, $SUPPORTED, true)) {
                $trTitle = trim($_POST['tr_title'] ?? '');
                $trUrl   = trim($_POST['tr_url'] ?? '');
                if ($trTitle !== '') {
                    try {
                        $pdo->prepare(
                            'INSERT INTO menu_item_translations (menu_item_id, language, title, url)
                             VALUES (:mid, :lang, :title, :url)
                             ON DUPLICATE KEY UPDATE title = VALUES(title), url = VALUES(url)'
                        )->execute([
                            ':mid'   => $menuItemId,
                            ':lang'  => $lang,
                            ':title' => $trTitle,
                            ':url'   => $trUrl,
                        ]);
                        header('Location: menu.php?edit=' . $menuItemId . '#menu-translations');
                        exit;
                    } catch (Throwable $e) {
                        error_log('[menu.php save_item_translation] ' . $e->getMessage());
                        $error = 'Çeviri kaydedilemedi.';
                    }
                }
            }
        } elseif (isset($_POST['delete_item'])) {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare('DELETE FROM menu_items WHERE id = :id OR parent_id = :id');
                    $stmt->execute([':id' => $id]);
                    $success = 'Menü öğesi (ve varsa altları) silindi.';
                } catch (Throwable $e) {
                    error_log('[menu.php delete_item] ' . $e->getMessage());
                    $error = 'Menü öğesi silinemedi.';
                }
            }
        } elseif (isset($_POST['save_order'])) {
            $order = $_POST['order'] ?? [];
            $i = 1;
            try {
                $stmt = $pdo->prepare('UPDATE menu_items SET sort_order = :sort WHERE id = :id');
                foreach ($order as $id) {
                    $stmt->execute([
                        ':sort' => $i++,
                        ':id'   => (int) $id,
                    ]);
                }
                $success = 'Menü sıralaması güncellendi.';
            } catch (Throwable $e) {
                error_log('[menu.php save_order] ' . $e->getMessage());
                $error = 'Sıralama kaydedilemedi.';
            }
        }
    }
}

// Liste
try {
    $items = $pdo->query('SELECT * FROM menu_items ORDER BY sort_order ASC, id ASC')->fetchAll();
} catch (Throwable $e) {
    $items = [];
    $error = 'Menü listesi alınamadı. Lütfen <a href="migrate.php">migrasyonu</a> çalıştırın.';
}

// Hızlı linkler için aktif kurumsal sayfalar
$pageLinks = [];
try {
    $stmtPages = $pdo->query('SELECT slug, title FROM pages WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
    foreach ($stmtPages as $p) {
        $pageLinks['page.php?slug=' . $p['slug']] = $p['title'];
    }
} catch (Throwable $e) {
    $pageLinks = [];
}

$token = csrf_token();

include __DIR__ . '/partials_header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Menü Öğeleri</strong>
                <small class="text-muted">Sürükle-bırak ile sıralayabilirsin.</small>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><?= $error ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success py-2"><?= $success ?></div>
                <?php endif; ?>

                <!-- Sıralama formu (delete form ile iç içe GİRMEZ) -->
                <form method="post" id="sort-form">
                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                    <input type="hidden" name="save_order" value="1">
                    <ul class="list-group" id="menu-items-list">
                        <?php foreach ($items as $item): ?>
                            <li class="list-group-item d-flex align-items-center justify-content-between" data-id="<?= e((string) $item['id']) ?>">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="drag-handle text-muted" style="cursor:grab;">
                                        <i class="bi bi-grip-vertical"></i>
                                    </span>
                                    <div>
                                        <div><?= e($item['title']) ?></div>
                                        <div class="small text-muted"><?= e($item['url']) ?></div>
                                        <?php if (!(bool)$item['is_active']): ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle small">Pasif</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <a href="?edit=<?= e((string) $item['id']) ?>" class="btn btn-sm btn-outline-secondary">
                                        Düzenle
                                    </a>
                                    <!-- Silme: ayrı hidden form aracılığıyla JS ile gönderilir -->
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="menuDelete(<?= (int)$item['id'] ?>, '<?= e(addslashes($item['title'])) ?>')">
                                        Sil
                                    </button>
                                    <input type="hidden" name="order[]" value="<?= e((string) $item['id']) ?>">
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="mt-3 text-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            Sıralamayı Kaydet
                        </button>
                    </div>
                </form>

                <!-- Silme formu — gizli, JS tarafından tetiklenir (nested form yasak olduğu için ayrı) -->
                <form method="post" id="delete-form" style="display:none;">
                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                    <input type="hidden" name="delete_item" value="1">
                    <input type="hidden" name="id" id="delete-target-id">
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <?php
        $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        $editItem = null;
        if ($editId) {
            foreach ($items as $it) {
                if ((int) $it['id'] === $editId) {
                    $editItem = $it;
                    break;
                }
            }
        }
        ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <strong><?= $editItem ? 'Menü Öğesi Düzenle' : 'Yeni Menü Öğesi' ?></strong>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                    <input type="hidden" name="save_item" value="1">
                    <?php if ($editItem): ?>
                        <input type="hidden" name="id" value="<?= e((string) $editItem['id']) ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Başlık</label>
                        <input type="text" name="title" class="form-control"
                               value="<?= e($editItem['title'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">URL</label>
                        <input type="text" name="url" class="form-control"
                               value="<?= e($editItem['url'] ?? '') ?>" required>
                        <div class="form-text">Örn: index.php, sectors.php, category.php?id=1</div>
                        <div class="mt-2">
                            <div class="small text-muted mb-1">Mevcut sayfalardan seç:</div>
                            <div class="d-flex flex-wrap gap-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        onclick="document.querySelector('input[name=url]').value='index.php'">
                                    Ana Sayfa
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        onclick="document.querySelector('input[name=url]').value='sectors.php'">
                                    Sektörler
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        onclick="document.querySelector('input[name=url]').value='news.php'">
                                    Haberler
                                </button>
                                <?php foreach ($pageLinks as $link => $title): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            onclick="document.querySelector('input[name=url]').value='<?= e($link) ?>'">
                                        <?= e($title) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Üst Menü (opsiyonel)</label>
                        <select name="parent_id" class="form-select">
                            <option value="0">Yok (üst seviye)</option>
                            <?php foreach ($items as $it): ?>
                                <option value="<?= e((string) $it['id']) ?>"
                                    <?= isset($editItem['parent_id']) && (int)$editItem['parent_id'] === (int)$it['id'] ? 'selected' : '' ?>>
                                    <?= e($it['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                            <?= !isset($editItem['is_active']) || (int)$editItem['is_active'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">
                            Bu öğe aktif olsun
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <?= $editItem ? 'Güncelle' : 'Ekle' ?>
                    </button>
                </form>

                <?php if ($editItem): ?>
                <!-- Dil Çevirileri -->
                <hr id="menu-translations">
                <h6 class="fw-semibold mb-3">Dil Çevirileri</h6>
                <?php
                $LANGS = ['en' => 'English', 'de' => 'Deutsch', 'it' => 'Italiano', 'fr' => 'Français'];
                $menuTrans = [];
                try {
                    $mtStmt = $pdo->prepare('SELECT * FROM menu_item_translations WHERE menu_item_id = ?');
                    $mtStmt->execute([$editItem['id']]);
                    foreach ($mtStmt->fetchAll() as $mt) {
                        $menuTrans[$mt['language']] = $mt;
                    }
                } catch (Throwable $e) {}
                ?>
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <?php $first = true; foreach ($LANGS as $lCode => $lLabel): ?>
                    <li class="nav-item">
                        <button class="nav-link <?= $first ? 'active' : '' ?>"
                                data-bs-toggle="tab"
                                data-bs-target="#menu-tr-<?= $lCode ?>"
                                type="button"><?= $lLabel ?>
                            <?php if (isset($menuTrans[$lCode])): ?>
                                <span class="badge bg-success ms-1" style="font-size:.6rem;">✓</span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <?php $first = false; endforeach; ?>
                </ul>
                <div class="tab-content">
                    <?php $first = true; foreach ($LANGS as $lCode => $lLabel): ?>
                    <div class="tab-pane fade <?= $first ? 'show active' : '' ?>" id="menu-tr-<?= $lCode ?>">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                            <input type="hidden" name="save_item_translation" value="1">
                            <input type="hidden" name="menu_item_id" value="<?= e((string)$editItem['id']) ?>">
                            <input type="hidden" name="translation_lang" value="<?= e($lCode) ?>">
                            <div class="mb-2">
                                <label class="form-label form-label-sm">Başlık <span class="text-danger">*</span></label>
                                <input type="text" name="tr_title" class="form-control form-control-sm"
                                       value="<?= e($menuTrans[$lCode]['title'] ?? '') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label form-label-sm">URL</label>
                                <input type="text" name="tr_url" class="form-control form-control-sm"
                                       value="<?= e($menuTrans[$lCode]['url'] ?? '') ?>"
                                       placeholder="<?= e($editItem['url']) ?>">
                                <div class="form-text">Boş bırakırsan varsayılan URL kullanılır.</div>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary"><?= e($lLabel) ?> Kaydet</button>
                        </form>
                    </div>
                    <?php $first = false; endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials_footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const list = document.getElementById('menu-items-list');
        if (!list) return;
        Sortable.create(list, {
            handle: '.drag-handle',
            animation: 150,
        });
    });

    function menuDelete(id, title) {
        if (!confirm('Bu menü öğesini (ve varsa alt öğelerini) silmek istediğinize emin misiniz?\n\n' + title)) return;
        document.getElementById('delete-target-id').value = id;
        document.getElementById('delete-form').submit();
    }

    // Hash ile geldiyse ilgili bölüme scroll et
    document.addEventListener('DOMContentLoaded', function () {
        if (window.location.hash) {
            var target = document.querySelector(window.location.hash);
            if (target) {
                setTimeout(function () {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 200);
            }
        }
    });
</script>

