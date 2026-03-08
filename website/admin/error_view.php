<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_login();

$logFile = __DIR__ . '/flexion_errors.log';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear'])) {
    @unlink($logFile);
    header('Location: error_view.php');
    exit;
}

$content = null;
if (is_file($logFile)) {
    $content = file_get_contents($logFile);
} 

include __DIR__ . '/partials_header.php';
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <strong><i class="bi bi-bug me-2 text-danger"></i>PHP Hata Logu</strong>
            <small class="text-muted ms-2"><?= e($logFile) ?></small>
        </div>
        <div class="d-flex gap-2">
            <a href="error_view.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-clockwise me-1"></i>Yenile
            </a>
            <?php if ($content !== null): ?>
                <form method="post" class="d-inline">
                    <button name="clear" class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Log dosyasını temizlemek istiyor musunuz?')">
                        <i class="bi bi-trash3 me-1"></i>Temizle
                    </button>
                </form>
            <?php endif; ?>
            <a href="health.php" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-heart-pulse me-1"></i>Sağlık Kontrol
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($content === null): ?>
            <div class="alert alert-success mb-0">
                <i class="bi bi-check-circle me-2"></i>
                Log dosyası boş veya henüz oluşmadı — şu ana kadar PHP fatal hatası yakalanmadı.
            </div>
        <?php elseif (trim($content) === ''): ?>
            <div class="alert alert-success mb-0">
                <i class="bi bi-check-circle me-2"></i>
                Log dosyası boş — hata yok.
            </div>
        <?php else: ?>
            <div class="alert alert-danger py-2 mb-3">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                PHP fatal hataları bulundu. Aşağıdaki log içeriğini inceleyip <a href="migrate.php" class="alert-link">migrasyonu</a> çalıştır, ardından logı temizle.
            </div>
            <pre class="bg-dark text-light p-3 rounded small" style="max-height:600px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;"><?= htmlspecialchars($content) ?></pre>
        <?php endif; ?>
    </div>
</div>

<div class="mt-3 text-muted small">
    <i class="bi bi-info-circle me-1"></i>
    Bu sayfa, <code>admin/products.php</code> ve diğer admin sayfalarındaki PHP fatal hatalarını yakalar.
    Sorunları giderdikten sonra logı temizlemeyi unutma.
</div>

<?php include __DIR__ . '/partials_footer.php'; ?>
