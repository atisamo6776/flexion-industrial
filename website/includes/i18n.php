<?php
/**
 * i18n.php — Çok Dilli Destek
 *
 * Desteklenen diller:   en (varsayılan), de, fr, it
 * Dil öncelik sırası:
 *   1. URL path prefix  (/de/..., /fr/..., /it/...)
 *   2. fx_lang cookie
 *   3. Accept-Language tarayıcı başlığı
 *   4. Varsayılan: en
 */

define('SUPPORTED_LANGS', ['en', 'de', 'it', 'fr']);
define('DEFAULT_LANG',    'en');

if (!defined('CURRENT_LANG')) {
    define('CURRENT_LANG', _i18n_detect_lang());
}

// ── Dil URL ön eki ──────────────────────────────────────────────────────────
// URL'deki /de, /fr, /it ön ekini döndürür; en için boş string.
function lang_prefix(string $lang = ''): string {
    $l = $lang ?: CURRENT_LANG;
    return ($l !== DEFAULT_LANG && in_array($l, SUPPORTED_LANGS, true)) ? '/' . $l : '';
}

// ── Seçili dilde ana sayfa URL'i ────────────────────────────────────────────
// EN için '/', DE için '/de/', FR için '/fr/', IT için '/it/'
function home_url(): string {
    $prefix = lang_prefix();
    return $prefix !== '' ? $prefix . '/' : '/';
}

// ── Seçili dilde bir sayfa slug'ı için temiz URL (/about-us veya /de/uber-uns)
function page_url(string $slug): string {
    if ($slug === '') return home_url();
    $prefix = lang_prefix();
    return $prefix !== '' ? $prefix . '/' . $slug : '/' . $slug;
}

// ── İç linki seçili dile göre prefix'le ────────────────────────────────────
// Harici URL'ler (http/https ile başlayanlar) değişmeden döner.
// Ana sayfa (index.php, /index.php, /) → her zaman home_url() (temiz URL: / veya /de/).
// Zaten dil prefix'i olan yollar (/de/...) değişmeden döner.
// Diğer tüm iç linklere CURRENT_LANG prefix'i eklenir.
function localized_url(string $url): string {
    if ($url === '' || $url === '#' || $url === '/') {
        return $url ?: '/';
    }
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }
    // Ana sayfa: index.php, /index.php, index, /index → temiz ana sayfa URL'i (/ veya /de/)
    $path = parse_url($url, PHP_URL_PATH);
    if ($path !== null && $path !== '') {
        $path = trim($path, '/');
        $base = $path !== '' ? strtolower(pathinfo($path, PATHINFO_FILENAME)) : '';
        if ($path === '' || $path === 'index' || $path === 'index.php' || $base === 'index') {
            return home_url();
        }
    }
    $prefix = lang_prefix();
    if ($prefix === '') {
        return $url;
    }
    // Zaten bu dil prefix'i ile başlıyorsa dokunma
    foreach (SUPPORTED_LANGS as $l) {
        if ($l !== DEFAULT_LANG && str_starts_with($url, '/' . $l . '/')) {
            return $url;
        }
        if ($l !== DEFAULT_LANG && $url === '/' . $l) {
            return $url;
        }
    }
    $path = ltrim($url, '/');
    return $prefix . '/' . $path;
}

// ── Dil değiştirilince mevcut sayfa URL'ini üretir ──────────────────────────
function lang_switch_url(string $lang): string {
    $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    // Mevcut dil ön ekini soy
    foreach (SUPPORTED_LANGS as $l) {
        if ($l !== DEFAULT_LANG && preg_match('#^/' . preg_quote($l, '#') . '(/|$)#', $uri, $m)) {
            $uri = isset($m[1]) ? ($m[1] === '/' ? substr($uri, strlen($l) + 1) : '/') : '/';
            break;
        }
    }
    if ($uri === '') $uri = '/';
    $prefix = ($lang !== DEFAULT_LANG) ? '/' . $lang : '';
    return $prefix . $uri;
}

// ── Dil tespiti ─────────────────────────────────────────────────────────────
function _i18n_detect_lang(): string {
    // 1. URL prefix
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    foreach (SUPPORTED_LANGS as $l) {
        if ($l !== DEFAULT_LANG && preg_match('#^/' . preg_quote($l, '#') . '(/|$)#', $path)) {
            return $l;
        }
    }

    // 2. Cookie
    $cookie = $_COOKIE['fx_lang'] ?? '';
    if ($cookie && in_array($cookie, SUPPORTED_LANGS, true)) {
        return $cookie;
    }

    // 3. GET (rewrite: /de/ → index.php?lang=de)
    $getLang = $_GET['lang'] ?? '';
    if ($getLang && in_array($getLang, SUPPORTED_LANGS, true)) {
        return $getLang;
    }

    // 4. Accept-Language
    $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    if ($accept) {
        foreach (explode(',', $accept) as $entry) {
            $tag = strtolower(trim(explode(';', $entry)[0]));
            $primary = explode('-', $tag)[0];
            if (in_array($primary, SUPPORTED_LANGS, true)) {
                return $primary;
            }
        }
    }

    // 5. Varsayılan
    return DEFAULT_LANG;
}
