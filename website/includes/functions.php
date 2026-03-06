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
            $settings = [];
        }
    }

    return $settings[$key] ?? $default;
}

/**
 * Ana menüyü getirir. Tablo yoksa boş dizi döner.
 */
function get_main_menu(): array
{
    try {
        $pdo   = db();
        $stmt  = $pdo->query('SELECT * FROM menu_items WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
        $items = $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }

    // Basit hiyerarşi
    $tree = [];
    foreach ($items as $item) {
        $item['children'] = [];
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
 * Aktif kategorileri (üst seviye) getirir. Tablo yoksa boş dizi döner.
 */
function get_active_categories(): array
{
    try {
        $pdo  = db();
        $stmt = $pdo->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC');
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Ana sayfa bölümlerini getirir. Tablo yoksa boş dizi döner.
 */
function get_home_sections(): array
{
    try {
        $pdo  = db();
        $stmt = $pdo->query('SELECT * FROM home_sections WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
    } catch (Throwable $e) {
        return [];
    }

    $sections = [];
    foreach ($stmt as $row) {
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
        return [];
    }
}

