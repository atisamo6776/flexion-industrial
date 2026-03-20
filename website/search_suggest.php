<?php
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

$pdo  = db();
$like = '%' . $q . '%';
$items = [];

// Kategoriler önce (max 2)
try {
    $stmtC = $pdo->prepare(
        'SELECT c.id, c.image,
                COALESCE(NULLIF(ct.name, \'\'), c.name) AS name,
                COALESCE(NULLIF(ct.slug, \'\'), c.slug) AS slug
         FROM categories c
         LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language = :lang
         WHERE c.is_active = 1
           AND (c.name LIKE :q1 OR ct.name LIKE :q2)
         ORDER BY name ASC
         LIMIT 2'
    );
    $stmtC->execute([
        ':lang' => CURRENT_LANG,
        ':q1'   => $like,
        ':q2'   => $like,
    ]);
    foreach (($stmtC->fetchAll() ?: []) as $row) {
        $slug  = (string)($row['slug'] ?? '');
        $img   = !empty($row['image']) ? asset_url((string)$row['image']) : '';
        $items[] = [
            'type'  => 'category',
            'title' => (string)($row['name'] ?? ''),
            'url'   => $slug !== '' ? localized_url('/' . $slug) : localized_url('/categories'),
            'image' => $img,
        ];
    }
} catch (Throwable $e) {
    // sessiz
}

// Ürünler (kalan slot: 5 - kategori sayısı)
$productLimit = 5 - count($items);
if ($productLimit > 0) {
    try {
        $stmtP = $pdo->prepare(
            'SELECT p.id, p.main_image,
                    COALESCE(NULLIF(pt.name, \'\'), p.name) AS name,
                    COALESCE(NULLIF(pt.slug, \'\'), p.slug) AS slug
             FROM products p
             LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language = :lang
             WHERE p.is_active = 1
               AND (
                    p.name LIKE :q1 OR p.code LIKE :q2 OR p.short_description LIKE :q3
                    OR pt.name LIKE :q4 OR pt.short_description LIKE :q5
               )
             ORDER BY name ASC
             LIMIT ' . $productLimit
        );
        $stmtP->execute([
            ':lang' => CURRENT_LANG,
            ':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like, ':q5' => $like,
        ]);
        foreach (($stmtP->fetchAll() ?: []) as $row) {
            $slug    = (string)($row['slug'] ?? '');
            $img     = !empty($row['main_image']) ? asset_url((string)$row['main_image']) : '';
            $items[] = [
                'type'  => 'product',
                'title' => (string)($row['name'] ?? ''),
                'url'   => $slug !== '' ? product_url($slug) : 'product?id=' . (int)($row['id'] ?? 0),
                'image' => $img,
            ];
        }
    } catch (Throwable $e) {
        // sessiz
    }
}

echo json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
