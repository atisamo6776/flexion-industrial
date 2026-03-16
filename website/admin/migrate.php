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

    // ── i18n: Çeviri tabloları ────────────────────────────────────────────────
    'category_translations' => "CREATE TABLE `category_translations` (
        `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `category_id`       INT UNSIGNED  NOT NULL,
        `language`          VARCHAR(5)    NOT NULL DEFAULT 'en',
        `name`              VARCHAR(200)  NOT NULL DEFAULT '',
        `slug`              VARCHAR(200)  NOT NULL DEFAULT '',
        `short_description` TEXT          NULL,
        `description`       LONGTEXT      NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_cat_lang` (`category_id`, `language`),
        UNIQUE KEY `uk_cat_lang_slug` (`language`, `slug`),
        KEY `idx_category_id` (`category_id`),
        KEY `idx_language` (`language`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'product_translations' => "CREATE TABLE `product_translations` (
        `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `product_id`        INT UNSIGNED  NOT NULL,
        `language`          VARCHAR(5)    NOT NULL DEFAULT 'en',
        `name`              VARCHAR(255)  NOT NULL DEFAULT '',
        `slug`              VARCHAR(255)  NOT NULL DEFAULT '',
        `short_description` TEXT          NULL,
        `description`       LONGTEXT      NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_prod_lang` (`product_id`, `language`),
        UNIQUE KEY `uk_prod_lang_slug` (`language`, `slug`),
        KEY `idx_product_id` (`product_id`),
        KEY `idx_language` (`language`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'page_translations' => "CREATE TABLE `page_translations` (
        `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `page_id`          INT UNSIGNED  NOT NULL,
        `language`         VARCHAR(5)    NOT NULL DEFAULT 'en',
        `slug`             VARCHAR(200)  NOT NULL DEFAULT '',
        `title`            VARCHAR(255)  NOT NULL DEFAULT '',
        `content`          LONGTEXT      NULL,
        `meta_description` VARCHAR(300)  NULL,
        `banner_title`     VARCHAR(255)  NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_page_lang` (`page_id`, `language`),
        UNIQUE KEY `uk_page_lang_slug` (`language`, `slug`),
        KEY `idx_page_id` (`page_id`),
        KEY `idx_language` (`language`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'news_translations' => "CREATE TABLE `news_translations` (
        `id`       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `news_id`  INT UNSIGNED  NOT NULL,
        `language` VARCHAR(5)    NOT NULL DEFAULT 'en',
        `slug`     VARCHAR(255)  NOT NULL DEFAULT '',
        `title`    VARCHAR(255)  NOT NULL DEFAULT '',
        `summary`  TEXT          NULL,
        `content`  LONGTEXT      NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_news_lang` (`news_id`, `language`),
        UNIQUE KEY `uk_news_lang_slug` (`language`, `slug`),
        KEY `idx_news_id` (`news_id`),
        KEY `idx_language` (`language`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // ── i18n: UI & Site metinleri ──────────────────────────────────────────────
    'site_translations' => "CREATE TABLE `site_translations` (
        `id`       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `key`      VARCHAR(100)  NOT NULL,
        `language` VARCHAR(5)    NOT NULL DEFAULT 'en',
        `value`    TEXT          NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_key_lang` (`key`, `language`),
        KEY `idx_language` (`language`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'home_section_translations' => "CREATE TABLE `home_section_translations` (
        `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `section_id`   INT UNSIGNED  NOT NULL,
        `language`     VARCHAR(5)    NOT NULL DEFAULT 'en',
        `title`        VARCHAR(255)  NULL,
        `content_json` LONGTEXT      NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_section_lang` (`section_id`, `language`),
        KEY `idx_section_id` (`section_id`),
        KEY `idx_language` (`language`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'menu_item_translations' => "CREATE TABLE `menu_item_translations` (
        `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `menu_item_id` INT UNSIGNED  NOT NULL,
        `language`     VARCHAR(5)    NOT NULL DEFAULT 'en',
        `title`        VARCHAR(200)  NOT NULL DEFAULT '',
        `url`          VARCHAR(500)  NOT NULL DEFAULT '',
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_menu_lang` (`menu_item_id`, `language`),
        KEY `idx_menu_item_id` (`menu_item_id`),
        KEY `idx_language` (`language`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'footer_link_translations' => "CREATE TABLE `footer_link_translations` (
        `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `footer_link_id` INT UNSIGNED  NOT NULL,
        `language`       VARCHAR(5)    NOT NULL DEFAULT 'en',
        `title`          VARCHAR(255)  NOT NULL DEFAULT '',
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_fl_lang` (`footer_link_id`, `language`),
        KEY `idx_footer_link_id` (`footer_link_id`),
        KEY `idx_language` (`language`)
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
    ['table' => 'categories', 'column' => 'parent_id',          'sql' => 'ALTER TABLE `categories` ADD COLUMN `parent_id` INT UNSIGNED NULL DEFAULT NULL'],
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
    ['table' => 'footer_links',  'column' => 'column_label',      'sql' => 'ALTER TABLE `footer_links` ADD COLUMN `column_label` VARCHAR(100) NOT NULL DEFAULT \'\' AFTER `column_key`'],
    ['table' => 'footer_links',  'column' => 'is_active',         'sql' => 'ALTER TABLE `footer_links` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1'],
    // pages banner stil kolonları (db_patch1)
    ['table' => 'pages', 'column' => 'banner_opacity',        'sql' => 'ALTER TABLE `pages` ADD COLUMN `banner_opacity` TINYINT NOT NULL DEFAULT 50'],
    ['table' => 'pages', 'column' => 'banner_blur',           'sql' => 'ALTER TABLE `pages` ADD COLUMN `banner_blur` TINYINT NOT NULL DEFAULT 0'],
    ['table' => 'pages', 'column' => 'banner_title_color',    'sql' => 'ALTER TABLE `pages` ADD COLUMN `banner_title_color` VARCHAR(20) NOT NULL DEFAULT \'#ffffff\''],
    ['table' => 'pages', 'column' => 'banner_title_size',     'sql' => 'ALTER TABLE `pages` ADD COLUMN `banner_title_size` VARCHAR(10) NOT NULL DEFAULT \'2rem\''],
    ['table' => 'pages', 'column' => 'banner_title_position', 'sql' => 'ALTER TABLE `pages` ADD COLUMN `banner_title_position` VARCHAR(10) NOT NULL DEFAULT \'center\''],
];

$settingDefaults = [
    'site_title'               => 'Flexion Industrial',
    'logo_height'              => '40',
    'news_banner_title'        => 'Haberler & Insights',
    'news_banner_image'        => '',
    'news_banner_opacity'      => '50',
    'news_banner_blur'         => '0',
    'news_banner_title_color'  => '#ffffff',
    'news_banner_title_size'   => '2rem',
    'news_banner_title_position' => 'center',
    'google_maps_embed'        => '',
    'show_header_title'        => '1',
    'company_address'          => '',
    'company_phone'            => '',
    'company_email'            => '',
    'footer_about'             => '',
];

// contact_submissions tablosu
$tableMigrations['contact_submissions'] = "CREATE TABLE `contact_submissions` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `type`       VARCHAR(30)   NOT NULL DEFAULT 'contact',
    `product_id` INT UNSIGNED  NULL     DEFAULT NULL,
    `name`       VARCHAR(200)  NOT NULL DEFAULT '',
    `email`      VARCHAR(200)  NOT NULL DEFAULT '',
    `phone`      VARCHAR(50)   NULL     DEFAULT NULL,
    `company`    VARCHAR(200)  NULL     DEFAULT NULL,
    `country`    VARCHAR(100)  NULL     DEFAULT NULL,
    `message`    TEXT          NOT NULL,
    `is_read`    TINYINT(1)    NOT NULL DEFAULT 0,
    `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_type` (`type`),
    KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

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

    // 4. site_translations: varsayılan EN değerleri (varsa dokunma)
    $siteTranslationDefaults = [
        // ─ Genel ──────────────────────────────────────────────────────────
        ['site_title',               'en', 'Flexion Industrial'],
        ['site_title',               'de', 'Flexion Industrial'],
        ['site_title',               'it', 'Flexion Industrial'],
        ['site_title',               'fr', 'Flexion Industrial'],
        ['topbar_text',              'en', 'Industrial rubber and cable solutions'],
        ['topbar_text',              'de', 'Industrielle Gummi- und Kabellösungen'],
        ['topbar_text',              'it', 'Soluzioni industriali in gomma e cavi'],
        ['topbar_text',              'fr', 'Solutions industrielles en caoutchouc et câbles'],
        ['meta_description',         'en', 'Flexion industrial hose and cable solutions'],
        ['meta_description',         'de', 'Flexion industrielle Schlauch- und Kabellösungen'],
        ['meta_description',         'it', 'Soluzioni industriali per tubi e cavi Flexion'],
        ['meta_description',         'fr', 'Solutions industrielles de tuyaux et câbles Flexion'],
        // ─ Site arama ─────────────────────────────────────────────────────
        ['search_placeholder',       'en', 'Search products...'],
        ['search_placeholder',       'de', 'Produkte suchen...'],
        ['search_placeholder',       'it', 'Cerca prodotti...'],
        ['search_placeholder',       'fr', 'Rechercher des produits...'],
        ['nav_language',              'en', 'Language'],
        ['nav_language',              'de', 'Sprache'],
        ['nav_language',              'it', 'Lingua'],
        ['nav_language',              'fr', 'Langue'],
        ['nav_open_menu',             'en', 'Open menu'],
        ['nav_open_menu',             'de', 'Menü öffnen'],
        ['nav_open_menu',             'it', 'Apri menu'],
        ['nav_open_menu',             'fr', 'Ouvrir le menu'],
        ['nav_close_menu',            'en', 'Close menu'],
        ['nav_close_menu',            'de', 'Menü schließen'],
        ['nav_close_menu',            'it', 'Chiudi menu'],
        ['nav_close_menu',            'fr', 'Fermer le menu'],
        // ─ Butonlar ────────────────────────────────────────────────────────
        ['btn_submit',               'en', 'Send'],
        ['btn_submit',               'de', 'Senden'],
        ['btn_submit',               'it', 'Invia'],
        ['btn_submit',               'fr', 'Envoyer'],
        ['btn_view_all',             'en', 'View All'],
        ['btn_view_all',             'de', 'Alle anzeigen'],
        ['btn_view_all',             'it', 'Vedi tutti'],
        ['btn_view_all',             'fr', 'Voir tout'],
        // ─ Ana sayfa ──────────────────────────────────────────────────────
        ['home_btn_view_sectors',    'en', 'View All Categories'],
        ['home_btn_view_sectors',    'de', 'Alle Kategorien anzeigen'],
        ['home_btn_view_sectors',    'it', 'Vedi tutte le categorie'],
        ['home_btn_view_sectors',    'fr', 'Voir toutes les catégories'],
        ['home_btn_view_categories', 'en', 'View All Categories'],
        ['home_btn_view_categories', 'de', 'Alle Kategorien anzeigen'],
        ['home_btn_view_categories', 'it', 'Vedi tutte le categorie'],
        ['home_btn_view_categories', 'fr', 'Voir toutes les catégories'],
        ['home_no_categories',       'en', 'No categories added yet.'],
        ['home_no_categories',       'de', 'Noch keine Kategorien hinzugefügt.'],
        ['home_no_categories',       'it', 'Nessuna categoria aggiunta.'],
        ['home_no_categories',       'fr', 'Aucune catégorie ajoutée.'],
        ['home_upload_image',        'en', 'Upload image (Admin → Home Sections)'],
        ['home_upload_image',        'de', 'Bild hochladen (Admin → Startseite)'],
        ['home_upload_image',        'it', 'Carica immagine (Admin → Sezioni home)'],
        ['home_upload_image',        'fr', 'Télécharger image (Admin → Sections accueil)'],
        // ─ Haberler ───────────────────────────────────────────────────────
        ['news_banner_title',        'en', 'News & Insights'],
        ['news_banner_title',        'de', 'Neuigkeiten & Einblicke'],
        ['news_banner_title',        'it', 'Notizie & Approfondimenti'],
        ['news_banner_title',        'fr', 'Actualités & Perspectives'],
        ['news_other',               'en', 'Other News'],
        ['news_other',               'de', 'Weitere Neuigkeiten'],
        ['news_other',               'it', 'Altre notizie'],
        ['news_other',               'fr', 'Autres actualités'],
        ['news_back',                'en', 'Back to news'],
        ['news_back',                'de', 'Zurück zu den Neuigkeiten'],
        ['news_back',                'it', 'Torna alle notizie'],
        ['news_back',                'fr', 'Retour aux actualités'],
        // ─ Kategoriler ────────────────────────────────────────────────────
        ['cat_not_found',            'en', 'Category not found'],
        ['cat_not_found',            'de', 'Kategorie nicht gefunden'],
        ['cat_not_found',            'it', 'Categoria non trovata'],
        ['cat_not_found',            'fr', 'Catégorie introuvable'],
        ['cat_back_sectors',         'en', 'Back to all categories'],
        ['cat_back_sectors',         'de', 'Zurück zu allen Kategorien'],
        ['cat_back_sectors',         'it', 'Torna a tutte le categorie'],
        ['cat_back_sectors',         'fr', 'Retour à toutes les catégories'],
        ['cat_back_categories',      'en', 'Back to all categories'],
        ['cat_back_categories',      'de', 'Zurück zu allen Kategorien'],
        ['cat_back_categories',      'it', 'Torna a tutte le categorie'],
        ['cat_back_categories',      'fr', 'Retour à toutes les catégories'],
        ['cat_categories_title',     'en', 'All Categories'],
        ['cat_categories_title',     'de', 'Alle Kategorien'],
        ['cat_categories_title',     'it', 'Tutte le categorie'],
        ['cat_categories_title',     'fr', 'Toutes les catégories'],
        ['cat_categories_subtitle',  'en', 'Industrial cable and hose solutions for every application.'],
        ['cat_categories_subtitle',  'de', 'Industrielle Kabel- und Schlauchlösungen für jede Anwendung.'],
        ['cat_categories_subtitle',  'it', 'Soluzioni industriali di cavi e tubi per ogni applicazione.'],
        ['cat_categories_subtitle',  'fr', 'Solutions industrielles de câbles et tuyaux pour chaque application.'],
        ['cat_categories_title',     'en', 'Categories'],
        ['cat_categories_title',     'de', 'Kategorien'],
        ['cat_categories_title',     'it', 'Categorie'],
        ['cat_categories_title',     'fr', 'Catégories'],
        ['cat_products_count',       'en', 'product(s)'],
        ['cat_products_count',       'de', 'Produkt(e)'],
        ['cat_products_count',       'it', 'prodotto/i'],
        ['cat_products_count',       'fr', 'produit(s)'],
        ['cat_sort_relevance',       'en', 'Relevance'],
        ['cat_sort_relevance',       'de', 'Relevanz'],
        ['cat_sort_relevance',       'it', 'Rilevanza'],
        ['cat_sort_relevance',       'fr', 'Pertinence'],
        ['cat_sort_az',              'en', 'A–Z'],
        ['cat_sort_az',              'de', 'A–Z'],
        ['cat_sort_az',              'it', 'A–Z'],
        ['cat_sort_az',              'fr', 'A–Z'],
        ['cat_sort_za',              'en', 'Z–A'],
        ['cat_sort_za',              'de', 'Z–A'],
        ['cat_sort_za',              'it', 'Z–A'],
        ['cat_sort_za',              'fr', 'Z–A'],
        // ─ Ürün ───────────────────────────────────────────────────────────
        ['prod_not_found',           'en', 'Product not found'],
        ['prod_not_found',           'de', 'Produkt nicht gefunden'],
        ['prod_not_found',           'it', 'Prodotto non trovato'],
        ['prod_not_found',           'fr', 'Produit introuvable'],
        ['prod_back',                'en', 'Back to products'],
        ['prod_back',                'de', 'Zurück zu den Produkten'],
        ['prod_back',                'it', 'Torna ai prodotti'],
        ['prod_back',                'fr', 'Retour aux produits'],
        ['prod_inquiry_title',       'en', 'Request Information'],
        ['prod_inquiry_title',       'de', 'Informationen anfragen'],
        ['prod_inquiry_title',       'it', 'Richiedi informazioni'],
        ['prod_inquiry_title',       'fr', 'Demander des informations'],
        ['prod_inquiry_sent',        'en', 'Your request has been received. We will contact you shortly.'],
        ['prod_inquiry_sent',        'de', 'Ihre Anfrage wurde erhalten. Wir werden uns in Kürze melden.'],
        ['prod_inquiry_sent',        'it', 'La tua richiesta è stata ricevuta. La contatteremo a breve.'],
        ['prod_inquiry_sent',        'fr', 'Votre demande a été reçue. Nous vous contacterons prochainement.'],
        ['prod_related',             'en', 'Related Products'],
        ['prod_related',             'de', 'Verwandte Produkte'],
        ['prod_related',             'it', 'Prodotti correlati'],
        ['prod_related',             'fr', 'Produits connexes'],
        ['prod_spec_title',          'en', 'Technical Specifications'],
        ['prod_spec_title',          'de', 'Technische Spezifikationen'],
        ['prod_spec_title',          'it', 'Specifiche tecniche'],
        ['prod_spec_title',          'fr', 'Spécifications techniques'],
        ['prod_docs_title',          'en', 'Documents'],
        ['prod_docs_title',          'de', 'Dokumente'],
        ['prod_docs_title',          'it', 'Documenti'],
        ['prod_docs_title',          'fr', 'Documents'],
        ['prod_regs_title',          'en', 'Regulations & Certifications'],
        ['prod_regs_title',          'de', 'Normen & Zertifizierungen'],
        ['prod_regs_title',          'it', 'Normative & Certificazioni'],
        ['prod_regs_title',          'fr', 'Réglementations & Certifications'],
        // ─ Çerez ──────────────────────────────────────────────────────────
        ['cookie_message',           'en', 'This website uses cookies to provide the best experience.'],
        ['cookie_message',           'de', 'Diese Website verwendet Cookies, um das beste Erlebnis zu bieten.'],
        ['cookie_message',           'it', 'Questo sito utilizza cookie per offrirti la migliore esperienza.'],
        ['cookie_message',           'fr', 'Ce site utilise des cookies pour offrir la meilleure expérience.'],
        ['cookie_policy_link',       'en', 'Privacy Policy'],
        ['cookie_policy_link',       'de', 'Datenschutzerklärung'],
        ['cookie_policy_link',       'it', 'Informativa sulla privacy'],
        ['cookie_policy_link',       'fr', 'Politique de confidentialité'],
        ['cookie_accept',            'en', 'Accept'],
        ['cookie_accept',            'de', 'Akzeptieren'],
        ['cookie_accept',            'it', 'Accetta'],
        ['cookie_accept',            'fr', 'Accepter'],
        ['cookie_reject',            'en', 'Decline'],
        ['cookie_reject',            'de', 'Ablehnen'],
        ['cookie_reject',            'it', 'Rifiuta'],
        ['cookie_reject',            'fr', 'Refuser'],
        // ─ Footer ─────────────────────────────────────────────────────────
        ['footer_rights',            'en', 'All rights reserved.'],
        ['footer_rights',            'de', 'Alle Rechte vorbehalten.'],
        ['footer_rights',            'it', 'Tutti i diritti riservati.'],
        ['footer_rights',            'fr', 'Tous droits réservés.'],
        ['footer_col_kurumsal',      'en', 'Corporate'],
        ['footer_col_iletisim',      'en', 'Contact'],
        ['footer_col_urunler',       'en', 'Products'],
        ['footer_col_contact',       'en', 'Contact'],
        ['footer_col_products',      'en', 'Products'],
        ['footer_col_corporate',     'en', 'Corporate'],
        ['footer_col_bize_ulasin',   'en', 'Get in Touch'],
        // ─ 404 ────────────────────────────────────────────────────────────
        ['404_title',                'en', 'Page Not Found'],
        ['404_title',                'de', 'Seite nicht gefunden'],
        ['404_title',                'it', 'Pagina non trovata'],
        ['404_title',                'fr', 'Page introuvable'],
        ['404_back',                 'en', 'Back to homepage'],
        ['404_back',                 'de', 'Zurück zur Startseite'],
        ['404_back',                 'it', 'Torna alla home'],
        ['404_back',                 'fr', "Retour à l'accueil"],
        // ─ Form alanları ──────────────────────────────────────────────────
        ['form_name',                'en', 'Name'],
        ['form_name',                'de', 'Name'],
        ['form_name',                'it', 'Nome'],
        ['form_name',                'fr', 'Nom'],
        ['form_surname',             'en', 'Surname'],
        ['form_surname',             'de', 'Nachname'],
        ['form_surname',             'it', 'Cognome'],
        ['form_surname',             'fr', 'Prénom'],
        ['form_email',               'en', 'E-mail'],
        ['form_email',               'de', 'E-Mail'],
        ['form_email',               'it', 'E-mail'],
        ['form_email',               'fr', 'E-mail'],
        ['form_phone',               'en', 'Phone'],
        ['form_phone',               'de', 'Telefon'],
        ['form_phone',               'it', 'Telefono'],
        ['form_phone',               'fr', 'Téléphone'],
        ['form_company',             'en', 'Company'],
        ['form_company',             'de', 'Unternehmen'],
        ['form_company',             'it', 'Azienda'],
        ['form_company',             'fr', 'Société'],
        ['form_country',             'en', 'Country'],
        ['form_country',             'de', 'Land'],
        ['form_country',             'it', 'Paese'],
        ['form_country',             'fr', 'Pays'],
        ['form_message',             'en', 'Message'],
        ['form_message',             'de', 'Nachricht'],
        ['form_message',             'it', 'Messaggio'],
        ['form_message',             'fr', 'Message'],
        ['form_contact_success',     'en', 'Your message has been sent. We will contact you shortly.'],
        ['form_contact_success',     'de', 'Ihre Nachricht wurde gesendet. Wir werden uns in Kürze melden.'],
        ['form_contact_success',     'it', 'Il tuo messaggio è stato inviato. La contatteremo a breve.'],
        ['form_contact_success',     'fr', 'Votre message a été envoyé. Nous vous contacterons prochainement.'],
        ['form_error_csrf',          'en', 'Security check failed. Please refresh and try again.'],
        ['form_error_csrf',         'de', 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden.'],
        ['form_error_csrf',         'it', 'Controllo di sicurezza fallito. Ricarica la pagina e riprova.'],
        ['form_error_csrf',         'fr', 'Échec de la vérification. Veuillez actualiser et réessayer.'],
        ['form_error_required',      'en', 'Please fill in the required fields (Name, Email, Message).'],
        ['form_error_required',     'de', 'Bitte füllen Sie die Pflichtfelder aus (Name, E-Mail, Nachricht).'],
        ['form_error_required',     'it', 'Compila i campi obbligatori (Nome, Email, Messaggio).'],
        ['form_error_required',     'fr', 'Veuillez remplir les champs obligatoires (Nom, E-mail, Message).'],
        ['form_error_email',         'en', 'Please enter a valid email address.'],
        ['form_error_email',        'de', 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'],
        ['form_error_email',        'it', 'Inserisci un indirizzo email valido.'],
        ['form_error_email',        'fr', 'Veuillez entrer une adresse e-mail valide.'],
    ];

    if (mg_table_exists($pdo, $dbName, 'site_translations')) {
        $stmtST = $pdo->prepare(
            'INSERT INTO site_translations (`key`, `language`, `value`) VALUES (:k, :l, :v)
             ON DUPLICATE KEY UPDATE `key` = `key`'
        );
        foreach ($siteTranslationDefaults as [$key, $lang, $val]) {
            try {
                $stmtST->execute([':k' => $key, ':l' => $lang, ':v' => $val]);
            } catch (Throwable $e) {
                // ignore
            }
        }
        $results[] = ['label' => 'site_translations: varsayılan değerler', 'status' => 'ok', 'msg' => count($siteTranslationDefaults) . ' satır işlendi'];
    }

    // 5. Kategori çevirileri — tüm diller için, slug ile lookup
    $categoryTranslationSeeds = [
        'water-hoses' => [
            'de' => ['name' => 'Wasserschläuche',          'slug' => 'wasserschlaeuche',               'short_description' => 'Schläuche für Wasser und allgemeine Dienste'],
            'it' => ['name' => 'Tubi per acqua',            'slug' => 'tubi-per-acqua',                 'short_description' => 'Tubi per acqua e servizi generali'],
            'fr' => ['name' => 'Tuyaux pour eau',           'slug' => 'tuyaux-pour-eau',                'short_description' => 'Tuyaux pour l\'eau et services généraux'],
        ],
        'air-gas-hoses' => [
            'de' => ['name' => 'Luft-Gas-Schläuche',        'slug' => 'luft-gas-schlaeuche',            'short_description' => 'Schläuche für Luft, Gas und Pneumatik'],
            'it' => ['name' => 'Tubi aria-gas',             'slug' => 'tubi-aria-gas',                  'short_description' => 'Tubi per aria compressa, gas e pneumatica'],
            'fr' => ['name' => 'Tuyaux air-gaz',            'slug' => 'tuyaux-air-gaz',                 'short_description' => 'Tuyaux pour air comprimé, gaz et pneumatique'],
        ],
        'oil-petroleum-hoses' => [
            'de' => ['name' => 'Öl- und Kraftstoffschläuche', 'slug' => 'oel-und-kraftstoffschlaeuche', 'short_description' => 'Schläuche für Öl, Kraftstoff und Petroleum'],
            'it' => ['name' => 'Tubi per olio e petrolio',  'slug' => 'tubi-per-olio-e-petrolio',        'short_description' => 'Tubi per olio, carburante e petrolio'],
            'fr' => ['name' => 'Tuyaux huile et pétrole',   'slug' => 'tuyaux-huile-et-petrole',         'short_description' => 'Tuyaux pour huile, carburant et pétrole'],
        ],
        'welding-hoses' => [
            'de' => ['name' => 'Schweißschläuche',          'slug' => 'schweissschlaeuche',              'short_description' => 'Schläuche für Schweißanwendungen'],
            'it' => ['name' => 'Tubi per saldatura',        'slug' => 'tubi-per-saldatura',              'short_description' => 'Tubi per applicazioni di saldatura'],
            'fr' => ['name' => 'Tuyaux de soudage',         'slug' => 'tuyaux-de-soudage',               'short_description' => 'Tuyaux pour applications de soudage'],
        ],
        'food-hoses' => [
            'de' => ['name' => 'Lebensmittelschläuche',     'slug' => 'lebensmittelschlaeuche',          'short_description' => 'Lebensmittelgeeignete Schläuche'],
            'it' => ['name' => 'Tubi alimentari',           'slug' => 'tubi-alimentari',                 'short_description' => 'Tubi per uso alimentare'],
            'fr' => ['name' => 'Tuyaux alimentaires',       'slug' => 'tuyaux-alimentaires',             'short_description' => 'Tuyaux pour usage alimentaire'],
        ],
        'material-handling-hoses' => [
            'de' => ['name' => 'Materialhandling-Schläuche', 'slug' => 'materialhandling-schlaeuche',   'short_description' => 'Schläuche für Materialförderung und -transport'],
            'it' => ['name' => 'Tubi per movimentazione materiali', 'slug' => 'tubi-movimentazione-materiali', 'short_description' => 'Tubi per la movimentazione di materiali'],
            'fr' => ['name' => 'Tuyaux de manutention',     'slug' => 'tuyaux-de-manutention',           'short_description' => 'Tuyaux pour la manutention de matériaux'],
        ],
        'sewer-cleaning-hoses' => [
            'de' => ['name' => 'Kanalreinigungsschläuche',  'slug' => 'kanalreinigungsschlaeuche',       'short_description' => 'Schläuche für Kanalreinigung und Spülung'],
            'it' => ['name' => 'Tubi per pulizia fognature', 'slug' => 'tubi-pulizia-fognature',          'short_description' => 'Tubi per la pulizia delle fognature'],
            'fr' => ['name' => 'Tuyaux de débouchage',      'slug' => 'tuyaux-de-debouchage',            'short_description' => 'Tuyaux pour le débouchage des canalisations'],
        ],
        'steam-hoses' => [
            'de' => ['name' => 'Dampfschläuche',            'slug' => 'dampfschlaeuche',                 'short_description' => 'Schläuche für Dampf und Hochtemperaturanwendungen'],
            'it' => ['name' => 'Tubi vapore',               'slug' => 'tubi-vapore',                     'short_description' => 'Tubi per vapore e alte temperature'],
            'fr' => ['name' => 'Tuyaux vapeur',             'slug' => 'tuyaux-vapeur',                   'short_description' => 'Tuyaux pour vapeur et hautes températures'],
        ],
        'chemical-hoses' => [
            'de' => ['name' => 'Chemikalienschläuche',      'slug' => 'chemikalienschlaeuche',           'short_description' => 'Schläuche für chemische Anwendungen'],
            'it' => ['name' => 'Tubi chimici',              'slug' => 'tubi-chimici',                    'short_description' => 'Tubi per applicazioni chimiche'],
            'fr' => ['name' => 'Tuyaux chimiques',          'slug' => 'tuyaux-chimiques',                'short_description' => 'Tuyaux pour applications chimiques'],
        ],
        'hydraulic-hoses' => [
            'de' => ['name' => 'Hydraulikschläuche',        'slug' => 'hydraulikschlaeuche',             'short_description' => 'Schläuche für Hydrauliksysteme'],
            'it' => ['name' => 'Tubi idraulici',            'slug' => 'tubi-idraulici',                  'short_description' => 'Tubi per sistemi idraulici'],
            'fr' => ['name' => 'Tuyaux hydrauliques',       'slug' => 'tuyaux-hydrauliques',             'short_description' => 'Tuyaux pour systèmes hydrauliques'],
        ],
    ];

    if (mg_table_exists($pdo, $dbName, 'category_translations') && mg_table_exists($pdo, $dbName, 'categories')) {
        $catTrStmt = $pdo->prepare(
            'INSERT INTO category_translations (category_id, language, name, slug, short_description)
             VALUES (:cid, :lang, :name, :slug, :sdesc)
             ON DUPLICATE KEY UPDATE category_id = category_id'
        );
        $catTrInserted = 0;
        foreach ($categoryTranslationSeeds as $enSlug => $langs) {
            $catIdStmt = $pdo->prepare('SELECT id FROM categories WHERE slug = ? LIMIT 1');
            $catIdStmt->execute([$enSlug]);
            $catId = $catIdStmt->fetchColumn();
            if (!$catId) continue;
            foreach ($langs as $lang => $fields) {
                try {
                    $catTrStmt->execute([
                        ':cid'   => $catId,
                        ':lang'  => $lang,
                        ':name'  => $fields['name'],
                        ':slug'  => $fields['slug'],
                        ':sdesc' => $fields['short_description'] ?? null,
                    ]);
                    $catTrInserted++;
                } catch (Throwable $e) {
                    // unique key ihlali: zaten var, geç
                }
            }
        }
        $results[] = ['label' => 'Kategori çevirileri (DE/IT/FR)', 'status' => 'ok', 'msg' => "$catTrInserted satır işlendi (varsa dokunulmadı)"];
    }

    // 6. Sayfa çevirileri — About Us ve Contact için DE/IT/FR
    $pageTranslationSeeds = [
        'about-us' => [
            'de' => ['title' => 'Über uns',   'slug' => 'ueber-uns', 'banner_title' => 'Über uns'],
            'it' => ['title' => 'Chi siamo',  'slug' => 'chi-siamo', 'banner_title' => 'Chi siamo'],
            'fr' => ['title' => 'À propos',   'slug' => 'a-propos',  'banner_title' => 'À propos'],
        ],
        'contact' => [
            'de' => ['title' => 'Kontakt',    'slug' => 'kontakt',  'banner_title' => 'Kontakt'],
            'it' => ['title' => 'Contatti',   'slug' => 'contatti', 'banner_title' => 'Contatti'],
            'fr' => ['title' => 'Contact',    'slug' => 'contact',  'banner_title' => 'Contact'],
        ],
    ];

    if (mg_table_exists($pdo, $dbName, 'page_translations') && mg_table_exists($pdo, $dbName, 'pages')) {
        $pageTrStmt = $pdo->prepare(
            'INSERT INTO page_translations (page_id, language, title, slug, banner_title)
             VALUES (:pid, :lang, :title, :slug, :btitle)
             ON DUPLICATE KEY UPDATE page_id = page_id'
        );
        $pageTrInserted = 0;
        foreach ($pageTranslationSeeds as $enSlug => $langs) {
            $pageIdStmt = $pdo->prepare('SELECT id FROM pages WHERE slug = ? LIMIT 1');
            $pageIdStmt->execute([$enSlug]);
            $pageId = $pageIdStmt->fetchColumn();
            if (!$pageId) continue;
            foreach ($langs as $lang => $fields) {
                try {
                    $pageTrStmt->execute([
                        ':pid'    => $pageId,
                        ':lang'   => $lang,
                        ':title'  => $fields['title'],
                        ':slug'   => $fields['slug'],
                        ':btitle' => $fields['banner_title'] ?? $fields['title'],
                    ]);
                    $pageTrInserted++;
                } catch (Throwable $e) {
                    // unique key ihlali: zaten var, geç
                }
            }
        }
        $results[] = ['label' => 'Sayfa çevirileri About Us & Contact (DE/IT/FR)', 'status' => 'ok', 'msg' => "$pageTrInserted satır işlendi (varsa dokunulmadı)"];
    }

    // 7. Varsayılan admin kullanıcısı
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
