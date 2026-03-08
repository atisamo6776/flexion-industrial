<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload_helper.php';

require_admin_login();

$pdo     = db();
$error   = null;
$success = null;

function settings_get_all(PDO $pdo): array
{
    try {
        $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
        $out  = [];
        foreach ($stmt as $row) {
            $out[$row['setting_key']] = $row['setting_value'];
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

function settings_save(PDO $pdo, string $key, ?string $value): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([':k' => $key, ':v' => $value]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Güvenlik doğrulaması başarısız.';
    } else {
        $fields = [
            'site_title', 'meta_description', 'ga_code',
        ];
        foreach ($fields as $field) {
            $val = isset($_POST[$field]) ? trim($_POST[$field]) : null;
            settings_save($pdo, $field, $val);
        }

        // Favicon
        if (!empty($_FILES['favicon']['name'])) {
            $uploadDir = __DIR__ . '/../assets/uploads/';
            $fn        = upload_file(
                $_FILES['favicon'],
                $uploadDir,
                ['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/svg+xml'],
                512 * 1024
            );
            if ($fn) {
                settings_save($pdo, 'favicon_path', 'assets/uploads/' . $fn);
            } else {
                $error = 'Favicon yüklenemedi. ICO, PNG veya SVG maks 512 KB.';
            }
        }

        // Haberler banner görseli
        if (!empty($_FILES['news_banner_image']['name'])) {
            $uploadDir = __DIR__ . '/../assets/uploads/';
            $fn        = upload_file(
                $_FILES['news_banner_image'],
                $uploadDir,
                ['image/jpeg', 'image/png', 'image/webp'],
                5 * 1024 * 1024
            );
            if ($fn) {
                settings_save($pdo, 'news_banner_image', 'assets/uploads/' . $fn);
            } else {
                $error = 'Haberler banner görseli yüklenemedi. JPG/PNG/WEBP maks 5 MB.';
            }
        }

        settings_save($pdo, 'news_banner_title', trim($_POST['news_banner_title'] ?? ''));

        if (!$error) {
            $success = 'Genel ayarlar kaydedildi.';
        }
    }
}

$settings = settings_get_all($pdo);
$token    = csrf_token();

include __DIR__ . '/partials_header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success py-2"><?= $success ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><strong>Site Kimliği</strong></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Site Başlığı</label>
                        <input type="text" name="site_title" class="form-control"
                               value="<?= e($settings['site_title'] ?? 'Flexion Industrial') ?>">
                        <div class="form-text">Tarayıcı sekmesinde ve SEO'da kullanılır.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Meta Açıklaması</label>
                        <textarea name="meta_description" class="form-control" rows="2"><?= e($settings['meta_description'] ?? '') ?></textarea>
                        <div class="form-text">Arama motorları için kısa site açıklaması (maks ~160 karakter).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Favicon</label>
                        <?php if (!empty($settings['favicon_path'])): ?>
                            <div class="mb-2">
                                <img src="<?= e('../' . $settings['favicon_path']) ?>" height="32" alt="favicon">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="favicon" class="form-control" accept="image/x-icon,image/vnd.microsoft.icon,image/png,image/svg+xml">
                        <div class="form-text">ICO, PNG veya SVG. Maks 512 KB.</div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><strong>Analitik &amp; Kodlar</strong></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Google Analytics / GTM Kodu</label>
                        <textarea name="ga_code" class="form-control font-monospace" rows="4" placeholder="<script>...</script>"><?= e($settings['ga_code'] ?? '') ?></textarea>
                        <div class="form-text">Buraya yapıştırdığın kod, her sayfanın &lt;head&gt; bölümüne eklenir.</div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><strong>Haberler Sayfası Banner</strong></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Banner Görseli</label>
                        <?php if (!empty($settings['news_banner_image'])): ?>
                            <div class="mb-2">
                                <img src="<?= e('../' . $settings['news_banner_image']) ?>" alt="" class="img-fluid rounded border">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="news_banner_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">Haberler listesi üstünde görünecek geniş banner görseli.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Banner Başlığı</label>
                        <input type="text" name="news_banner_title" class="form-control"
                               value="<?= e($settings['news_banner_title'] ?? 'Haberler & Insights') ?>">
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><strong>Mevcut Tüm Ayarlar (bilgi)</strong></div>
                <div class="card-body">
                    <table class="table table-sm small">
                        <thead><tr><th>Anahtar</th><th>Değer</th></tr></thead>
                        <tbody>
                            <?php foreach ($settings as $k => $v): ?>
                                <tr>
                                    <td class="text-muted font-monospace"><?= e($k) ?></td>
                                    <td><?= e(mb_strimwidth((string) $v, 0, 80, '...')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="small text-muted mb-0">
                        Diğer ayarlar (logo, footer, sosyal medya vb.) <a href="header-footer.php">Header/Footer</a> sayfasından yönetilir.
                    </p>
                </div>
            </div>

            <div class="text-end mb-4">
                <button type="submit" class="btn btn-primary px-4">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/partials_footer.php'; ?>
