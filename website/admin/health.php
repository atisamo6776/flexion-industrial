<?php
/**
 * Flexion - Veritabanı Sağlık Kontrol Ekranı
 * PHP/DB durumu ve şema bütünlüğünü gösterir.
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin_login();

$pdo    = db();
$dbName = null;

try {
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
} catch (Throwable $e) {
    // ignore
}

/* ================================================================
   Kontrol listesi
================================================================ */

function hc_table_exists(PDO $pdo, ?string $db, string $table): bool
{
    if (!$db) return false;
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?"
        );
        $stmt->execute([$db, $table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function hc_column_exists(PDO $pdo, ?string $db, string $table, string $column): bool
{
    if (!$db) return false;
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$db, $table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

// PHP kontrolleri
$phpChecks = [];

$phpChecks[] = [
    'label' => 'PHP Sürümü',
    'value' => PHP_VERSION,
    'ok'    => version_compare(PHP_VERSION, '7.4', '>='),
    'note'  => 'Minimum PHP 7.4 gereklidir.',
];

$phpChecks[] = [
    'label' => 'PDO MySQL Driver',
    'value' => in_array('mysql', PDO::getAvailableDrivers()) ? 'Aktif' : 'EKSİK',
    'ok'    => in_array('mysql', PDO::getAvailableDrivers()),
    'note'  => 'PDO MySQL eklentisi gereklidir.',
];

$phpChecks[] = [
    'label' => 'FileInfo Eklentisi',
    'value' => extension_loaded('fileinfo') ? 'Aktif' : 'Yok (fallback devrede)',
    'ok'    => true,
    'note'  => 'Yoksa MIME tipi kontrolü için $_FILES[\'type\'] kullanılır.',
];

$phpChecks[] = [
    'label' => 'GD / Image',
    'value' => extension_loaded('gd') ? 'Aktif' : 'Yok',
    'ok'    => true,
    'note'  => 'Şu an zorunlu değil.',
];

$phpChecks[] = [
    'label' => 'MB String',
    'value' => extension_loaded('mbstring') ? 'Aktif' : 'EKSİK',
    'ok'    => extension_loaded('mbstring'),
    'note'  => 'Türkçe karakter işleme için gereklidir.',
];

// DB bağlantı kontrolü
$dbConnected = false;
$dbVersion   = 'N/A';
try {
    $dbVersion   = $pdo->query('SELECT VERSION()')->fetchColumn();
    $dbConnected = true;
} catch (Throwable $e) {
    $dbVersion = 'Bağlantı hatası: ' . $e->getMessage();
}

// Tablo kontrolleri
$requiredTables = [
    'settings'             => 'Genel site ayarları',
    'users'                => 'Admin kullanıcıları',
    'menu_items'           => 'Menü öğeleri',
    'categories'           => 'Kategoriler',
    'products'             => 'Ürünler',
    'product_images'       => 'Ürün ek görselleri',
    'product_spec_tables'  => 'Ürün teknik tablo başlıkları',
    'product_specs'        => 'Ürün teknik özellikler',
    'product_regulations'  => 'Ürün regülasyonları',
    'product_documents'    => 'Ürün dokümanları (PDF vb.)',
    'home_sections'        => 'Ana sayfa bölümleri',
    'pages'                => 'Kurumsal sayfalar',
    'news'                 => 'Haberler',
    'footer_links'         => 'Footer linkleri',
];

$tableResults = [];
$missingTableCount = 0;
foreach ($requiredTables as $tbl => $desc) {
    $exists = hc_table_exists($pdo, $dbName, $tbl);
    if (!$exists) $missingTableCount++;
    $tableResults[$tbl] = ['ok' => $exists, 'desc' => $desc];
}

// Kolon kontrolleri
$requiredColumns = [
    ['table' => 'pages',              'column' => 'banner_image',        'desc' => 'Kurumsal sayfa banner görseli'],
    ['table' => 'pages',              'column' => 'banner_title',        'desc' => 'Kurumsal sayfa banner başlığı'],
    ['table' => 'users',              'column' => 'is_active',           'desc' => 'Kullanıcı aktif/pasif (auth için zorunlu)'],
    ['table' => 'users',              'column' => 'must_change_password', 'desc' => 'İlk giriş şifre değiştirme zorunluluğu'],
    ['table' => 'footer_links',       'column' => 'column_label',        'desc' => 'Footer sütun etiketi'],
    ['table' => 'product_spec_tables','column' => 'is_active',           'desc' => 'Spec tablo aktif/pasif'],
    ['table' => 'product_regulations','column' => 'is_active',           'desc' => 'Regülasyon aktif/pasif'],
    ['table' => 'product_documents',  'column' => 'is_active',           'desc' => 'Doküman aktif/pasif'],
    ['table' => 'home_sections',      'column' => 'section_type',        'desc' => 'Ana sayfa blok tipi'],
    ['table' => 'menu_items',         'column' => 'is_active',           'desc' => 'Menü aktif/pasif'],
];

$columnResults = [];
$missingColumnCount = 0;
foreach ($requiredColumns as $cm) {
    $exists = hc_table_exists($pdo, $dbName, $cm['table'])
              && hc_column_exists($pdo, $dbName, $cm['table'], $cm['column']);
    if (!$exists) $missingColumnCount++;
    $columnResults[] = array_merge($cm, ['ok' => $exists]);
}

// Veri bütünlüğü kontrolleri
$dataChecks = [];
$dataCheckQueries = [
    ['label' => 'Aktif admin kullanıcısı',   'query' => "SELECT COUNT(*) FROM users WHERE is_active = 1",       'warn_zero' => true],
    ['label' => 'Kategori sayısı',            'query' => "SELECT COUNT(*) FROM categories",                      'warn_zero' => false],
    ['label' => 'Ürün sayısı',               'query' => "SELECT COUNT(*) FROM products",                        'warn_zero' => false],
    ['label' => 'Aktif menü öğesi',          'query' => "SELECT COUNT(*) FROM menu_items WHERE is_active = 1",  'warn_zero' => true],
    ['label' => 'Ana sayfa bloğu',           'query' => "SELECT COUNT(*) FROM home_sections WHERE is_active = 1",'warn_zero' => false],
];
foreach ($dataCheckQueries as $dc) {
    try {
        $cnt = (int) $pdo->query($dc['query'])->fetchColumn();
        $dataChecks[] = ['label' => $dc['label'], 'count' => $cnt, 'warn' => $dc['warn_zero'] && $cnt === 0, 'ok' => true];
    } catch (Throwable $e) {
        $dataChecks[] = ['label' => $dc['label'], 'count' => 'HATA', 'warn' => true, 'ok' => false, 'err' => $e->getMessage()];
    }
}

// Ayar kontrolleri
$settingChecks = [];
$settingKeys = ['site_title', 'logo_height', 'news_banner_title'];
try {
    foreach ($settingKeys as $key) {
        $val = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $val->execute([$key]);
        $row = $val->fetch();
        $settingChecks[] = [
            'key'    => $key,
            'value'  => $row ? ($row['setting_value'] ?? '(boş)') : 'Kayıt yok',
            'ok'     => (bool)$row,
        ];
    }
} catch (Throwable $e) {
    $settingChecks[] = ['key' => 'settings tablosu', 'value' => 'Erişilemedi', 'ok' => false];
}

// Schema version
$schemaVersion = 'Belirsiz';
try {
    $sv = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $sv->execute(['schema_version']);
    $row = $sv->fetch();
    $schemaVersion = $row ? $row['setting_value'] : 'Migrasyon çalıştırılmamış';
} catch (Throwable $e) {
    $schemaVersion = 'Erişilemedi';
}

$dataIssueCount = count(array_filter($dataChecks, fn($c) => $c['warn']));

$allGood = $dbConnected
    && $missingTableCount === 0
    && $missingColumnCount === 0
    && !array_filter($phpChecks, fn($c) => !$c['ok']);

include __DIR__ . '/partials_header.php';
?>

<div class="row">
    <div class="col-12">
        <!-- Özet Durum -->
        <div class="alert <?= $allGood ? 'alert-success' : 'alert-warning' ?> d-flex align-items-center gap-3 mb-4">
            <i class="bi <?= $allGood ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> fs-4"></i>
            <div class="flex-grow-1">
                <?php if ($allGood): ?>
                    <strong>Her şey yolunda!</strong> Tüm tablo/kolon kontrolleri başarılı.
                <?php else: ?>
                    <strong>Dikkat:</strong>
                    <?php if ($missingTableCount > 0): ?>
                        <?= $missingTableCount ?> eksik tablo,
                    <?php endif; ?>
                    <?php if ($missingColumnCount > 0): ?>
                        <?= $missingColumnCount ?> eksik kolon,
                    <?php endif; ?>
                    <?php if ($dataIssueCount > 0): ?>
                        <?= $dataIssueCount ?> veri uyarısı tespit edildi.
                    <?php endif; ?>
                    <?php if ($missingTableCount > 0 || $missingColumnCount > 0): ?>
                        500 hatalarını önlemek için migrasyonu çalıştırın.
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php if (!$allGood): ?>
                <a href="migrate.php" class="btn btn-primary btn-sm text-nowrap">
                    <i class="bi bi-play-circle me-1"></i>Migrasyonu Çalıştır
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- PHP & Sunucu -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <strong>PHP &amp; Sunucu</strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Kontrol</th><th>Değer</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($phpChecks as $c): ?>
                            <tr>
                                <td><?= e($c['label']) ?></td>
                                <td class="small text-muted"><?= e($c['value']) ?></td>
                                <td>
                                    <?php if ($c['ok']): ?>
                                        <span class="badge bg-success">OK</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger" title="<?= e($c['note']) ?>">EKSİK</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td>Veritabanı Bağlantısı</td>
                            <td class="small text-muted"><?= e($dbVersion) ?></td>
                            <td>
                                <?php if ($dbConnected): ?>
                                    <span class="badge bg-success">OK</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">HATA</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Veritabanı Adı</td>
                            <td class="small text-muted"><?= e($dbName ?? 'N/A') ?></td>
                            <td><span class="badge bg-<?= $dbName ? 'success' : 'secondary' ?>"><?= $dbName ? 'OK' : 'N/A' ?></span></td>
                        </tr>
                        <tr>
                            <td>Şema Versiyonu</td>
                            <td class="small text-muted"><?= e($schemaVersion) ?></td>
                            <td><span class="badge bg-secondary">Bilgi</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Ayarlar -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <strong>Temel Ayarlar</strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Anahtar</th><th>Değer</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($settingChecks as $sc): ?>
                            <tr>
                                <td class="font-monospace small"><?= e($sc['key']) ?></td>
                                <td class="small text-muted"><?= e(mb_strimwidth((string)$sc['value'], 0, 50, '...')) ?></td>
                                <td>
                                    <?php if ($sc['ok']): ?>
                                        <span class="badge bg-success">OK</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">YOK</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tablo Kontrolleri -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Veritabanı Tabloları</strong>
                <span class="badge <?= $missingTableCount > 0 ? 'bg-danger' : 'bg-success' ?>">
                    <?= count($tableResults) - $missingTableCount ?>/<?= count($tableResults) ?> OK
                </span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Tablo</th><th>Açıklama</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tableResults as $tbl => $info): ?>
                            <tr class="<?= !$info['ok'] ? 'table-danger' : '' ?>">
                                <td class="font-monospace small"><?= e($tbl) ?></td>
                                <td class="small text-muted"><?= e($info['desc']) ?></td>
                                <td>
                                    <?php if ($info['ok']): ?>
                                        <span class="badge bg-success">OK</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">EKSİK</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Kolon Kontrolleri -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Kritik Kolonlar</strong>
                <span class="badge <?= $missingColumnCount > 0 ? 'bg-danger' : 'bg-success' ?>">
                    <?= count($columnResults) - $missingColumnCount ?>/<?= count($columnResults) ?> OK
                </span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Tablo.Kolon</th><th>Açıklama</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($columnResults as $cr): ?>
                            <tr class="<?= !$cr['ok'] ? 'table-danger' : '' ?>">
                                <td class="font-monospace small"><?= e($cr['table']) ?>.<?= e($cr['column']) ?></td>
                                <td class="small text-muted"><?= e($cr['desc']) ?></td>
                                <td>
                                    <?php if ($cr['ok']): ?>
                                        <span class="badge bg-success">OK</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">EKSİK</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Veri Bütünlüğü -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Veri Bütünlüğü</strong>
                <?php if ($dataIssueCount > 0): ?>
                    <span class="badge bg-warning text-dark"><?= $dataIssueCount ?> uyarı</span>
                <?php else: ?>
                    <span class="badge bg-success">OK</span>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Kontrol</th><th>Sayı / Durum</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dataChecks as $dc): ?>
                            <tr class="<?= $dc['warn'] ? 'table-warning' : '' ?>">
                                <td><?= e($dc['label']) ?></td>
                                <td class="small text-muted"><?= e((string)$dc['count']) ?></td>
                                <td>
                                    <?php if (!$dc['ok']): ?>
                                        <span class="badge bg-danger" title="<?= e($dc['err'] ?? '') ?>">HATA</span>
                                    <?php elseif ($dc['warn']): ?>
                                        <span class="badge bg-warning text-dark">UYARI</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">OK</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <a href="migrate.php" class="btn btn-primary">
        <i class="bi bi-play-circle me-1"></i>Migrasyonu Çalıştır
    </a>
    <a href="index.php" class="btn btn-outline-secondary">Admin Paneli</a>
    <button onclick="location.reload()" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-clockwise me-1"></i>Yenile
    </button>
</div>

<?php include __DIR__ . '/partials_footer.php'; ?>
