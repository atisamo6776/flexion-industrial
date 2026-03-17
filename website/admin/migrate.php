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
        ['footer_social_title',      'en', 'Social Media'],
        ['footer_social_title',      'de', 'Soziale Medien'],
        ['footer_social_title',      'it', 'Social media'],
        ['footer_social_title',      'fr', 'Réseaux sociaux'],
        ['footer_col_company',       'en', 'Company'],
        ['footer_col_company',       'de', 'Unternehmen'],
        ['footer_col_company',       'it', 'Azienda'],
        ['footer_col_company',       'fr', 'Entreprise'],
        ['footer_col_products',      'en', 'Products'],
        ['footer_col_products',      'de', 'Produkte'],
        ['footer_col_products',      'it', 'Prodotti'],
        ['footer_col_products',      'fr', 'Produits'],
        ['footer_col_categories',    'en', 'Categories'],
        ['footer_col_categories',    'de', 'Kategorien'],
        ['footer_col_categories',    'it', 'Categorie'],
        ['footer_col_categories',    'fr', 'Catégories'],
        ['footer_col_information',   'en', 'Information'],
        ['footer_col_information',   'de', 'Informationen'],
        ['footer_col_information',   'it', 'Informazioni'],
        ['footer_col_information',   'fr', 'Informations'],
        ['footer_link_all_products', 'en', 'All Products'],
        ['footer_link_all_products', 'de', 'Alle Produkte'],
        ['footer_link_all_products', 'it', 'Tutti i prodotti'],
        ['footer_link_all_products', 'fr', 'Tous les produits'],
        ['footer_link_news',         'en', 'News'],
        ['footer_link_news',         'de', 'Neuigkeiten'],
        ['footer_link_news',         'it', 'Notizie'],
        ['footer_link_news',         'fr', 'Actualités'],
        ['footer_link_privacy',      'en', 'Privacy Policy'],
        ['footer_link_privacy',      'de', 'Datenschutzerklärung'],
        ['footer_link_privacy',      'it', 'Informativa sulla privacy'],
        ['footer_link_privacy',      'fr', 'Politique de confidentialité'],
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

    // 5. footer_links URL düzeltmeleri (eski sectors.php linkleri)
    if (mg_table_exists($pdo, $dbName, 'footer_links')) {
        try {
            $pdo->exec("UPDATE footer_links SET url = '/categories' WHERE url IN ('sectors.php', 'sectors', '/sectors', '/sectors.php')");
            $results[] = ['label' => 'footer_links: sectors → /categories', 'status' => 'ok', 'msg' => 'URL düzeltmesi uygulandı (varsa)'];
        } catch (Throwable $e) {
            $results[] = ['label' => 'footer_links: sectors → /categories', 'status' => 'fail', 'msg' => $e->getMessage()];
        }
    }

    // 5b. footer_links seed (kurumsal görünüm + dil uyumlu slug)
    if (mg_table_exists($pdo, $dbName, 'footer_links')) {
        try {
            $seedLinks = [
                // Company
                ['company',     '', 'About Us',          'page.php?slug=about-us',  1, 1],
                ['company',     '', 'Contact',           'page.php?slug=contact',   2, 1],
                // Products
                ['products',    '', 'All Products',      '/categories',             1, 1],
                // Information
                ['information', '', 'News',              'news',                    1, 1],
                ['information', '', 'Privacy Policy',    'page.php?slug=privacy-policy', 2, 1],
            ];

            $ins = $pdo->prepare(
                'INSERT INTO footer_links (column_key, column_label, title, url, sort_order, is_active)
                 SELECT :ck, :cl, :t, :u, :so, :ia
                 WHERE NOT EXISTS (
                    SELECT 1 FROM footer_links WHERE column_key = :ck2 AND url = :u2 LIMIT 1
                 )'
            );
            $seeded = 0;
            foreach ($seedLinks as [$ck, $cl, $t, $u, $so, $ia]) {
                $ins->execute([
                    ':ck'  => $ck,  ':cl'  => $cl,  ':t'  => $t,  ':u'  => $u,  ':so' => $so, ':ia' => $ia,
                    ':ck2' => $ck,  ':u2'  => $u,
                ]);
                $seeded += (int) $ins->rowCount();
            }
            $results[] = ['label' => 'footer_links: seed', 'status' => 'ok', 'msg' => $seeded . ' yeni link eklendi (varsa dokunulmadı)'];

            // footer_link_translations: başlıkları dillere göre çevir (varsa)
            if (mg_table_exists($pdo, $dbName, 'footer_link_translations')) {
                $titleTr = [
                    'About Us'       => ['de' => 'Über uns',        'it' => 'Chi siamo',      'fr' => 'À propos'],
                    'Contact'        => ['de' => 'Kontakt',         'it' => 'Contatti',       'fr' => 'Contact'],
                    'All Products'   => ['de' => 'Alle Produkte',   'it' => 'Tutti i prodotti','fr' => 'Tous les produits'],
                    'News'           => ['de' => 'Neuigkeiten',     'it' => 'Notizie',        'fr' => 'Actualités'],
                    'Privacy Policy' => ['de' => 'Datenschutzerklärung','it' => 'Informativa sulla privacy','fr' => 'Politique de confidentialité'],
                ];
                $getId = $pdo->prepare('SELECT id, title FROM footer_links WHERE column_key = ? AND url = ? LIMIT 1');
                $insTr = $pdo->prepare(
                    'INSERT INTO footer_link_translations (footer_link_id, language, title)
                     VALUES (:fid, :lang, :title)
                     ON DUPLICATE KEY UPDATE title = VALUES(title)'
                );
                $trCount = 0;
                foreach ($seedLinks as [$ck, $_cl, $t, $u]) {
                    $getId->execute([$ck, $u]);
                    $row = $getId->fetch();
                    if (!$row) continue;
                    $fid = (int) $row['id'];
                    foreach (['de','it','fr'] as $l) {
                        if (!isset($titleTr[$t][$l])) continue;
                        $insTr->execute([':fid' => $fid, ':lang' => $l, ':title' => $titleTr[$t][$l]]);
                        $trCount++;
                    }
                }
                $results[] = ['label' => 'footer_link_translations: seed', 'status' => 'ok', 'msg' => $trCount . ' çeviri işlendi'];
            }
        } catch (Throwable $e) {
            $results[] = ['label' => 'footer_links: seed', 'status' => 'fail', 'msg' => $e->getMessage()];
        }
    }

    // 6. Kategori çevirileri — tüm diller için, slug ile lookup
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

    // 7. Sayfa çevirileri — About Us ve Contact için DE/IT/FR
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

    // 8. Legal sayfalar + çevirileri (Privacy Policy, Cookie Policy, Terms & Conditions)
    if (mg_table_exists($pdo, $dbName, 'pages') && mg_table_exists($pdo, $dbName, 'page_translations')) {

        $legalPages = [
            [
                'slug'         => 'privacy-policy',
                'title'        => 'Privacy Policy',
                'banner_title' => 'Privacy Policy',
                'content'      => '<h2>Privacy Policy</h2>
<p>Flexion Industrial AG ("Flexion", "we", "us", or "our") is committed to protecting your personal data. This Privacy Policy explains how we collect, use, and protect information about you when you visit <strong>flexion-industrial.ch</strong>.</p>
<h3>1. Data We Collect</h3>
<ul>
<li><strong>Contact requests:</strong> Name, email address, phone number, company name, country, and message content submitted via our contact or product enquiry forms.</li>
<li><strong>Usage data:</strong> IP address, browser type, pages visited, and session duration collected through server logs and analytics cookies.</li>
</ul>
<h3>2. Purpose of Processing</h3>
<p>We process your personal data solely to respond to your enquiries, to improve our website, and to comply with applicable Swiss and EU data-protection regulations (nDSG / GDPR).</p>
<h3>3. Data Retention</h3>
<p>Contact enquiry data is retained for a maximum of 24 months. Log data is automatically deleted after 90 days.</p>
<h3>4. Sharing of Data</h3>
<p>We do not sell or rent your personal data to third parties. We may share data with trusted service providers (hosting, analytics) under strict confidentiality agreements.</p>
<h3>5. Your Rights</h3>
<p>You have the right to access, correct, or request deletion of your personal data at any time by contacting us at <a href="mailto:info@flexionindustrial.com">info@flexionindustrial.com</a>.</p>
<h3>6. Contact</h3>
<p>Flexion Industrial AG<br>Switzerland<br>Email: info@flexionindustrial.com</p>',
                'translations' => [
                    'de' => ['slug' => 'datenschutzerklaerung', 'title' => 'Datenschutzerklärung', 'banner_title' => 'Datenschutzerklärung', 'content' => '<h2>Datenschutzerklärung</h2>
<p>Flexion Industrial AG ("Flexion", "wir") verpflichtet sich zum Schutz Ihrer personenbezogenen Daten. Diese Datenschutzerklärung erläutert, wie wir Informationen erheben, verwenden und schützen, wenn Sie <strong>flexion-industrial.ch</strong> besuchen.</p>
<h3>1. Erhobene Daten</h3>
<ul>
<li><strong>Kontaktanfragen:</strong> Name, E-Mail, Telefonnummer, Unternehmen, Land und Nachrichteninhalt aus unseren Kontakt- oder Produktanfrage-Formularen.</li>
<li><strong>Nutzungsdaten:</strong> IP-Adresse, Browsertyp, besuchte Seiten und Sitzungsdauer über Server-Logs und Analyse-Cookies.</li>
</ul>
<h3>2. Zweck der Verarbeitung</h3>
<p>Wir verarbeiten Ihre Daten ausschließlich zur Beantwortung Ihrer Anfragen, zur Verbesserung unserer Website und zur Einhaltung des Schweizer Datenschutzgesetzes (nDSG) und der DSGVO.</p>
<h3>3. Datenspeicherung</h3>
<p>Kontaktanfragen werden maximal 24 Monate gespeichert. Log-Daten werden nach 90 Tagen automatisch gelöscht.</p>
<h3>4. Datenweitergabe</h3>
<p>Wir verkaufen oder vermieten Ihre Daten nicht. Daten werden nur unter strikten Vertraulichkeitsvereinbarungen an Dienstleister (Hosting, Analyse) weitergegeben.</p>
<h3>5. Ihre Rechte</h3>
<p>Sie haben das Recht auf Auskunft, Berichtigung oder Löschung Ihrer Daten. Kontaktieren Sie uns unter <a href="mailto:info@flexionindustrial.com">info@flexionindustrial.com</a>.</p>'],
                    'it' => ['slug' => 'informativa-sulla-privacy', 'title' => 'Informativa sulla privacy', 'banner_title' => 'Informativa sulla privacy', 'content' => '<h2>Informativa sulla privacy</h2>
<p>Flexion Industrial AG ("Flexion", "noi") si impegna a proteggere i Vostri dati personali. La presente informativa spiega come raccogliamo, utilizziamo e proteggiamo le informazioni su di voi quando visitate <strong>flexion-industrial.ch</strong>.</p>
<h3>1. Dati raccolti</h3>
<ul>
<li><strong>Richieste di contatto:</strong> nome, e-mail, telefono, azienda, paese e testo del messaggio inviati tramite i nostri moduli.</li>
<li><strong>Dati di utilizzo:</strong> indirizzo IP, tipo di browser, pagine visitate e durata della sessione tramite log del server e cookie analitici.</li>
</ul>
<h3>2. Finalità del trattamento</h3>
<p>Trattiamo i Vostri dati esclusivamente per rispondere alle Vostre richieste, migliorare il nostro sito e rispettare la normativa svizzera (nLPD) e il GDPR.</p>
<h3>3. Conservazione</h3>
<p>Le richieste di contatto vengono conservate per un massimo di 24 mesi. I dati di log vengono eliminati automaticamente dopo 90 giorni.</p>
<h3>4. Condivisione dei dati</h3>
<p>Non vendiamo né affittiamo i Vostri dati. I dati vengono condivisi con fornitori di servizi (hosting, analisi) solo con accordi di riservatezza rigorosi.</p>
<h3>5. I Vostri diritti</h3>
<p>Avete il diritto di accedere, rettificare o richiedere la cancellazione dei Vostri dati contattandoci all\'indirizzo <a href="mailto:info@flexionindustrial.com">info@flexionindustrial.com</a>.</p>'],
                    'fr' => ['slug' => 'politique-de-confidentialite', 'title' => 'Politique de confidentialité', 'banner_title' => 'Politique de confidentialité', 'content' => '<h2>Politique de confidentialité</h2>
<p>Flexion Industrial AG ("Flexion", "nous") s\'engage à protéger vos données personnelles. La présente politique explique comment nous collectons, utilisons et protégeons vos informations lorsque vous visitez <strong>flexion-industrial.ch</strong>.</p>
<h3>1. Données collectées</h3>
<ul>
<li><strong>Demandes de contact :</strong> nom, e-mail, téléphone, société, pays et contenu du message soumis via nos formulaires.</li>
<li><strong>Données d\'utilisation :</strong> adresse IP, type de navigateur, pages visitées et durée de session via les journaux serveur et les cookies analytiques.</li>
</ul>
<h3>2. Finalité du traitement</h3>
<p>Nous traitons vos données uniquement pour répondre à vos demandes, améliorer notre site et respecter la loi suisse (nLPD) et le RGPD.</p>
<h3>3. Conservation</h3>
<p>Les demandes de contact sont conservées 24 mois maximum. Les données de journal sont supprimées automatiquement après 90 jours.</p>
<h3>4. Partage des données</h3>
<p>Nous ne vendons ni ne louons vos données. Elles ne sont partagées qu\'avec des prestataires (hébergement, analyse) sous strict accord de confidentialité.</p>
<h3>5. Vos droits</h3>
<p>Vous pouvez accéder, rectifier ou demander la suppression de vos données en nous contactant à <a href="mailto:info@flexionindustrial.com">info@flexionindustrial.com</a>.</p>'],
                ],
            ],
            [
                'slug'         => 'cookie-policy',
                'title'        => 'Cookie Policy',
                'banner_title' => 'Cookie Policy',
                'content'      => '<h2>Cookie Policy</h2>
<p>This Cookie Policy explains how Flexion Industrial AG uses cookies and similar technologies on <strong>flexion-industrial.ch</strong>.</p>
<h3>1. What Are Cookies?</h3>
<p>Cookies are small text files stored on your device when you visit a website. They help the site function properly and provide usage statistics.</p>
<h3>2. Types of Cookies We Use</h3>
<ul>
<li><strong>Essential cookies:</strong> Required for the website to operate (session management, security).</li>
<li><strong>Analytics cookies:</strong> Help us understand how visitors interact with the site (page views, traffic sources). These are only activated after your consent.</li>
</ul>
<h3>3. Managing Cookies</h3>
<p>You can accept or decline non-essential cookies using the cookie banner shown on your first visit. You may also manage cookie preferences through your browser settings at any time.</p>
<h3>4. Third-Party Cookies</h3>
<p>We may use trusted third-party services (e.g. Google Analytics) that set their own cookies. These are subject to the third party\'s privacy policy.</p>
<h3>5. Contact</h3>
<p>For questions about our use of cookies, contact us at <a href="mailto:info@flexionindustrial.com">info@flexionindustrial.com</a>.</p>',
                'translations' => [
                    'de' => ['slug' => 'cookie-richtlinie', 'title' => 'Cookie-Richtlinie', 'banner_title' => 'Cookie-Richtlinie', 'content' => '<h2>Cookie-Richtlinie</h2>
<p>Diese Cookie-Richtlinie erläutert, wie Flexion Industrial AG Cookies und ähnliche Technologien auf <strong>flexion-industrial.ch</strong> einsetzt.</p>
<h3>1. Was sind Cookies?</h3>
<p>Cookies sind kleine Textdateien, die beim Besuch einer Website auf Ihrem Gerät gespeichert werden. Sie helfen, die Website korrekt zu betreiben und Nutzungsstatistiken zu erstellen.</p>
<h3>2. Von uns verwendete Cookie-Typen</h3>
<ul>
<li><strong>Notwendige Cookies:</strong> Erforderlich für den Betrieb der Website (Sitzungsverwaltung, Sicherheit).</li>
<li><strong>Analyse-Cookies:</strong> Helfen uns zu verstehen, wie Besucher die Website nutzen. Diese werden nur nach Ihrer Zustimmung aktiviert.</li>
</ul>
<h3>3. Cookie-Verwaltung</h3>
<p>Sie können nicht notwendige Cookies über das Cookie-Banner akzeptieren oder ablehnen. Außerdem können Sie Cookie-Einstellungen jederzeit in Ihrem Browser verwalten.</p>
<h3>4. Drittanbieter-Cookies</h3>
<p>Wir können vertrauenswürdige Drittanbieterdienste (z. B. Google Analytics) verwenden, die eigene Cookies setzen.</p>
<h3>5. Kontakt</h3>
<p>Bei Fragen zu Cookies kontaktieren Sie uns unter <a href="mailto:info@flexionindustrial.com">info@flexionindustrial.com</a>.</p>'],
                    'it' => ['slug' => 'cookie-policy', 'title' => 'Cookie Policy', 'banner_title' => 'Cookie Policy', 'content' => '<h2>Cookie Policy</h2>
<p>La presente Cookie Policy spiega come Flexion Industrial AG utilizza cookie e tecnologie simili su <strong>flexion-industrial.ch</strong>.</p>
<h3>1. Cosa sono i cookie?</h3>
<p>I cookie sono piccoli file di testo memorizzati sul Vostro dispositivo quando visitate un sito web. Aiutano il sito a funzionare correttamente e forniscono statistiche di utilizzo.</p>
<h3>2. Tipi di cookie utilizzati</h3>
<ul>
<li><strong>Cookie essenziali:</strong> necessari per il funzionamento del sito (gestione sessioni, sicurezza).</li>
<li><strong>Cookie analitici:</strong> ci aiutano a capire come i visitatori interagiscono con il sito. Vengono attivati solo dopo il Vostro consenso.</li>
</ul>
<h3>3. Gestione dei cookie</h3>
<p>Potete accettare o rifiutare i cookie non essenziali tramite il banner cookie mostrato alla prima visita, oppure gestire le preferenze dal browser.</p>
<h3>4. Cookie di terze parti</h3>
<p>Potremmo utilizzare servizi di terze parti (es. Google Analytics) che impostano i propri cookie.</p>
<h3>5. Contatti</h3>
<p>Per domande sull\'uso dei cookie: <a href="mailto:info@flexionindustrial.com">info@flexionindustrial.com</a>.</p>'],
                    'fr' => ['slug' => 'politique-des-cookies', 'title' => 'Politique des cookies', 'banner_title' => 'Politique des cookies', 'content' => '<h2>Politique des cookies</h2>
<p>La présente politique explique comment Flexion Industrial AG utilise les cookies et technologies similaires sur <strong>flexion-industrial.ch</strong>.</p>
<h3>1. Qu\'est-ce qu\'un cookie ?</h3>
<p>Les cookies sont de petits fichiers texte enregistrés sur votre appareil lors de la visite d\'un site web. Ils permettent le bon fonctionnement du site et fournissent des statistiques d\'utilisation.</p>
<h3>2. Types de cookies utilisés</h3>
<ul>
<li><strong>Cookies essentiels :</strong> nécessaires au fonctionnement du site (gestion de session, sécurité).</li>
<li><strong>Cookies analytiques :</strong> nous aident à comprendre comment les visiteurs interagissent avec le site. Ils ne sont activés qu\'après votre consentement.</li>
</ul>
<h3>3. Gestion des cookies</h3>
<p>Vous pouvez accepter ou refuser les cookies non essentiels via la bannière cookie à votre première visite, ou gérer vos préférences dans les paramètres de votre navigateur.</p>
<h3>4. Cookies tiers</h3>
<p>Nous pouvons faire appel à des services tiers (ex. Google Analytics) qui déposent leurs propres cookies.</p>
<h3>5. Contact</h3>
<p>Pour toute question : <a href="mailto:info@flexionindustrial.com">info@flexionindustrial.com</a>.</p>'],
                ],
            ],
            [
                'slug'         => 'terms-and-conditions',
                'title'        => 'Terms & Conditions',
                'banner_title' => 'Terms & Conditions',
                'content'      => '<h2>Terms &amp; Conditions</h2>
<p>These Terms &amp; Conditions govern your use of the Flexion Industrial AG website at <strong>flexion-industrial.ch</strong>. By accessing the site, you agree to these terms.</p>
<h3>1. Use of the Website</h3>
<p>The content published on this website is for general informational purposes only. Flexion Industrial AG reserves the right to modify or discontinue any part of the site without notice.</p>
<h3>2. Intellectual Property</h3>
<p>All content — including texts, images, logos, and graphics — is the property of Flexion Industrial AG or its licensors and may not be reproduced without prior written permission.</p>
<h3>3. Product Information</h3>
<p>Technical specifications, dimensions, and availability of products are subject to change without notice. Contact us for the most up-to-date information before placing an order.</p>
<h3>4. Limitation of Liability</h3>
<p>Flexion Industrial AG shall not be liable for any direct, indirect, incidental, or consequential damages arising from the use of this website or the information it contains.</p>
<h3>5. Governing Law</h3>
<p>These Terms are governed by Swiss law. Any disputes shall be subject to the exclusive jurisdiction of the courts of Switzerland.</p>
<h3>6. Contact</h3>
<p>Flexion Industrial AG<br>Switzerland<br>Email: <a href="mailto:info@flexionindustrial.com">info@flexionindustrial.com</a></p>',
                'translations' => [
                    'de' => ['slug' => 'allgemeine-geschaeftsbedingungen', 'title' => 'Allgemeine Geschäftsbedingungen', 'banner_title' => 'AGB', 'content' => '<h2>Allgemeine Geschäftsbedingungen</h2>
<p>Diese AGB regeln die Nutzung der Website von Flexion Industrial AG unter <strong>flexion-industrial.ch</strong>. Mit dem Zugriff auf die Website stimmen Sie diesen Bedingungen zu.</p>
<h3>1. Nutzung der Website</h3>
<p>Die auf dieser Website veröffentlichten Inhalte dienen ausschließlich allgemeinen Informationszwecken. Flexion Industrial AG behält sich vor, Teile der Website ohne vorherige Ankündigung zu ändern oder einzustellen.</p>
<h3>2. Geistiges Eigentum</h3>
<p>Alle Inhalte – Texte, Bilder, Logos und Grafiken – sind Eigentum von Flexion Industrial AG oder ihrer Lizenzgeber und dürfen ohne vorherige schriftliche Genehmigung nicht reproduziert werden.</p>
<h3>3. Produktinformationen</h3>
<p>Technische Spezifikationen und Verfügbarkeit können sich ohne Vorankündigung ändern. Kontaktieren Sie uns vor einer Bestellung für aktuelle Informationen.</p>
<h3>4. Haftungsbeschränkung</h3>
<p>Flexion Industrial AG haftet nicht für direkte, indirekte oder Folgeschäden, die aus der Nutzung dieser Website entstehen.</p>
<h3>5. Anwendbares Recht</h3>
<p>Diese AGB unterliegen Schweizer Recht. Streitigkeiten unterliegen der ausschließlichen Zuständigkeit der Schweizer Gerichte.</p>
<h3>6. Kontakt</h3>
<p>Email: <a href="mailto:info@flexionindustrial.com">info@flexionindustrial.com</a></p>'],
                    'it' => ['slug' => 'termini-e-condizioni', 'title' => 'Termini e condizioni', 'banner_title' => 'Termini e condizioni', 'content' => '<h2>Termini e condizioni</h2>
<p>I presenti Termini e condizioni disciplinano l\'utilizzo del sito web di Flexion Industrial AG all\'indirizzo <strong>flexion-industrial.ch</strong>. Accedendo al sito, accettate questi termini.</p>
<h3>1. Utilizzo del sito</h3>
<p>I contenuti pubblicati hanno scopo puramente informativo. Flexion Industrial AG si riserva il diritto di modificare o interrompere qualsiasi parte del sito senza preavviso.</p>
<h3>2. Proprietà intellettuale</h3>
<p>Tutti i contenuti — testi, immagini, loghi e grafica — sono di proprietà di Flexion Industrial AG o dei suoi licenziatari e non possono essere riprodotti senza autorizzazione scritta.</p>
<h3>3. Informazioni sui prodotti</h3>
<p>Le specifiche tecniche e la disponibilità dei prodotti sono soggette a modifiche senza preavviso. Contattateci prima di effettuare un ordine.</p>
<h3>4. Limitazione di responsabilità</h3>
<p>Flexion Industrial AG non è responsabile per danni diretti, indiretti o consequenziali derivanti dall\'utilizzo di questo sito.</p>
<h3>5. Legge applicabile</h3>
<p>I presenti termini sono disciplinati dalla legge svizzera. Eventuali controversie sono di competenza esclusiva dei tribunali svizzeri.</p>
<h3>6. Contatti</h3>
<p>Email: <a href="mailto:info@flexionindustrial.com">info@flexionindustrial.com</a></p>'],
                    'fr' => ['slug' => 'conditions-generales', 'title' => 'Conditions générales', 'banner_title' => 'CGU', 'content' => '<h2>Conditions générales d\'utilisation</h2>
<p>Les présentes Conditions générales régissent l\'utilisation du site web de Flexion Industrial AG à l\'adresse <strong>flexion-industrial.ch</strong>. En accédant au site, vous acceptez ces conditions.</p>
<h3>1. Utilisation du site</h3>
<p>Les contenus publiés sur ce site sont fournis à titre purement informatif. Flexion Industrial AG se réserve le droit de modifier ou d\'interrompre toute partie du site sans préavis.</p>
<h3>2. Propriété intellectuelle</h3>
<p>Tous les contenus — textes, images, logos et graphismes — sont la propriété de Flexion Industrial AG ou de ses concédants et ne peuvent être reproduits sans autorisation écrite préalable.</p>
<h3>3. Informations sur les produits</h3>
<p>Les spécifications techniques et la disponibilité des produits peuvent être modifiées sans préavis. Contactez-nous avant toute commande.</p>
<h3>4. Limitation de responsabilité</h3>
<p>Flexion Industrial AG ne saurait être tenue responsable de tout dommage direct, indirect ou consécutif résultant de l\'utilisation de ce site.</p>
<h3>5. Droit applicable</h3>
<p>Les présentes conditions sont régies par le droit suisse. Tout litige relève de la compétence exclusive des tribunaux suisses.</p>
<h3>6. Contact</h3>
<p>Email : <a href="mailto:info@flexionindustrial.com">info@flexionindustrial.com</a></p>'],
                ],
            ],
        ];

        $insPage = $pdo->prepare(
            'INSERT INTO pages (slug, title, banner_title, content, is_active, sort_order)
             VALUES (:slug, :title, :btitle, :content, 1, 99)
             ON DUPLICATE KEY UPDATE slug = slug'
        );
        $insPageTr = $pdo->prepare(
            'INSERT INTO page_translations (page_id, language, title, slug, banner_title, content)
             VALUES (:pid, :lang, :title, :slug, :btitle, :content)
             ON DUPLICATE KEY UPDATE page_id = page_id'
        );
        $legalInserted = 0;
        foreach ($legalPages as $lp) {
            try {
                $insPage->execute([':slug' => $lp['slug'], ':title' => $lp['title'], ':btitle' => $lp['banner_title'], ':content' => $lp['content']]);
            } catch (Throwable $e) {
                // unique: zaten var
            }
            $pidStmt = $pdo->prepare('SELECT id FROM pages WHERE slug = ? LIMIT 1');
            $pidStmt->execute([$lp['slug']]);
            $pid = $pidStmt->fetchColumn();
            if (!$pid) continue;
            // EN page_translation da ekle (slug + title)
            try {
                $insPageTr->execute([':pid' => $pid, ':lang' => 'en', ':title' => $lp['title'], ':slug' => $lp['slug'], ':btitle' => $lp['banner_title'], ':content' => $lp['content']]);
                $legalInserted++;
            } catch (Throwable $e) {
                // ignore unique
            }
            foreach ($lp['translations'] as $lang => $tr) {
                try {
                    $insPageTr->execute([':pid' => $pid, ':lang' => $lang, ':title' => $tr['title'], ':slug' => $tr['slug'], ':btitle' => $tr['banner_title'], ':content' => $tr['content']]);
                    $legalInserted++;
                } catch (Throwable $e) {
                    // ignore unique
                }
            }
        }
        $results[] = ['label' => 'Legal sayfalar (Privacy/Cookie/Terms) + çeviriler', 'status' => 'ok', 'msg' => "$legalInserted sayfa/çeviri işlendi"];
    }

    // 8b. Footer reset + seed (Company/Products/Information — Categories sütunu dinamik, kod tarafında çekiliyor)
    if (mg_table_exists($pdo, $dbName, 'footer_links')) {
        try {
            // Eski tüm footer_links ve çevirilerini temizle
            if (mg_table_exists($pdo, $dbName, 'footer_link_translations')) {
                $pdo->exec('DELETE FROM footer_link_translations');
            }
            $pdo->exec('DELETE FROM footer_links');

            // Yeni footer link'leri: URL'ler dil bağımsız; page_clean_url() runtime'da çevirir
            $newLinks = [
                // Company
                ['company',     'About Us',          'page.php?slug=about-us',           1],
                ['company',     'Contact',           'page.php?slug=contact',            2],
                ['company',     'News',              '/news',                             3],
                // Products
                ['products',    'All Products',      '/categories',                       1],
                ['products',    'Catalog',           '/assets/uploads/catalog/flexion-catalog.pdf', 2],
                // Information
                ['information', 'Privacy Policy',    'page.php?slug=privacy-policy',     1],
                ['information', 'Cookie Policy',     'page.php?slug=cookie-policy',      2],
                ['information', 'Terms & Conditions','page.php?slug=terms-and-conditions',3],
            ];

            $insFL = $pdo->prepare(
                'INSERT INTO footer_links (column_key, title, url, sort_order, is_active) VALUES (:ck, :t, :u, :so, 1)'
            );
            $insFLT = mg_table_exists($pdo, $dbName, 'footer_link_translations')
                ? $pdo->prepare('INSERT INTO footer_link_translations (footer_link_id, language, title) VALUES (:fid, :lang, :title)')
                : null;

            $linkTitles = [
                'About Us'           => ['de' => 'Über uns',                'it' => 'Chi siamo',              'fr' => 'À propos'],
                'Contact'            => ['de' => 'Kontakt',                 'it' => 'Contatti',               'fr' => 'Contact'],
                'News'               => ['de' => 'Neuigkeiten',             'it' => 'Notizie',                'fr' => 'Actualités'],
                'All Products'       => ['de' => 'Alle Produkte',           'it' => 'Tutti i prodotti',       'fr' => 'Tous les produits'],
                'Catalog'            => ['de' => 'Katalog',                 'it' => 'Catalogo',               'fr' => 'Catalogue'],
                'Privacy Policy'     => ['de' => 'Datenschutzerklärung',    'it' => 'Informativa sulla privacy', 'fr' => 'Politique de confidentialité'],
                'Cookie Policy'      => ['de' => 'Cookie-Richtlinie',       'it' => 'Cookie Policy',          'fr' => 'Politique des cookies'],
                'Terms & Conditions' => ['de' => 'AGB',                     'it' => 'Termini e condizioni',   'fr' => 'Conditions générales'],
            ];

            $flSeeded = 0;
            foreach ($newLinks as [$ck, $t, $u, $so]) {
                $insFL->execute([':ck' => $ck, ':t' => $t, ':u' => $u, ':so' => $so]);
                $fid = (int) $pdo->lastInsertId();
                $flSeeded++;
                if ($insFLT && isset($linkTitles[$t])) {
                    foreach ($linkTitles[$t] as $l => $trTitle) {
                        try {
                            $insFLT->execute([':fid' => $fid, ':lang' => $l, ':title' => $trTitle]);
                        } catch (Throwable $e) { }
                    }
                }
            }
            $results[] = ['label' => 'Footer reset + seed (Company/Products/Information)', 'status' => 'ok', 'msg' => "$flSeeded link eklendi, çeviriler işlendi"];
        } catch (Throwable $e) {
            $results[] = ['label' => 'Footer reset + seed', 'status' => 'fail', 'msg' => $e->getMessage()];
        }
    }

    // 8c. Footer site_translations (kolon başlıkları)
    $footerColKeys = [
        ['footer_col_company',     'en', 'Company'],
        ['footer_col_company',     'de', 'Unternehmen'],
        ['footer_col_company',     'it', 'Azienda'],
        ['footer_col_company',     'fr', 'Entreprise'],
        ['footer_col_products',    'en', 'Products'],
        ['footer_col_products',    'de', 'Produkte'],
        ['footer_col_products',    'it', 'Prodotti'],
        ['footer_col_products',    'fr', 'Produits'],
        ['footer_col_categories',  'en', 'Categories'],
        ['footer_col_categories',  'de', 'Kategorien'],
        ['footer_col_categories',  'it', 'Categorie'],
        ['footer_col_categories',  'fr', 'Catégories'],
        ['footer_col_information', 'en', 'Information'],
        ['footer_col_information', 'de', 'Informationen'],
        ['footer_col_information', 'it', 'Informazioni'],
        ['footer_col_information', 'fr', 'Informations'],
        ['footer_view_all_categories', 'en', 'View all categories'],
        ['footer_view_all_categories', 'de', 'Alle Kategorien anzeigen'],
        ['footer_view_all_categories', 'it', 'Vedi tutte le categorie'],
        ['footer_view_all_categories', 'fr', 'Voir toutes les catégories'],
    ];
    if (mg_table_exists($pdo, $dbName, 'site_translations')) {
        $stmtFCK = $pdo->prepare(
            'INSERT INTO site_translations (`key`, `language`, `value`) VALUES (:k, :l, :v)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        foreach ($footerColKeys as [$k, $l, $v]) {
            try { $stmtFCK->execute([':k' => $k, ':l' => $l, ':v' => $v]); } catch (Throwable $e) { }
        }
        $results[] = ['label' => 'Footer kolon çevirileri (site_translations)', 'status' => 'ok', 'msg' => count($footerColKeys) . ' key işlendi'];
    }

    // 9. Varsayılan admin kullanıcısı
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
