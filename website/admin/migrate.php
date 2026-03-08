<?php
/**
 * Flexion - İdempotent Veritabanı Migrasyon Aracı
 * Admin panelinden tek tık ile eksik tablo ve kolonları oluşturur.
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin_login();

$pdo = db();
$dbName = null;

try {
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
} catch (Throwable $e) {
    // ignore
}

/* ================================================================
   Migrasyon tanımları
================================================================ */

$tableMigrations = [
    'settings' => "CREATE TABLE `settings` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `setting_key` VARCHAR(100) NOT NULL,
        `setting_value` TEXT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'menu_items' => "CREATE TABLE `menu_items` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `parent_id` INT UNSIGNED NULL DEFAULT NULL,
        `title` VARCHAR(200) NOT NULL,
        `url` VARCHAR(500) NOT NULL DEFAULT '#',
        `sort_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'categories' => "CREATE TABLE `categories` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `parent_id` INT UNSIGNED NULL DEFAULT NULL,
        `name` VARCHAR(200) NOT NULL,
        `slug` VARCHAR(200) NOT NULL,
        `short_description` TEXT NULL,
        `description` LONGTEXT NULL,
        `image` VARCHAR(255) NULL,
        `sort_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'products' => "CREATE TABLE `products` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `category_id` INT UNSIGNED NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `slug` VARCHAR(255) NOT NULL,
        `code` VARCHAR(100) NULL,
        `short_description` TEXT NULL,
        `description` LONGTEXT NULL,
        `main_image` VARCHAR(255) NULL,
        `sort_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'product_images' => "CREATE TABLE `product_images` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `product_id` INT UNSIGNED NOT NULL,
        `image` VARCHAR(255) NOT NULL,
        `sort_order` INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_product_id` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'product_spec_tables' => "CREATE TABLE `product_spec_tables` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `product_id` INT UNSIGNED NOT NULL,
        `title` VARCHAR(255) NULL,
        `sort_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        KEY `idx_product_id` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'product_specs' => "CREATE TABLE `product_specs` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `table_id` INT UNSIGNED NOT NULL,
        `label` VARCHAR(255) NOT NULL,
        `value` VARCHAR(500) NOT NULL,
        `sort_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        KEY `idx_table_id` (`table_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'product_regulations' => "CREATE TABLE `product_regulations` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `product_id` INT UNSIGNED NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `icon` VARCHAR(255) NULL,
        `sort_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        KEY `idx_product_id` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'product_documents' => "CREATE TABLE `product_documents` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `product_id` INT UNSIGNED NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(500) NOT NULL,
        `sort_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        KEY `idx_product_id` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'home_sections' => "CREATE TABLE `home_sections` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `section_type` VARCHAR(50) NOT NULL DEFAULT 'hero',
        `title` VARCHAR(255) NULL,
        `content_json` LONGTEXT NULL,
        `sort_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'pages' => "CREATE TABLE `pages` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `slug` VARCHAR(200) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `content` LONGTEXT NULL,
        `meta_description` VARCHAR(300) NULL,
        `banner_image` VARCHAR(255) NULL,
        `banner_title` VARCHAR(255) NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` INT NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'news' => "CREATE TABLE `news` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(255) NOT NULL,
        `slug` VARCHAR(255) NOT NULL,
        `summary` TEXT NULL,
        `content` LONGTEXT NULL,
        `image` VARCHAR(255) NULL,
        `published_at` DATETIME NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'footer_links' => "CREATE TABLE `footer_links` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `column_key` VARCHAR(50) NOT NULL,
        `column_label` VARCHAR(100) NOT NULL DEFAULT '',
        `title` VARCHAR(255) NOT NULL,
        `url` VARCHAR(500) NOT NULL,
        `sort_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        KEY `idx_column_key` (`column_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'users' => "CREATE TABLE `users` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `username` VARCHAR(100) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `email` VARCHAR(200) NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

$columnMigrations = [
    // pages
    ['table' => 'pages',    'column' => 'banner_image',       'sql' => 'ALTER TABLE `pages` ADD COLUMN `banner_image` VARCHAR(255) NULL AFTER `meta_description`'],
    ['table' => 'pages',    'column' => 'banner_title',       'sql' => 'ALTER TABLE `pages` ADD COLUMN `banner_title` VARCHAR(255) NULL AFTER `banner_image`'],
    ['table' => 'pages',    'column' => 'sort_order',         'sql' => 'ALTER TABLE `pages` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0'],
    // users
    ['table' => 'users',    'column' => 'email',              'sql' => 'ALTER TABLE `users` ADD COLUMN `email` VARCHAR(200) NULL'],
    ['table' => 'users',    'column' => 'is_active',          'sql' => 'ALTER TABLE `users` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1'],
    ['table' => 'users',    'column' => 'must_change_password','sql' => 'ALTER TABLE `users` ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0'],
    // product_spec_tables
    ['table' => 'product_spec_tables', 'column' => 'is_active', 'sql' => 'ALTER TABLE `product_spec_tables` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1'],
    // product_regulations
    ['table' => 'product_regulations', 'column' => 'is_active', 'sql' => 'ALTER TABLE `product_regulations` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1'],
    ['table' => 'product_regulations', 'column' => 'icon',      'sql' => 'ALTER TABLE `product_regulations` ADD COLUMN `icon` VARCHAR(255) NULL'],
    // product_documents
    ['table' => 'product_documents',   'column' => 'is_active', 'sql' => 'ALTER TABLE `product_documents` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1'],
    // product_images
    ['table' => 'product_images',      'column' => 'sort_order','sql' => 'ALTER TABLE `product_images` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0'],
    // categories
    ['table' => 'categories', 'column' => 'is_active',          'sql' => 'ALTER TABLE `categories` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1'],
    ['table' => 'categories', 'column' => 'sort_order',         'sql' => 'ALTER TABLE `categories` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0'],
    // news
    ['table' => 'news',       'column' => 'is_active',          'sql' => 'ALTER TABLE `news` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1'],
    // home_sections
    ['table' => 'home_sections', 'column' => 'section_type',    'sql' => 'ALTER TABLE `home_sections` ADD COLUMN `section_type` VARCHAR(50) NOT NULL DEFAULT \'hero\''],
    ['table' => 'home_sections', 'column' => 'is_active',       'sql' => 'ALTER TABLE `home_sections` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1'],
    // menu_items
    ['table' => 'menu_items',  'column' => 'is_active',         'sql' => 'ALTER TABLE `menu_items` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1'],
    ['table' => 'menu_items',  'column' => 'sort_order',        'sql' => 'ALTER TABLE `menu_items` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0'],
    ['table' => 'menu_items',  'column' => 'parent_id',         'sql' => 'ALTER TABLE `menu_items` ADD COLUMN `parent_id` INT UNSIGNED NULL DEFAULT NULL'],
    // footer_links
    ['table' => 'footer_links',  'column' => 'column_label',    'sql' => 'ALTER TABLE `footer_links` ADD COLUMN `column_label` VARCHAR(100) NOT NULL DEFAULT \'\' AFTER `column_key`'],
    ['table' => 'footer_links',  'column' => 'is_active',       'sql' => 'ALTER TABLE `footer_links` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1'],
];

$settingDefaults = [
    'site_title'          => 'Flexion Industrial',
    'logo_height'         => '40',
    'news_banner_title'   => 'Haberler & Insights',
    'news_banner_image'   => '',
    'company_address'     => '',
    'company_phone'       => '',
    'company_email'       => '',
    'footer_about'        => '',
];

/* ================================================================
   Migrasyon işlemi
================================================================ */
$results = [];

function mg_table_exists(PDO $pdo, string $db, string $table): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?"
    );
    $stmt->execute([$db, $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function mg_column_exists(PDO $pdo, string $db, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$db, $table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

$runMigration = isset($_POST['run']) && $_POST['run'] === '1';

if ($runMigration) {
    // 1. Tablo migrasyonları
    foreach ($tableMigrations as $tableName => $createSql) {
        $label = "Tablo: <code>$tableName</code>";
        if (mg_table_exists($pdo, $dbName, $tableName)) {
            $results[] = ['label' => $label, 'status' => 'skip', 'msg' => 'Zaten var'];
        } else {
            try {
                $pdo->exec($createSql);
                $results[] = ['label' => $label, 'status' => 'ok', 'msg' => 'Oluşturuldu'];
            } catch (Throwable $e) {
                $results[] = ['label' => $label, 'status' => 'fail', 'msg' => $e->getMessage()];
            }
        }
    }

    // 2. Kolon migrasyonları
    foreach ($columnMigrations as $cm) {
        if (!$cm['sql']) continue;
        $label = "Kolon: <code>{$cm['table']}.{$cm['column']}</code>";
        if (!mg_table_exists($pdo, $dbName, $cm['table'])) {
            $results[] = ['label' => $label, 'status' => 'skip', 'msg' => 'Tablo yok, tablo migrasyonu çalıştı'];
            continue;
        }
        if (mg_column_exists($pdo, $dbName, $cm['table'], $cm['column'])) {
            $results[] = ['label' => $label, 'status' => 'skip', 'msg' => 'Zaten var'];
        } else {
            try {
                $pdo->exec($cm['sql']);
                $results[] = ['label' => $label, 'status' => 'ok', 'msg' => 'Eklendi'];
            } catch (Throwable $e) {
                $results[] = ['label' => $label, 'status' => 'fail', 'msg' => $e->getMessage()];
            }
        }
    }

    // 3. Varsayılan ayarlar
    foreach ($settingDefaults as $key => $value) {
        $label = "Ayar: <code>$key</code>";
        try {
            $pdo->prepare(
                'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
                 ON DUPLICATE KEY UPDATE setting_key = setting_key'
            )->execute([':k' => $key, ':v' => $value]);
            $results[] = ['label' => $label, 'status' => 'ok', 'msg' => 'Eklendi (varsa dokunulmadı)'];
        } catch (Throwable $e) {
            $results[] = ['label' => $label, 'status' => 'fail', 'msg' => $e->getMessage()];
        }
    }

    // 4. Varsayılan admin kullanıcısı
    $label = 'Kullanıcı: <code>admin</code>';
    try {
        $cnt = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'")->fetchColumn();
        if ($cnt === 0) {
            $hash = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO users (username, password, must_change_password) VALUES (?, ?, 1)')
                ->execute(['admin', $hash]);
            $results[] = ['label' => $label, 'status' => 'ok', 'msg' => 'Oluşturuldu (admin/admin123)'];
        } else {
            $results[] = ['label' => $label, 'status' => 'skip', 'msg' => 'Zaten var'];
        }
    } catch (Throwable $e) {
        $results[] = ['label' => $label, 'status' => 'fail', 'msg' => $e->getMessage()];
    }

    // schema_version güncelle
    try {
        $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        )->execute([':k' => 'schema_version', ':v' => '2.0.' . date('Ymd')]);
    } catch (Throwable $e) {
        // ignore
    }
}

include __DIR__ . '/partials_header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Veritabanı Migrasyonu</strong>
                <a href="health.php" class="btn btn-sm btn-outline-secondary">← Sağlık Kontrol</a>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Bu araç, veritabanında eksik olan tabloları ve kolonları <strong>güvenli ve idempotent</strong> (tekrar çalıştırılabilir) biçimde oluşturur.
                    Mevcut verilere dokunmaz; sadece eksik yapıları ekler.
                </p>

                <?php if (!$runMigration): ?>
                    <div class="alert alert-warning">
                        <strong>Dikkat:</strong> Bu işlemi çalıştırmadan önce veritabanını yedekleyin.
                        Mevcut veriler silinmez, yalnızca eksik tablolar/kolonlar eklenir.
                    </div>
                    <form method="post">
                        <input type="hidden" name="run" value="1">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <button type="submit" class="btn btn-primary btn-lg w-100" onclick="return confirm('Migrasyonu başlatmak istediğinize emin misiniz?')">
                            <i class="bi bi-play-circle me-2"></i>Migrasyonu Başlat
                        </button>
                    </form>
                <?php else: ?>
                    <?php
                    $okCount   = count(array_filter($results, fn($r) => $r['status'] === 'ok'));
                    $skipCount = count(array_filter($results, fn($r) => $r['status'] === 'skip'));
                    $failCount = count(array_filter($results, fn($r) => $r['status'] === 'fail'));
                    ?>
                    <div class="alert <?= $failCount > 0 ? 'alert-danger' : 'alert-success' ?> mb-3">
                        Migrasyon tamamlandı:
                        <strong><?= $okCount ?></strong> oluşturuldu,
                        <strong><?= $skipCount ?></strong> atlandı (zaten vardı),
                        <strong><?= $failCount ?></strong> hata.
                    </div>

                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Adım</th>
                                <th>Durum</th>
                                <th>Mesaj</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $r): ?>
                                <tr>
                                    <td><?= $r['label'] ?></td>
                                    <td>
                                        <?php if ($r['status'] === 'ok'): ?>
                                            <span class="badge bg-success">OK</span>
                                        <?php elseif ($r['status'] === 'skip'): ?>
                                            <span class="badge bg-secondary">SKIP</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">FAIL</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= e($r['msg']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="mt-3 d-flex gap-2">
                        <a href="health.php" class="btn btn-primary">Sağlık Kontrol ile Doğrula</a>
                        <a href="migrate.php" class="btn btn-outline-secondary">Tekrar Çalıştır</a>
                        <a href="index.php" class="btn btn-outline-secondary">Admin Paneli</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials_footer.php'; ?>
