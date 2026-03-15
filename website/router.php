<?php
/**
 * router.php
 *
 * Çift görev üstlenir:
 *   1. Eski ?id= tabanlı URL'leri slug'lı URL'ye 301 yönlendirir.
 *   2. Tek segment URL'lerin kategori mi, sayfa mı olduğuna karar verir.
 */

require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/functions.php';

$route = $_GET['_route'] ?? '';
$lang  = $_GET['lang']   ?? CURRENT_LANG;
$slug  = $_GET['slug']   ?? '';
$id    = (int) ($_GET['id'] ?? 0);
$pdo   = db();

$prefix = ($lang !== 'en') ? '/' . $lang : '';

// ── Eski URL'ler: 301 redirect ──────────────────────────────────────────────
if ($route === 'old-cat' && $id > 0) {
    try {
        // Önce çeviri tablosunda slug ara
        $stmt = $pdo->prepare(
            'SELECT ct.slug FROM category_translations ct WHERE ct.category_id = ? AND ct.language = ? LIMIT 1'
        );
        $stmt->execute([$id, $lang]);
        $tr = $stmt->fetchColumn();
        if ($tr) {
            header('Location: ' . $prefix . '/' . rawurlencode($tr), true, 301);
            exit;
        }
        // Ana tabloda dene
        $stmt2 = $pdo->prepare('SELECT slug FROM categories WHERE id = ? LIMIT 1');
        $stmt2->execute([$id]);
        $mainSlug = $stmt2->fetchColumn();
        if ($mainSlug) {
            header('Location: ' . $prefix . '/' . rawurlencode($mainSlug), true, 301);
            exit;
        }
    } catch (Throwable $e) {
        error_log('router old-cat: ' . $e->getMessage());
    }
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

if ($route === 'old-prod' && $id > 0) {
    try {
        // Ürün + kategorisini bul
        $stmt = $pdo->prepare(
            'SELECT p.id AS pid, pt.slug AS p_slug_tr, p.slug AS p_slug,
                    c.id AS cid, ct.slug AS c_slug_tr, c.slug AS c_slug
             FROM products p
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language = ?
             LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language = ?
             WHERE p.id = ? LIMIT 1'
        );
        $stmt->execute([$lang, $lang, $id]);
        $row = $stmt->fetch();
        if ($row) {
            $cSlug = $row['c_slug_tr'] ?: $row['c_slug'];
            $pSlug = $row['p_slug_tr'] ?: $row['p_slug'];
            header('Location: ' . $prefix . '/' . rawurlencode($cSlug) . '/' . rawurlencode($pSlug), true, 301);
            exit;
        }
    } catch (Throwable $e) {
        error_log('router old-prod: ' . $e->getMessage());
    }
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

if ($route === 'old-news' && $id > 0) {
    try {
        $stmt = $pdo->prepare(
            'SELECT nt.slug AS t_slug, n.slug AS slug
             FROM news n
             LEFT JOIN news_translations nt ON nt.news_id = n.id AND nt.language = ?
             WHERE n.id = ? LIMIT 1'
        );
        $stmt->execute([$lang, $id]);
        $row = $stmt->fetch();
        if ($row) {
            $nSlug = $row['t_slug'] ?: $row['slug'];
            $newsPrefix = ($lang !== 'en') ? $prefix . '/haberler' : '/haberler';
            header('Location: ' . $newsPrefix . '/' . rawurlencode($nSlug), true, 301);
            exit;
        }
    } catch (Throwable $e) {
        error_log('router old-news: ' . $e->getMessage());
    }
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

// ── Tek segment: kategori mi, sayfa mı? ──────────────────────────────────────
if ($route === 'cat-or-page' && $slug !== '') {
    // Önce: çeviri tablosunda kategori slug'ı
    try {
        $stmt = $pdo->prepare(
            'SELECT c.id FROM category_translations ct
             JOIN categories c ON c.id = ct.category_id
             WHERE ct.slug = ? AND ct.language = ? AND c.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([$slug, $lang]);
        $catId = $stmt->fetchColumn();
        if (!$catId) {
            // Ana tabloda dene
            $stmt2 = $pdo->prepare('SELECT id FROM categories WHERE slug = ? AND is_active = 1 LIMIT 1');
            $stmt2->execute([$slug]);
            $catId = $stmt2->fetchColumn();
        }
        if ($catId) {
            $_GET['id']   = $catId;
            $_GET['lang'] = $lang;
            require __DIR__ . '/category.php';
            exit;
        }
    } catch (Throwable $e) {
        error_log('router cat lookup: ' . $e->getMessage());
    }

    // Sayfa olarak dene
    try {
        $stmt = $pdo->prepare(
            'SELECT p.id FROM page_translations pt
             JOIN pages p ON p.id = pt.page_id
             WHERE pt.slug = ? AND pt.language = ? AND p.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([$slug, $lang]);
        $pageId = $stmt->fetchColumn();
        if (!$pageId) {
            $stmt2 = $pdo->prepare('SELECT id FROM pages WHERE slug = ? AND is_active = 1 LIMIT 1');
            $stmt2->execute([$slug]);
            $pageId = $stmt2->fetchColumn();
        }
        if ($pageId) {
            $_GET['slug'] = $slug;
            $_GET['lang'] = $lang;
            require __DIR__ . '/page.php';
            exit;
        }
    } catch (Throwable $e) {
        error_log('router page lookup: ' . $e->getMessage());
    }

    // Bulunamadı
    http_response_code(404);
    if (file_exists(__DIR__ . '/404.php')) {
        require __DIR__ . '/404.php';
    } else {
        echo '<h1>404 Sayfa Bulunamadı</h1>';
    }
    exit;
}

// Tanımsız route
http_response_code(400);
exit;
