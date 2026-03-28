<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_login();

$pdo   = db();
$token = generate_csrf_token();
$msg   = '';
$err   = '';

$uploadDir   = __DIR__ . '/../assets/uploads/catalog/';
$uploadBase  = 'assets/uploads/catalog/';
$allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];

// ── POST işlemleri ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $err = 'Güvenlik doğrulaması başarısız.';
    } elseif (isset($_POST['add_reg'])) {
        $label = trim($_POST['admin_label'] ?? '');
        if (empty($_FILES['reg_image']['name'])) {
            $err = 'Görsel seçmediniz.';
        } else {
            @mkdir($uploadDir, 0755, true);
            $fname = upload_file($_FILES['reg_image'], $uploadDir, $allowedMime, 3 * 1024 * 1024);
            if (!$fname) {
                $err = 'Görsel yüklenemedi. PNG/JPG/WebP/SVG, maks 3 MB.';
            } else {
                $sort = (int)$pdo->query('SELECT IFNULL(MAX(sort_order),0)+1 FROM catalog_regulation_images')->fetchColumn();
                $pdo->prepare('INSERT INTO catalog_regulation_images (image_path, admin_label, sort_order, is_active) VALUES (?,?,?,1)')
                    ->execute([$uploadBase . $fname, $label, $sort]);
                $msg = 'Regülasyon görseli eklendi.';
            }
        }
    } elseif (isset($_POST['delete_reg'])) {
        $id = (int)($_POST['reg_id'] ?? 0);
        $row = $pdo->prepare('SELECT image_path FROM catalog_regulation_images WHERE id=?');
        $row->execute([$id]);
        $r = $row->fetch();
        if ($r) {
            $file = __DIR__ . '/../' . $r['image_path'];
            if (is_file($file)) @unlink($file);
        }
        $pdo->prepare('DELETE FROM catalog_regulation_images WHERE id=?')->execute([$id]);
        $msg = 'Görsel silindi.';
    } elseif (isset($_POST['toggle_reg'])) {
        $id = (int)($_POST['reg_id'] ?? 0);
        $pdo->prepare('UPDATE catalog_regulation_images SET is_active = NOT is_active WHERE id=?')->execute([$id]);
        $msg = 'Durum güncellendi.';
    } elseif (isset($_POST['update_label'])) {
        $id    = (int)($_POST['reg_id'] ?? 0);
        $label = trim($_POST['admin_label'] ?? '');
        $pdo->prepare('UPDATE catalog_regulation_images SET admin_label=? WHERE id=?')->execute([$label, $id]);
        $msg = 'Etiket güncellendi.';
    }
}

$items = $pdo->query('SELECT * FROM catalog_regulation_images ORDER BY sort_order ASC, id ASC')->fetchAll();
?>
<?php include __DIR__ . '/partials_header.php'; ?>
<div class="container-fluid px-4 py-4">
    <div class="d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-award text-primary fs-4"></i>
        <h1 class="h4 mb-0">Regülasyon / Sertifika Görsel Kütüphanesi</h1>
    </div>

    <?php if ($msg): ?><div class="alert alert-success py-2"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endif; ?>

    <div class="row g-4">
        <!-- Yeni Ekle -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><strong>Yeni Regülasyon Görseli Ekle</strong></div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                        <input type="hidden" name="add_reg" value="1">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Admin Etiketi <span class="text-muted fw-normal">(opsiyonel)</span></label>
                            <input type="text" name="admin_label" class="form-control form-control-sm" placeholder="Örn: NSF 51, FDA Compliant">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Görsel <span class="text-danger">*</span></label>
                            <input type="file" name="reg_image" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp,image/svg+xml" required>
                            <div class="form-text">PNG/SVG önerilir, maks 3 MB. Detay sayfasında sabit boyutta gösterilir.</div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary w-100">+ Ekle</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Liste -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex align-items-center justify-content-between">
                    <strong>Regülasyon Görselleri</strong>
                    <span class="badge bg-secondary-subtle text-secondary border"><?= count($items) ?> öğe</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($items)): ?>
                        <p class="text-muted small p-3 mb-0">Henüz görsel yok. Sol taraftan ekleyin.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($items as $item): ?>
                                <li class="list-group-item py-2 <?= !(bool)$item['is_active'] ? 'list-group-item-secondary' : '' ?>">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="flex-shrink-0" style="width:64px;height:48px;display:flex;align-items:center;justify-content:center;background:#f8f9fa;border-radius:8px;border:1px solid #e9ecef;">
                                            <img src="<?= e('../' . $item['image_path']) ?>" alt="" style="max-width:58px;max-height:42px;object-fit:contain;">
                                        </div>
                                        <div class="flex-grow-1">
                                            <form method="post" class="d-flex gap-2 align-items-center">
                                                <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                                <input type="hidden" name="update_label" value="1">
                                                <input type="hidden" name="reg_id" value="<?= e((string)$item['id']) ?>">
                                                <input type="text" name="admin_label" value="<?= e($item['admin_label']) ?>" class="form-control form-control-sm" placeholder="Etiket" style="max-width:220px;">
                                                <button class="btn btn-sm btn-outline-secondary py-0">Kaydet</button>
                                            </form>
                                            <div class="small text-muted mt-1"><?= e($item['image_path']) ?></div>
                                        </div>
                                        <div class="flex-shrink-0 d-flex gap-1">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                                <input type="hidden" name="toggle_reg" value="1">
                                                <input type="hidden" name="reg_id" value="<?= e((string)$item['id']) ?>">
                                                <button class="btn btn-sm <?= (bool)$item['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?> py-0">
                                                    <?= (bool)$item['is_active'] ? 'Pasif' : 'Aktif' ?>
                                                </button>
                                            </form>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Bu görseli silmek istediğinize emin misiniz?')">
                                                <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                                <input type="hidden" name="delete_reg" value="1">
                                                <input type="hidden" name="reg_id" value="<?= e((string)$item['id']) ?>">
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
