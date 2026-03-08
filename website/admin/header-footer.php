<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload_helper.php';

require_admin_login();

$pdo     = db();
$error   = null;
$success = null;

function hf_fetch_settings(PDO $pdo): array
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
    } elseif (isset($_POST['save_settings'])) {
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

        // Logo yüksekliği
        $logoHeight = (int) ($_POST['logo_height'] ?? 36);
        $logoHeight = max(20, min(120, $logoHeight));
        hf_save_setting($pdo, 'logo_height', (string) $logoHeight);

        // Logo yükleme
        if (!empty($_FILES['logo']['name'])) {
            $savedName = upload_file(
                $_FILES['logo'],
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

    } elseif (isset($_POST['add_footer_link'])) {
        $colKey   = trim($_POST['fl_column_key'] ?? '');
        $colLabel = trim($_POST['fl_column_label'] ?? '');
        $flTitle  = trim($_POST['fl_title'] ?? '');
        $flUrl    = trim($_POST['fl_url'] ?? '');
        if ($colKey && $flTitle && $flUrl) {
            try {
                $stmtSort = $pdo->prepare('SELECT IFNULL(MAX(sort_order),0)+1 FROM footer_links WHERE column_key = ?');
                $stmtSort->execute([$colKey]);
                $flSort = (int) $stmtSort->fetchColumn();
                $pdo->prepare('INSERT INTO footer_links (column_key, column_label, title, url, sort_order, is_active) VALUES (?,?,?,?,?,1)')
                    ->execute([$colKey, $colLabel, $flTitle, $flUrl, $flSort]);
                $success = 'Link eklendi.';
            } catch (Throwable $e) {
                $error = 'Link eklenemedi. Lütfen <a href="migrate.php">migrasyonu</a> çalıştırın.';
            }
        } else {
            $error = 'Sütun, başlık ve URL zorunludur.';
        }

    } elseif (isset($_POST['delete_footer_link'])) {
        $flId = (int) ($_POST['fl_id'] ?? 0);
        if ($flId > 0) {
            try {
                $pdo->prepare('DELETE FROM footer_links WHERE id = ?')->execute([$flId]);
                $success = 'Link silindi.';
            } catch (Throwable $e) {
                $error = 'Silinemedi.';
            }
        }

    } elseif (isset($_POST['toggle_footer_link'])) {
        $flId = (int) ($_POST['fl_id'] ?? 0);
        if ($flId > 0) {
            try {
                $pdo->prepare('UPDATE footer_links SET is_active = NOT is_active WHERE id = ?')->execute([$flId]);
                $success = 'Link durumu güncellendi.';
            } catch (Throwable $e) {
                $error = 'Güncellenemedi.';
            }
        }

    } elseif (isset($_POST['save_footer_link_order'])) {
        $ids  = $_POST['fl_order'] ?? [];
        $i    = 1;
        try {
            $stmt = $pdo->prepare('UPDATE footer_links SET sort_order = ? WHERE id = ?');
            foreach ($ids as $fid) {
                $stmt->execute([$i++, (int) $fid]);
            }
            $success = 'Sıralama güncellendi.';
        } catch (Throwable $e) {
            $error = 'Sıralama kaydedilemedi.';
        }

    } elseif (isset($_POST['update_column_label'])) {
        $colKey   = trim($_POST['fl_column_key'] ?? '');
        $colLabel = trim($_POST['fl_column_label'] ?? '');
        if ($colKey && $colLabel) {
            try {
                $pdo->prepare('UPDATE footer_links SET column_label = ? WHERE column_key = ?')
                    ->execute([$colLabel, $colKey]);
                $success = 'Sütun başlığı güncellendi.';
            } catch (Throwable $e) {
                $error = 'Güncellenemedi.';
            }
        }
    }
}

$settings = hf_fetch_settings($pdo);
$token    = csrf_token();

// Footer linkleri (column_key'e göre grupla)
$footerLinksRaw   = [];
$footerLinksByCol = [];
$footerLinksError = null;
try {
    $footerLinksRaw = $pdo->query('SELECT * FROM footer_links ORDER BY column_key ASC, sort_order ASC, id ASC')->fetchAll();
    foreach ($footerLinksRaw as $fl) {
        $footerLinksByCol[$fl['column_key']][] = $fl;
    }
} catch (Throwable $e) {
    $footerLinksError = 'footer_links tablosu bulunamadı. Lütfen <a href="migrate.php">migrasyonu</a> çalıştırın.';
}

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
            <input type="hidden" name="save_settings" value="1">

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
                        <label class="form-label">Logo Yüksekliği (px)</label>
                        <input type="number" name="logo_height" class="form-control" min="20" max="120"
                               value="<?= e($settings['logo_height'] ?? '36') ?>">
                        <div class="form-text">Header ve footer'da logo yüksekliği. Önerilen: 30-60 px.</div>
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

        <!-- ======== Footer Link Yönetimi ======== -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><strong>Footer Linkleri</strong></div>
            <div class="card-body">
                <?php if ($footerLinksError): ?>
                    <div class="alert alert-warning py-2"><?= $footerLinksError ?></div>
                <?php endif; ?>
                <p class="small text-muted mb-3">
                    Footer'daki sütun başlıklarını ve linkleri buradan yönetin. Sütun anahtarı:
                    <code>company</code>, <code>products</code>, <code>contact</code> gibi benzersiz tanımlayıcılardır.
                </p>

                <!-- Sütun başlığı güncelleme -->
                <?php
                $allCols = [];
                foreach ($footerLinksRaw as $fl) {
                    $allCols[$fl['column_key']] = $fl['column_label'];
                }
                ?>
                <?php if (!empty($allCols)): ?>
                <div class="mb-3">
                    <strong class="small d-block mb-2">Sütun Başlıklarını Güncelle</strong>
                    <div class="row g-2">
                        <?php foreach ($allCols as $ck => $cl): ?>
                        <div class="col-md-4">
                            <form method="post" class="d-flex gap-1">
                                <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                <input type="hidden" name="update_column_label" value="1">
                                <input type="hidden" name="fl_column_key" value="<?= e($ck) ?>">
                                <input type="text" name="fl_column_label" class="form-control form-control-sm"
                                       value="<?= e($cl) ?>" placeholder="Başlık">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Kaydet</button>
                            </form>
                            <div class="form-text">Anahtar: <code><?= e($ck) ?></code></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Link ekleme formu -->
                <form method="post" class="border rounded p-3 mb-3">
                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                    <input type="hidden" name="add_footer_link" value="1">
                    <strong class="small d-block mb-2">Yeni Link Ekle</strong>
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label form-label-sm">Sütun Anahtarı <span class="text-danger">*</span></label>
                            <input type="text" name="fl_column_key" class="form-control form-control-sm"
                                   placeholder="company" list="col-keys-list" required>
                            <datalist id="col-keys-list">
                                <?php foreach (array_unique(array_column($footerLinksRaw, 'column_key')) as $ck): ?>
                                    <option value="<?= e($ck) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm">Sütun Görünür Adı</label>
                            <input type="text" name="fl_column_label" class="form-control form-control-sm"
                                   placeholder="Kurumsal" list="col-labels-list">
                            <datalist id="col-labels-list">
                                <?php foreach ($allCols as $ck => $cl): ?>
                                    <option value="<?= e($cl) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm">Link Başlığı <span class="text-danger">*</span></label>
                            <input type="text" name="fl_title" class="form-control form-control-sm"
                                   placeholder="Hakkımızda" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm">URL <span class="text-danger">*</span></label>
                            <input type="text" name="fl_url" class="form-control form-control-sm"
                                   placeholder="page.php?slug=..." required>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-sm btn-primary w-100">+</button>
                        </div>
                    </div>
                </form>

                <!-- Mevcut linkler -->
                <?php foreach ($footerLinksByCol as $colKey => $colLinks): ?>
                    <div class="mb-3">
                        <div class="fw-semibold small border-bottom pb-1 mb-2">
                            <?= e($colLinks[0]['column_label'] ?: $colKey) ?>
                            <span class="text-muted ms-1">(<?= e($colKey) ?>)</span>
                        </div>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                            <input type="hidden" name="save_footer_link_order" value="1">
                            <ul class="list-group list-group-flush" id="fl-list-<?= e($colKey) ?>">
                                <?php foreach ($colLinks as $fl): ?>
                                    <li class="list-group-item d-flex align-items-center justify-content-between py-1" data-id="<?= e((string)$fl['id']) ?>">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="drag-handle text-muted" style="cursor:grab;"><i class="bi bi-grip-vertical"></i></span>
                                            <div>
                                                <div class="small <?= !(bool)$fl['is_active'] ? 'text-muted text-decoration-line-through' : '' ?>">
                                                    <?= e($fl['title']) ?>
                                                </div>
                                                <div class="x-small text-muted" style="font-size:0.75rem;"><?= e($fl['url']) ?></div>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-1">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                                <input type="hidden" name="toggle_footer_link" value="1">
                                                <input type="hidden" name="fl_id" value="<?= e((string)$fl['id']) ?>">
                                                <button class="btn btn-xs btn-sm py-0 px-1 <?= (bool)$fl['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                                                    <?= (bool)$fl['is_active'] ? 'Pasif' : 'Aktif' ?>
                                                </button>
                                            </form>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                                <input type="hidden" name="delete_footer_link" value="1">
                                                <input type="hidden" name="fl_id" value="<?= e((string)$fl['id']) ?>">
                                                <button class="btn btn-xs btn-sm btn-outline-danger py-0 px-1"
                                                        onclick="return confirm('Linki silmek istiyor musunuz?')">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                            <input type="hidden" name="fl_order[]" value="<?= e((string)$fl['id']) ?>">
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="mt-1 text-end">
                                <button type="submit" class="btn btn-xs btn-sm btn-outline-secondary">Sıralamayı Kaydet</button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($footerLinksRaw)): ?>
                    <p class="text-muted small">Henüz link eklenmemiş. Yukarıdaki formdan ekleyebilirsiniz.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials_footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
document.querySelectorAll('[id^="fl-list-"]').forEach(function(list) {
    Sortable.create(list, { handle: '.drag-handle', animation: 150 });
});
</script>
