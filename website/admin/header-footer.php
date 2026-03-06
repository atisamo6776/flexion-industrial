<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload_helper.php';

require_admin_login();

$pdo     = db();
$error   = null;
$success = null;

function hf_fetch_settings(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
    $out  = [];
    foreach ($stmt as $row) {
        $out[$row['setting_key']] = $row['setting_value'];
    }
    return $out;
}

function hf_save_setting(PDO $pdo, string $key, ?string $value): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([':k' => $key, ':v' => $value]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($token)) {
        $error = 'Güvenlik doğrulaması başarısız. Lütfen formu tekrar deneyin.';
    } else {
        $fields = [
            'site_title', 'topbar_text', 'contact_phone', 'contact_email',
            'company_name', 'company_address',
            'social_linkedin', 'social_youtube',
            'footer_text', 'newsletter_text',
        ];
        foreach ($fields as $field) {
            $val = isset($_POST[$field]) ? trim($_POST[$field]) : null;
            hf_save_setting($pdo, $field, $val);
        }

        // Logo yükleme (upload_helper ile güvenli)
        if (!empty($_FILES['logo']['name'])) {
            $file = $_FILES['logo'];

            $savedName = upload_file(
                $file,
                __DIR__ . '/../assets/uploads/',
                ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
                2 * 1024 * 1024
            );

            if ($savedName) {
                hf_save_setting($pdo, 'logo_path', 'assets/uploads/' . $savedName);
            } else {
                $error = 'Logo yüklenemedi. JPG/PNG/WEBP/SVG ve en fazla 2 MB olmalı.';
            }
        }

        if (!$error) {
            $success = 'Header/Footer ayarları güncellendi.';
        }
    }
}

$settings = hf_fetch_settings($pdo);
$token    = csrf_token();

include __DIR__ . '/partials_header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success py-2"><?= e($success) ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><strong>Header Ayarları</strong></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Logo</label>
                        <?php if (!empty($settings['logo_path'])): ?>
                            <div class="mb-2">
                                <img src="<?= e('../' . $settings['logo_path']) ?>" alt="Logo" height="40" class="border rounded p-1">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="logo" class="form-control" accept="image/jpeg,image/png,image/webp,image/svg+xml">
                        <div class="form-text">Maks 2 MB. JPG, PNG, WEBP veya SVG.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Üst Bar Metni</label>
                        <input type="text" name="topbar_text" class="form-control"
                               value="<?= e($settings['topbar_text'] ?? '') ?>">
                        <div class="form-text">Header üstündeki ince bilgi çubuğu metni.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Site Başlığı</label>
                        <input type="text" name="site_title" class="form-control"
                               value="<?= e($settings['site_title'] ?? 'Flexion Industrial') ?>">
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><strong>İletişim Bilgileri (Footer &amp; Header)</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Telefon</label>
                            <input type="text" name="contact_phone" class="form-control"
                                   value="<?= e($settings['contact_phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">E-posta</label>
                            <input type="email" name="contact_email" class="form-control"
                                   value="<?= e($settings['contact_email'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Şirket Adı</label>
                            <input type="text" name="company_name" class="form-control"
                                   value="<?= e($settings['company_name'] ?? 'Flexion Industrial') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Şirket Adresi</label>
                            <input type="text" name="company_address" class="form-control"
                                   value="<?= e($settings['company_address'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><strong>Sosyal Medya</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">LinkedIn URL</label>
                            <input type="url" name="social_linkedin" class="form-control"
                                   value="<?= e($settings['social_linkedin'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">YouTube URL</label>
                            <input type="url" name="social_youtube" class="form-control"
                                   value="<?= e($settings['social_youtube'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><strong>Footer Metinleri</strong></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Footer Alt Bar Metni (copyright vb.)</label>
                        <input type="text" name="footer_text" class="form-control"
                               value="<?= e($settings['footer_text'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bülten Kısa Açıklaması</label>
                        <textarea name="newsletter_text" class="form-control" rows="2"><?= e($settings['newsletter_text'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="text-end mb-4">
                <button type="submit" class="btn btn-primary px-4">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/partials_footer.php'; ?>
