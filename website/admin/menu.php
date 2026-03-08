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
                    <div class="alert alert-danger py-2"><?= e($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success py-2"><?= e($success) ?></div>
                <?php endif; ?>
                <form method="post">
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
                                    <button type="submit" name="delete_item" value="1" class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Bu menü öğesini silmek istediğinize emin misiniz?')">
                                        <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
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
</script>

