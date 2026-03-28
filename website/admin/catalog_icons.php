<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_login();

$pdo   = db();
$token = generate_csrf_token();
$msg   = '';
$err   = '';

$uploadDir  = __DIR__ . '/../assets/uploads/catalog/';
$uploadBase = 'assets/uploads/catalog/';
$allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];

// ── POST işlemleri ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $err = 'Güvenlik doğrulaması başarısız.';
    } elseif (isset($_POST['add_icon'])) {
        $label = trim($_POST['admin_label'] ?? '');
        if (empty($_FILES['icon_image']['name'])) {
            $err = 'Görsel seçmediniz.';
        } else {
            @mkdir($uploadDir, 0755, true);
            $fname = upload_file($_FILES['icon_image'], $uploadDir, $allowedMime, 3 * 1024 * 1024);
            if (!$fname) {
                $err = 'Görsel yüklenemedi. PNG/JPG/WebP/SVG, maks 3 MB.';
            } else {
                $sort = (int)$pdo->query('SELECT IFNULL(MAX(sort_order),0)+1 FROM catalog_product_icons')->fetchColumn();
                $pdo->prepare('INSERT INTO catalog_product_icons (image_path, admin_label, sort_order, is_active) VALUES (?,?,?,1)')
                    ->execute([$uploadBase . $fname, $label, $sort]);
                $msg = 'İkon eklendi.';
            }
        }
    } elseif (isset($_POST['delete_icon'])) {
        $id = (int)($_POST['icon_id'] ?? 0);
        $row = $pdo->prepare('SELECT image_path FROM catalog_product_icons WHERE id=?');
        $row->execute([$id]);
        $r = $row->fetch();
        if ($r) {
            $file = __DIR__ . '/../' . $r['image_path'];
            if (is_file($file)) @unlink($file);
        }
        $pdo->prepare('DELETE FROM catalog_product_icons WHERE id=?')->execute([$id]);
        $msg = 'İkon silindi.';
    } elseif (isset($_POST['toggle_icon'])) {
        $id = (int)($_POST['icon_id'] ?? 0);
        $pdo->prepare('UPDATE catalog_product_icons SET is_active = NOT is_active WHERE id=?')->execute([$id]);
        $msg = 'Durum güncellendi.';
    } elseif (isset($_POST['update_label'])) {
        $id    = (int)($_POST['icon_id'] ?? 0);
        $label = trim($_POST['admin_label'] ?? '');
        $pdo->prepare('UPDATE catalog_product_icons SET admin_label=? WHERE id=?')->execute([$label, $id]);
        $msg = 'Etiket güncellendi.';
    }
}

$icons = $pdo->query('SELECT * FROM catalog_product_icons ORDER BY sort_order ASC, id ASC')->fetchAll();
?>
<?php include __DIR__ . '/partials_header.php'; ?>
<div class="container-fluid px-4 py-4">
    <div class="d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-patch-check text-primary fs-4"></i>
        <h1 class="h4 mb-0">Ürün İkon Kütüphanesi</h1>
    </div>

    <?php if ($msg): ?><div class="alert alert-success py-2"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endif; ?>

    <div class="row g-4">
        <!-- Yeni İkon Ekle -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><strong>Yeni İkon Ekle</strong></div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                        <input type="hidden" name="add_icon" value="1">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Admin Etiketi <span class="text-muted fw-normal">(opsiyonel, sadece panelde görünür)</span></label>
                            <input type="text" name="admin_label" class="form-control form-control-sm" placeholder="Örn: CE Mark, ISO 9001">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Görsel <span class="text-danger">*</span></label>
                            <input type="file" name="icon_image" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp,image/svg+xml" required>
                            <div class="form-text">PNG/SVG önerilir, maks 3 MB. Detay sayfasında sabit boyutta gösterilir.</div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary w-100">+ Ekle</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- İkon Listesi -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex align-items-center justify-content-between">
                    <strong>İkonlar</strong>
                    <span class="badge bg-secondary-subtle text-secondary border"><?= count($icons) ?> öğe</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($icons)): ?>
                        <p class="text-muted small p-3 mb-0">Henüz ikon yok. Sol taraftan ekleyin.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($icons as $icon): ?>
                                <li class="list-group-item py-2 <?= !(bool)$icon['is_active'] ? 'list-group-item-secondary' : '' ?>">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="flex-shrink-0" style="width:52px;height:52px;display:flex;align-items:center;justify-content:center;background:#f8f9fa;border-radius:8px;border:1px solid #e9ecef;">
                                            <img src="<?= e('../' . $icon['image_path']) ?>" alt="" style="max-width:44px;max-height:44px;object-fit:contain;">
                                        </div>
                                        <div class="flex-grow-1">
                                            <form method="post" class="d-flex gap-2 align-items-center">
                                                <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                                <input type="hidden" name="update_label" value="1">
                                                <input type="hidden" name="icon_id" value="<?= e((string)$icon['id']) ?>">
                                                <input type="text" name="admin_label" value="<?= e($icon['admin_label']) ?>" class="form-control form-control-sm" placeholder="Etiket" style="max-width:220px;">
                                                <button class="btn btn-sm btn-outline-secondary py-0">Kaydet</button>
                                            </form>
                                            <div class="small text-muted mt-1"><?= e($icon['image_path']) ?></div>
                                        </div>
                                        <div class="flex-shrink-0 d-flex gap-1">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                                <input type="hidden" name="toggle_icon" value="1">
                                                <input type="hidden" name="icon_id" value="<?= e((string)$icon['id']) ?>">
                                                <button class="btn btn-sm <?= (bool)$icon['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?> py-0">
                                                    <?= (bool)$icon['is_active'] ? 'Pasif' : 'Aktif' ?>
                                                </button>
                                            </form>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Bu ikonu silmek istediğinize emin misiniz?')">
                                                <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                                <input type="hidden" name="delete_icon" value="1">
                                                <input type="hidden" name="icon_id" value="<?= e((string)$icon['id']) ?>">
                                                <button class="btn btn-sm btn-outline-danger py-0">Sil</button>
                                            </form>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/partials_footer.php'; ?>
