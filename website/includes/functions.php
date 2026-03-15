<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * XSS'e karşı basit kaçış.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Admin tarafından girilen HTML içeriğini tehlikeli etiketlerden arındırır.
 * WYSIWYG çıktıları için: script, iframe, form vb. kaldırılır,
 * normal içerik etiketleri (p, strong, img, a …) korunur.
 */
function sanitize_html(?string $html): string
{
    if ($html === null || $html === '') {
        return '';
    }

    // Kesinlikle kod çalıştırabilecek veya dış kaynak gömebilecek etiketler
    $dangerous = ['script', 'iframe', 'object', 'embed', 'applet', 'base',
                  'form', 'input', 'button', 'select', 'textarea', 'link', 'meta', 'style'];

    foreach ($dangerous as $tag) {
        // Açılış + kapanış çifti
        $html = preg_replace('/<\s*' . $tag . '(\s[^>]*)?>.*?<\s*\/\s*' . $tag . '\s*>/si', '', $html);
        // Self-closing veya kapanışsız etiket
        $html = preg_replace('/<\s*' . $tag . '(\s[^>]*)?\/?>/si', '', $html);
    }

    // Inline olay işleyicileri (onclick, onload …)
    $html = preg_replace('/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $html);

    // javascript: protokolü (href, src vb.)
    $html = preg_replace('/\b(href|src|action)\s*=\s*["\']?\s*javascript:/i', '$1="#" data-removed=', $html);

    return $html;
}

/**
 * Admin login olmuş mu kontrol eder.
 */
function is_admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

/**
 * Admin login gerekliyse, değilse login sayfasına yönlendirir.
 */
function require_admin_login(): void
{
    if (!is_admin_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * CSRF token üretir ve session'a yazar.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * POST isteğinden gelen CSRF token'ı doğrular.
 */
function verify_csrf_token(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    $valid = hash_equals($_SESSION['csrf_token'], $token);
    if ($valid) {
        // Tek kullanımlık yapmak istersen burayı açabilirsin:
        // unset($_SESSION['csrf_token']);
    }
    return $valid;
}

/**
 * Basit redirect helper.
 */
function redirect(string $path): void
{
    if (BASE_URL) {
        header('Location: ' . rtrim(BASE_URL, '/') . '/' . ltrim($path, '/'));
    } else {
        header('Location: ' . $path);
    }
    exit;
}

/**
 * Flash mesajları (başarılı / hata) için basit yardımcılar.
 */
function set_flash(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function get_flash(string $key): ?string
{
    if (!empty($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }

    return null;
}

/**
 * settings tablosundan tek ayar değeri getirir.
 * Tablo yoksa veya bağlantı hatası varsa default döner (500 önlenir).
 */
function get_setting(string $key, ?string $default = null): ?string
{
    static $settings = null;

    if ($settings === null) {
        try {
            $pdo  = db();
            $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
            $settings = [];
            foreach ($stmt as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            error_log('[flexion] get_setting failed: ' . $e->getMessage());
            $settings = [];
        }
    }

    return $settings[$key] ?? $default;
}

/**
 * Ana menüyü getirir. menu_item_translations tablosundan dil bazlı
 * title/url uygular; çeviri yoksa orijinal değeri korur.
 * Tablo yoksa boş dizi döner.
 */
function get_main_menu(): array
{
    $lang = defined('CURRENT_LANG') ? CURRENT_LANG : 'en';
    try {
        $pdo   = db();
        $stmt  = $pdo->query('SELECT * FROM menu_items WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
        $items = $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('[flexion] get_main_menu failed: ' . $e->getMessage());
        return [];
    }

    // Çevirileri çek
    $translations = [];
    try {
        $pdo2 = db();
        $stmt2 = $pdo2->prepare(
            "SELECT menu_item_id, language, title, url
             FROM menu_item_translations
             WHERE language IN (:l, 'en')"
        );
        $stmt2->execute([':l' => $lang]);
        foreach ($stmt2->fetchAll() as $tr) {
            $translations[$tr['menu_item_id']][$tr['language']] = $tr;
        }
    } catch (Throwable $e) {
        // menu_item_translations tablosu henüz yoksa sessizce devam et
    }

    // Çeviriyi uygula
    foreach ($items as &$item) {
        $id = $item['id'];
        $tr = $translations[$id][$lang]
           ?? $translations[$id]['en']
           ?? null;
        if ($tr) {
            if (!empty($tr['title'])) $item['title'] = $tr['title'];
            if (!empty($tr['url']))   $item['url']   = $tr['url'];
        }
        $item['children'] = [];
    }
    unset($item);

    // Hiyerarşi
    $tree = [];
    foreach ($items as $item) {
        $tree[$item['id']] = $item;
    }
    $root = [];
    foreach ($tree as $id => &$item) {
        if ($item['parent_id']) {
            if (isset($tree[$item['parent_id']])) {
                $tree[$item['parent_id']]['children'][] = &$item;
            } else {
                $root[] = &$item;
            }
        } else {
            $root[] = &$item;
        }
    }
    unset($item);

    return $root;
}

/**
 * Tüm aktif kategorileri düz liste olarak getirir.
 */
function get_active_categories(): array
{
    try {
        $pdo  = db();
        $stmt = $pdo->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC');
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('[flexion] get_active_categories failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Kategorileri hiyerarşik ağaç olarak getirir.
 * Üst seviye kategoriler + her birinin 'children' dizisi (alt kategoriler).
 * parent_id kolonu yoksa düz liste döner (fallback).
 */
function get_categories_tree(): array
{
    try {
        $pdo  = db();
        $stmt = $pdo->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC');
        $all  = $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('[flexion] get_categories_tree failed: ' . $e->getMessage());
        return [];
    }

    $parents  = [];
    $children = [];

    foreach ($all as $cat) {
        $cat['children'] = [];
        $pid = isset($cat['parent_id']) ? (int)$cat['parent_id'] : 0;
        if ($pid === 0) {
            $parents[$cat['id']] = $cat;
        } else {
            $children[$pid][] = $cat;
        }
    }

    // parent_id kolonu hiç yoksa (eski şema) düz liste döndür
    if (empty($parents) && !empty($all)) {
        foreach ($all as $cat) {
            $cat['children'] = [];
            $parents[$cat['id']] = $cat;
        }
    }

    foreach ($parents as $id => &$parent) {
        $parent['children'] = $children[$id] ?? [];
    }
    unset($parent);

    return array_values($parents);
}

/**
 * Ana sayfa bölümlerini getirir. home_section_translations tablosundan
 * dil bazlı title/content_json uygular; çeviri yoksa orijinal korunur.
 * Tablo yoksa boş dizi döner.
 */
function get_home_sections(): array
{
    $lang = defined('CURRENT_LANG') ? CURRENT_LANG : 'en';
    try {
        $pdo  = db();
        $stmt = $pdo->query('SELECT * FROM home_sections WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
    } catch (Throwable $e) {
        error_log('[flexion] get_home_sections failed: ' . $e->getMessage());
        return [];
    }

    $rows = $stmt->fetchAll();
    if (empty($rows)) return [];

    // Çevirileri tek sorguda çek
    $translations = [];
    try {
        $ids   = array_column($rows, 'id');
        $in    = implode(',', array_fill(0, count($ids), '?'));
        $pdo2  = db();
        $stmt2 = $pdo2->prepare(
            "SELECT section_id, language, title, content_json
             FROM home_section_translations
             WHERE section_id IN ({$in}) AND language IN (?, 'en')"
        );
        $stmt2->execute(array_merge($ids, [$lang]));
        foreach ($stmt2->fetchAll() as $tr) {
            $translations[$tr['section_id']][$tr['language']] = $tr;
        }
    } catch (Throwable $e) {
        // home_section_translations tablosu henüz yoksa sessizce devam et
    }

    $sections = [];
    foreach ($rows as $row) {
        $id = $row['id'];
        $tr = $translations[$id][$lang]
           ?? $translations[$id]['en']
           ?? null;

        if ($tr) {
            if (!empty($tr['title']))        $row['title']        = $tr['title'];
            if (!empty($tr['content_json'])) $row['content_json'] = $tr['content_json'];
        }

        $row['content'] = [];
        if (!empty($row['content_json'])) {
            $data = json_decode($row['content_json'], true);
            if (is_array($data)) {
                $row['content'] = $data;
            }
        }
        $sections[] = $row;
    }

    return $sections;
}

/**
 * Son haberleri getirir. Tablo yoksa boş dizi döner.
 */
function get_latest_news(int $limit = 3): array
{
    try {
        $pdo  = db();
        $stmt = $pdo->prepare('SELECT * FROM news WHERE is_active = 1 ORDER BY COALESCE(published_at, NOW()) DESC, id DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('[flexion] get_latest_news failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Güvenli e-posta bildirimi gönderici.
 *
 * Hosting'de PHP mail() devre dışıysa hata vermek yerine sessizce loglar;
 * bu sayede form işleme hiçbir zaman 500 üretmez.
 */
function send_notification_mail(string $to, string $subject, string $body, string $from = ''): void
{
    if (!$to) {
        return;
    }

    if (!function_exists('mail')) {
        error_log('[flexion] mail() function unavailable — e-posta gönderilemedi. Alıcı: ' . $to);
        return;
    }

    if (!$from) {
        $host = $_SERVER['HTTP_HOST'] ?? 'flexion.com';
        $from = 'From: noreply@' . $host;
    }

    if (!@mail($to, $subject, $body, $from)) {
        error_log('[flexion] mail() başarısız — Alıcı: ' . $to . ' | Konu: ' . $subject);
    }
}

/**
 * Haberler banner HTML'ini basar (liste ve detay görünümü için ortak).
 * Çağrıldığı yerde doğrudan ekrana yazar.
 */
function render_news_banner(): void
{
    $bannerImg   = get_setting('news_banner_image', '');
    if (!$bannerImg) {
        return;
    }
    $bannerTitle = t('news_banner_title', get_setting('news_banner_title', 'News &amp; Insights'));
    $opacity     = max(0, min(100, (int) get_setting('news_banner_opacity', '50')));
    $blur        = max(0, min(20,  (int) get_setting('news_banner_blur', '0')));
    $titleColor  = get_setting('news_banner_title_color', '#ffffff');
    $titleSize   = get_setting('news_banner_title_size', '2rem');
    $titlePos    = get_setting('news_banner_title_position', 'center');
    $alignMap    = ['left' => 'text-start', 'center' => 'text-center', 'right' => 'text-end'];
    $alignClass  = $alignMap[$titlePos] ?? 'text-center';
    ?>
    <section class="fx-page-banner mb-0">
        <div class="fx-banner-bg" style="background-image:url('<?= e($bannerImg) ?>');
             filter:blur(<?= $blur ?>px); transform:scale(1.05);"></div>
        <div class="fx-banner-overlay" style="background:rgba(0,0,0,<?= round($opacity / 100, 2) ?>);"></div>
        <div class="fx-banner-content">
            <div class="container <?= $alignClass ?>">
                <h1 class="fx-banner-title"
                    style="color:<?= e($titleColor) ?>;font-size:<?= e($titleSize) ?>;">
                    <?= e($bannerTitle) ?>
                </h1>
            </div>
        </div>
    </section>
    <?php
}

// ════════════════════════════════════════════════════════════════════
//  i18n Yardımcıları
// ════════════════════════════════════════════════════════════════════

/**
 * site_translations tablosundan UI metnini getirir.
 * Önce CURRENT_LANG, yoksa 'en', o da yoksa $default döner.
 */
function t(string $key, string $default = ''): string
{
    static $cache = [];
    $lang = defined('CURRENT_LANG') ? CURRENT_LANG : 'en';
    $cacheKey = $lang . ':' . $key;

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT `value` FROM `site_translations`
             WHERE `key` = :k AND `language` = :l LIMIT 1"
        );
        $stmt->execute([':k' => $key, ':l' => $lang]);
        $val = $stmt->fetchColumn();

        if ($val === false && $lang !== 'en') {
            $stmt->execute([':k' => $key, ':l' => 'en']);
            $val = $stmt->fetchColumn();
        }

        $result = ($val !== false) ? (string)$val : $default;
    } catch (Throwable $e) {
        error_log('[flexion] t() error for key=' . $key . ': ' . $e->getMessage());
        $result = $default;
    }

    $cache[$cacheKey] = $result;
    return $result;
}

/**
 * Türkçe/özel karakterleri içeren metinden URL dostu slug üretir.
 */
function make_slug(string $text): string
{
    $map = [
        'ş'=>'s','Ş'=>'s','ı'=>'i','İ'=>'i','ğ'=>'g','Ğ'=>'g',
        'ü'=>'u','Ü'=>'u','ö'=>'o','Ö'=>'o','ç'=>'c','Ç'=>'c',
        'ä'=>'ae','Ä'=>'ae','ö'=>'o','ü'=>'u','ß'=>'ss',
        'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','å'=>'a',
        'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
        'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
        'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o',
        'ù'=>'u','ú'=>'u','û'=>'u',
        'ý'=>'y','ñ'=>'n',
    ];
    $text = strtr($text, $map);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: bin2hex(random_bytes(4));
}

/**
 * Çeviri tablosundan belirli bir kayıt için istenen dil çevirisini getirir.
 * Bulunamazsa varsayılan dil (en) kaydına geri döner.
 *
 * @param string $table   Çeviri tablosu adı (örn. 'product_translations')
 * @param string $fkCol   Foreign key kolon adı (örn. 'product_id')
 * @param int    $id      Kayıt ID'si
 * @param string $lang    İstenen dil kodu (örn. 'de')
 * @return array          Çeviri satırı (boş dizi döner bulunamazsa)
 */
function get_translation(string $table, string $fkCol, int $id, string $lang = ''): array
{
    if (!defined('CURRENT_LANG')) return [];
    $lang = $lang ?: CURRENT_LANG;
    try {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE `{$fkCol}` = ? AND `language` = ? LIMIT 1");
        $stmt->execute([$id, $lang]);
        $row = $stmt->fetch();
        if ($row) return $row;

        // Fallback to English
        if ($lang !== 'en') {
            $stmt->execute([$id, 'en']);
            $row = $stmt->fetch();
            if ($row) return $row;
        }
    } catch (Throwable $e) {
        error_log('get_translation error: ' . $e->getMessage());
    }
    return [];
}

/**
 * Çeviriyi kaydeder veya günceller (UPSERT).
 *
 * @param string $table    Çeviri tablosu adı
 * @param string $fkCol    Foreign key kolon adı
 * @param int    $id       Kayıt ID'si
 * @param string $lang     Dil kodu
 * @param array  $fields   Kolon => değer eşlemeleri
 */
function save_translation(string $table, string $fkCol, int $id, string $lang, array $fields): void
{
    try {
        $pdo = db();
        $fields[$fkCol] = $id;
        $fields['language'] = $lang;
        $cols   = implode(', ', array_map(fn($c) => "`{$c}`", array_keys($fields)));
        $ph     = implode(', ', array_map(fn($c) => ":{$c}", array_keys($fields)));
        $update = implode(', ', array_map(fn($c) => "`{$c}` = :{$c}", array_keys($fields)));
        $pdo->prepare("INSERT INTO `{$table}` ({$cols}) VALUES ({$ph}) ON DUPLICATE KEY UPDATE {$update}")
            ->execute($fields);
    } catch (Throwable $e) {
        error_log('save_translation error: ' . $e->getMessage());
    }
}

