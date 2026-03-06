-- Flexion Website - MySQL Schema
-- Charset: utf8mb4

USE `flexionindustria_main`;

-- Admin users
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan şifre: admin123 (ilk girişte otomatik olarak bcrypt'e çevrilir)
INSERT INTO `users` (`username`, `password`, `is_active`)
VALUES
('admin', 'admin123', 1);

-- Site wide settings (key/value)
CREATE TABLE `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(150) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Main navigation menu
CREATE TABLE `menu_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` INT UNSIGNED NULL,
  `title` VARCHAR(255) NOT NULL,
  `url` VARCHAR(255) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories / sectors
CREATE TABLE `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` INT UNSIGNED NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `name` VARCHAR(255) NOT NULL,
  `short_description` TEXT NULL,
  `description` TEXT NULL,
  `image` VARCHAR(255) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products
CREATE TABLE `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(100) NULL,
  `short_description` TEXT NULL,
  `description` MEDIUMTEXT NULL,
  `main_image` VARCHAR(255) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Additional product images
CREATE TABLE `product_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `image` VARCHAR(255) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Technical specification tables (for grouping)
CREATE TABLE `product_spec_tables` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Technical specification rows
CREATE TABLE `product_specs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_id` INT UNSIGNED NOT NULL,
  `label` VARCHAR(255) NOT NULL,
  `value` VARCHAR(255) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_table_id` (`table_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Regulations / certifications per product
CREATE TABLE `product_regulations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `icon` VARCHAR(255) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Homepage sections (hero, sectors, featured products, news, etc.)
CREATE TABLE `home_sections` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `section_type` VARCHAR(100) NOT NULL,
  `title` VARCHAR(255) NULL,
  `content_json` MEDIUMTEXT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- News / insights
CREATE TABLE `news` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `title` VARCHAR(255) NOT NULL,
  `summary` TEXT NULL,
  `content` MEDIUMTEXT NULL,
  `image` VARCHAR(255) NULL,
  `published_at` DATETIME NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Useful / downloadable documents
CREATE TABLE `useful_documents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `category_id` INT UNSIGNED NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED / ÖRNEK VERİLER
-- ============================================================

-- Site ayarları
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_title',       'Flexion Industrial'),
('topbar_text',      'Industrial Cable & Hose Solutions Since 2010'),
('contact_phone',    '+90 (212) 000 00 00'),
('contact_email',    'info@flexionindustrial.com'),
('company_name',     'Flexion Industrial A.Ş.'),
('company_address',  'Ostim OSB, Ankara / Türkiye'),
('social_linkedin',  ''),
('social_youtube',   ''),
('footer_text',      '© 2025 Flexion Industrial A.Ş. Tüm hakları saklıdır.'),
('newsletter_text',  'Yeni ürün ve projelerden haberdar olmak için bültenimize abone olun.'),
('news_banner_image',''),
('news_banner_title','Haberler & Insights');

-- Ana menü
INSERT INTO `menu_items` (`title`, `url`, `sort_order`, `is_active`) VALUES
('Ana Sayfa',    'index.php',   1, 1),
('Sektörler',    'sectors.php', 2, 1),
('Ürünler',      'sectors.php', 3, 1),
('Haberler',     'news.php',    4, 1),
('İletişim',     '#iletisim',   5, 1);

-- Örnek kategoriler
INSERT INTO `categories` (`name`, `slug`, `short_description`, `sort_order`, `is_active`) VALUES
('Enerji Kabloları',      'enerji-kabolari',     'Yüksek gerilim ve düşük gerilim enerji kablo çözümleri.',   1, 1),
('Kontrol Kabloları',     'kontrol-kabolari',    'Endüstriyel otomasyon ve kontrol sistemleri için kablolar.', 2, 1),
('Data & İletişim',       'data-iletisim',       'Yapısal kablolama ve iletişim altyapısı çözümleri.',        3, 1),
('Yangına Dayanıklı',     'yangina-dayanikli',   'BS 6387 ve IEC 60331 standartlarına uygun yangın kabloları.',4, 1),
('Denizcilik & Offshore', 'denizcilik-offshore', 'DNV, Lloyd''s onaylı marine ve offshore kablo serileri.',   5, 1),
('Özel Uygulama',         'ozel-uygulama',       'Müşteri talebine özel tasarım ve üretim.',                  6, 1);

-- Örnek ürünler
INSERT INTO `products` (`category_id`, `name`, `slug`, `code`, `short_description`, `sort_order`, `is_active`) VALUES
(1, 'FLX-HV 35 kV XLPE',   'flx-hv-35kv-xlpe',   'FLX-HV-35',  'Orta gerilim dağıtım şebekeleri için XLPE izoleli enerji kablosu.',          1, 1),
(1, 'FLX-LV 1 kV NYY',     'flx-lv-1kv-nyy',     'FLX-LV-1',   '0,6/1 kV PVC izoleli, PVC kılıflı güç kablosu. Bina ve altyapı uygulamaları.', 2, 1),
(2, 'FLX-CTRL 0,6/1 kV',   'flx-ctrl-06-1kv',    'FLX-CTRL',   'Çok damarlı kontrol kablosu; esnek ve rijit seçenekli.',                       1, 1),
(3, 'FLX-CAT6A U/FTP',     'flx-cat6a-uftp',     'FLX-CAT6A',  '10 Gbps''e kadar hız; bant genişliği 500 MHz.',                               1, 1),
(4, 'FLX-FR 3 Saat',       'flx-fr-3saat',        'FLX-FR-3H',  'BS 6387 CWZ sertifikalı; 950 °C''de 3 saat yangın dayanımı.',                 1, 1),
(5, 'FLX-MRN NEK606',      'flx-mrn-nek606',      'FLX-MRN',    'NEK 606 standardına uygun offshore güç kablosu.',                             1, 1);

-- Örnek ana sayfa bölümleri
INSERT INTO `home_sections` (`section_type`, `title`, `content_json`, `sort_order`, `is_active`) VALUES
(
  'hero',
  'Endüstriyel Kablo Çözümleri',
  '{"eyebrow":"Flexion Industrial Cable","subtitle":"Yüksek performanslı kablo ve hortum çözümleri sunan Flexion, zorlu endüstriyel ortamlar için güvenilir altyapı sağlar.","button_text":"Ürünleri İncele","button_url":"sectors.php","image":""}',
  1, 1
),
(
  'sectors',
  'Uygulama Sektörleri',
  '{"subtitle":"Enerji üretiminden denizciliğe kadar geniş bir uygulama yelpazesi."}',
  2, 1
),
(
  'text_image',
  'Flexion Hakkında',
  '{"text":"Flexion Industrial, 2010 yılından bu yana enerji, altyapı ve endüstriyel sektörlere yönelik yüksek kaliteli kablo ve bağlantı çözümleri sunmaktadır. Tüm ürünlerimiz uluslararası standartlara (IEC, BS, DIN) uygun üretilmektedir.","button_text":"Daha Fazla","button_url":"#hakkinda","image":""}',
  3, 1
),
(
  'news',
  'Güncel Haberler',
  '{"subtitle":"Flexion ürünleri ve sektördeki gelişmeler."}',
  4, 1
);

-- Kurumsal sayfalar
CREATE TABLE `pages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(200) NOT NULL UNIQUE,
  `title` VARCHAR(255) NOT NULL,
  `content` LONGTEXT NULL,
  `meta_description` VARCHAR(300) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `sort_order`, `is_active`) VALUES
('hakkimizda', 'Hakkımızda',
 '<h2>Flexion Industrial Hakkında</h2><p>Flexion Industrial, 2010 yılından bu yana enerji, altyapı ve endüstriyel sektörlere yönelik yüksek kaliteli kablo ve bağlantı çözümleri sunmaktadır. Tüm ürünlerimiz uluslararası standartlara (IEC, BS, DIN) uygun üretilmektedir.</p><p>Müşterilerimize en yüksek kalitede ürün ve hizmet sunmayı hedefleyen firmamız, sürekli gelişen teknolojileri takip ederek sektörde öncü konumunu korumaktadır.</p>',
 'Flexion Industrial hakkında - Misyon, vizyon ve kurumsal kimlik.',
 1, 1),
('iletisim', 'İletişim',
 '<h2>Bize Ulaşın</h2><p>Ürünlerimiz veya çözümlerimiz hakkında bilgi almak için aşağıdaki iletişim kanallarından bize ulaşabilirsiniz.</p>',
 'Flexion Industrial iletişim bilgileri ve bize ulaşın.',
 2, 1);

-- Ürün dokümanları
CREATE TABLE `product_documents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_documents_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Örnek haber
INSERT INTO `news` (`title`, `slug`, `summary`, `published_at`, `is_active`) VALUES
('Flexion IEC 60502-2 Sertifikasını Aldı', 'flexion-iec-60502-2-sertifikasi',
 'Orta gerilim kablo serimiz IEC 60502-2 uluslararası standardı sertifikasyonunu başarıyla tamamladı.',
 '2025-03-01', 1),
('Yeni FLX-FR Yangına Dayanıklı Kablo Serisi', 'yeni-flx-fr-yangina-dayanikli',
 'BS 6387 CWZ sertifikalı yeni yangına dayanıklı kablo serimiz artık sipariş alınmaya başlandı.',
 '2025-01-15', 1);

