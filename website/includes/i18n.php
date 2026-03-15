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

    // 3. Accept-Language
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

    return DEFAULT_LANG;
}
