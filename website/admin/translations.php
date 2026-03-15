<?php

require_once __DIR__ . '/../includes/auth.php';

require_admin_login();

$pdo     = db();
$error   = null;
$success = null;

$LANGS       = ['en' => 'English', 'de' => 'Deutsch', 'it' => 'Italiano', 'fr' => 'Français'];
$SUPPORTED   = array_keys($LANGS);

// Gruplar: hangi key hangi grupta görünür
$KEY_GROUPS = [
    'Genel'       => ['site_title', 'topbar_text', 'meta_description', 'search_placeholder'],
    'Navigasyon'  => ['nav_home', 'nav_products', 'nav_news', 'nav_contact', 'nav_about'],
    'Ana Sayfa'   => ['home_hero_title', 'home_hero_subtitle', 'home_hero_btn', 'home_sectors_title', 'home_sectors_subtitle', 'home_news_title', 'home_btn_view_sectors', 'home_btn_view_news', 'home_no_categories', 'home_no_news', 'home_upload_image'],
    'Haberler'    => ['news_banner_title', 'news_other', 'news_back', 'news_not_found', 'news_not_found_desc', 'news_empty'],
    'Kategoriler' => ['cat_categories_title', 'cat_not_found', 'cat_not_found_desc', 'cat_back_sectors', 'cat_no_products', 'cat_sort_relevance', 'cat_sort_az', 'cat_sort_za', 'cat_products_count'],
    'Ürünler'     => ['prod_not_found', 'prod_not_found_desc', 'prod_back', 'prod_inquiry_title', 'prod_inquiry_sent', 'prod_related', 'prod_spec_title', 'prod_docs_title', 'prod_regs_title', 'prod_code_label'],
    'Form'        => ['form_name', 'form_surname', 'form_email', 'form_phone', 'form_company', 'form_country', 'form_message', 'form_contact_success', 'form_success_title', 'form_send_new', 'contact_form_title', 'contact_address_label'],
    'Butonlar'    => ['btn_submit', 'btn_view_all', 'btn_close', 'btn_cancel'],
    'Çerez'       => ['cookie_message', 'cookie_policy_link', 'cookie_accept', 'cookie_reject'],
    'Footer'      => ['footer_rights', 'footer_newsletter_label', 'footer_social_title'],
    '404'         => ['404_title', '404_desc', '404_back'],
    'Sayfalama'   => ['pagination_prev', 'pagination_next'],
];

// POST işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($token)) {
        $error = 'Güvenlik doğrulaması başarısız.';
    } elseif (isset($_POST['save_translations'])) {
        $translationsData = $_POST['translations'] ?? [];
        $stmt = $pdo->prepare(
            'INSERT INTO site_translations (`key`, `language`, `value`) VALUES (:k, :l, :v)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        $saved = 0;
        foreach ($translationsData as $key => $langs) {
            $key = trim($key);
            if (!$key) continue;
            foreach ($langs as $lang => $value) {
                if (!in_array($lang, $SUPPORTED, true)) continue;
                try {
                    $stmt->execute([':k' => $key, ':l' => $lang, ':v' => trim($value)]);
                    $saved++;
                } catch (Throwable $e) {
                    error_log('[translations.php] ' . $e->getMessage());
                }
            }
        }
        $success = $saved . ' çeviri kaydedildi.';
    } elseif (isset($_POST['add_key'])) {
        $newKey = trim($_POST['new_key'] ?? '');
        $newGroup = trim($_POST['new_group'] ?? '') ?: 'Genel';
        if ($newKey && preg_match('/^[a-z0-9_]+$/', $newKey)) {
            foreach ($SUPPORTED as $lang) {
                $val = trim($_POST['new_value_' . $lang] ?? '');
                try {
                    $pdo->prepare(
                        'INSERT IGNORE INTO site_translations (`key`, `language`, `value`) VALUES (?, ?, ?)'
                    )->execute([$newKey, $lang, $val]);
                } catch (Throwable $e) {}
            }
            $success = '"' . htmlspecialchars($newKey) . '" anahtarı eklendi.';
        } else {
            $error = 'Anahtar sadece küçük harf, rakam ve alt çizgi içerebilir.';
        }
    } elseif (isset($_POST['save_footer_link_translations'])) {
        $flTrData = $_POST['footer_link_tr'] ?? [];
        $flStmt   = $pdo->prepare(
            'INSERT INTO footer_link_translations (footer_link_id, language, title) VALUES (:fid, :lang, :title)
             ON DUPLICATE KEY UPDATE title = VALUES(title)'
        );
        $flSaved = 0;
        foreach ($flTrData as $flId => $langs) {
            $flId = (int) $flId;
            if (!$flId) continue;
            foreach ($langs as $lang => $title) {
                if (!in_array($lang, $SUPPORTED, true)) continue;
                $title = trim($title);
                if (!$title) continue;
                try {
                    $flStmt->execute([':fid' => $flId, ':lang' => $lang, ':title' => $title]);
                    $flSaved++;
                } catch (Throwable $e) {}
            }
        }
        $success = "Footer: {$flSaved} çeviri kaydedildi.";
    }
}

// Tüm mevcut çevirileri al
$allTranslations = [];
try {
    $rows = $pdo->query('SELECT `key`, `language`, `value` FROM site_translations ORDER BY `key`, `language`')->fetchAll();
    foreach ($rows as $row) {
        $allTranslations[$row['key']][$row['language']] = $row['value'];
    }
} catch (Throwable $e) {
    $error = 'Çeviriler yüklenemedi. Önce <a href="migrate.php">migrate</a> çalıştırın.';
}

$token = csrf_token();

include __DIR__ . '/partials_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Site Çevirileri</h4>
    <a href="#add-key-section" class="btn btn-sm btn-outline-primary">+ Yeni Anahtar</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger py-2"><?= $error ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success py-2"><?= $success ?></div>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
    <input type="hidden" name="save_translations" value="1">

    <!-- Grup sekmeleri -->
    <ul class="nav nav-tabs mb-3" role="tablist">
        <?php $first = true; foreach ($KEY_GROUPS as $groupName => $_): ?>
        <li class="nav-item">
            <button class="nav-link <?= $first ? 'active' : '' ?>" type="button"
                    data-bs-toggle="tab"
                    data-bs-target="#tr-group-<?= e(preg_replace('/[^a-z0-9]/i', '-', $groupName)) ?>">
                <?= e($groupName) ?>
            </button>
        </li>
        <?php $first = false; endforeach; ?>
    </ul>

    <div class="tab-content">
    <?php $first = true; foreach ($KEY_GROUPS as $groupName => $keys): ?>
    <?php $groupId = 'tr-group-' . preg_replace('/[^a-z0-9]/i', '-', $groupName); ?>
    <div class="tab-pane fade <?= $first ? 'show active' : '' ?>" id="<?= e($groupId) ?>">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:200px;">Anahtar</th>
                                <?php foreach ($LANGS as $lCode => $lLabel): ?>
                                    <th><?= e($lLabel) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($keys as $key): ?>
                        <tr>
                            <td><code class="small"><?= e($key) ?></code></td>
                            <?php foreach ($LANGS as $lCode => $lLabel): ?>
                            <td>
                                <input type="text"
                                       name="translations[<?= e($key) ?>][<?= e($lCode) ?>]"
                                       class="form-control form-control-sm"
                                       value="<?= e($allTranslations[$key][$lCode] ?? '') ?>"
                                       placeholder="<?= e($lLabel) ?>...">
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php
                        // Bu gruptaki key'lerde olmayan ama DB'de olan key'leri de ekle
                        $extraKeys = array_diff(array_keys($allTranslations), array_merge(...array_values($KEY_GROUPS)));
                        if ($groupName === array_key_last($KEY_GROUPS) && !empty($extraKeys)):
                        ?>
                        <?php foreach ($extraKeys as $extraKey): ?>
                        <tr class="table-warning">
                            <td><code class="small"><?= e($extraKey) ?></code> <span class="badge bg-warning text-dark">extra</span></td>
                            <?php foreach ($LANGS as $lCode => $lLabel): ?>
                            <td>
                                <input type="text"
                                       name="translations[<?= e($extraKey) ?>][<?= e($lCode) ?>]"
                                       class="form-control form-control-sm"
                                       value="<?= e($allTranslations[$extraKey][$lCode] ?? '') ?>">
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="mt-3 text-end">
            <button type="submit" class="btn btn-primary btn-sm">Tümünü Kaydet</button>
        </div>
    </div>
    <?php $first = false; endforeach; ?>
    </div>
</form>

<!-- Yeni Anahtar Ekle -->
<div class="card border-0 shadow-sm mt-4" id="add-key-section">
    <div class="card-header bg-white"><strong>Yeni Çeviri Anahtarı Ekle</strong></div>
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
            <input type="hidden" name="add_key" value="1">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Anahtar <span class="text-danger">*</span></label>
                    <input type="text" name="new_key" class="form-control form-control-sm" required
                           placeholder="ornek_key" pattern="[a-z0-9_]+">
                    <div class="form-text">Küçük harf, rakam, alt çizgi.</div>
                </div>
                <?php foreach ($LANGS as $lCode => $lLabel): ?>
                <div class="col">
                    <label class="form-label"><?= e($lLabel) ?></label>
                    <input type="text" name="new_value_<?= e($lCode) ?>" class="form-control form-control-sm">
                </div>
                <?php endforeach; ?>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Ekle</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Footer Linkleri Çevirisi -->
<?php
$footerLinksAll = [];
$footerLinkTransAll = [];
try {
    $footerLinksAll = $pdo->query('SELECT * FROM footer_links WHERE is_active = 1 ORDER BY column_key, sort_order, id')->fetchAll();
    if ($footerLinksAll) {
        $fIds = array_column($footerLinksAll, 'id');
        $fIn  = implode(',', array_fill(0, count($fIds), '?'));
        $fRows = $pdo->prepare("SELECT * FROM footer_link_translations WHERE footer_link_id IN ({$fIn})")->execute($fIds) ? [] : [];
        $stmt2 = $pdo->prepare("SELECT * FROM footer_link_translations WHERE footer_link_id IN ({$fIn})");
        $stmt2->execute($fIds);
        foreach ($stmt2->fetchAll() as $flt) {
            $footerLinkTransAll[$flt['footer_link_id']][$flt['language']] = $flt['title'];
        }
    }
} catch (Throwable $e) {}
?>

<?php if (!empty($footerLinksAll)): ?>
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white"><strong>Footer Link Çevirileri</strong></div>
    <div class="card-body p-0">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
            <input type="hidden" name="save_footer_link_translations" value="1">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Sütun</th>
                            <th>Link (Varsayılan)</th>
                            <?php foreach ($LANGS as $lCode => $lLabel): ?>
                                <th><?= e($lLabel) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($footerLinksAll as $fl): ?>
                    <tr>
                        <td><small class="text-muted"><?= e($fl['column_key']) ?></small></td>
                        <td><?= e($fl['title']) ?></td>
                        <?php foreach ($LANGS as $lCode => $lLabel): ?>
                        <td>
                            <input type="text"
                                   name="footer_link_tr[<?= $fl['id'] ?>][<?= e($lCode) ?>]"
                                   class="form-control form-control-sm"
                                   value="<?= e($footerLinkTransAll[$fl['id']][$lCode] ?? '') ?>"
                                   placeholder="<?= e($fl['title']) ?>">
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-3 text-end">
                <button type="submit" class="btn btn-primary btn-sm">Footer Çevirilerini Kaydet</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/partials_footer.php'; ?>
