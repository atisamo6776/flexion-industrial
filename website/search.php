<?php
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/functions.php';

$q    = trim((string)($_GET['q'] ?? ''));
$sort = in_array(($_GET['sort'] ?? ''), ['name_asc', 'name_desc', 'relevance'], true)
    ? (string)$_GET['sort']
    : 'relevance';

$pageTitle = t('search_page_title', 'Search Products') . ' | ' . t('site_title', get_setting('site_title', 'Flexion Industrial'));
require_once __DIR__ . '/includes/header.php';

$pdo = db();
$resultsProducts = [];
$resultsCats = [];

if ($q !== '') {
    $like = '%' . $q . '%';

    $orderClause = $sort === 'name_desc' ? 'name DESC' : 'name ASC';

    try {
        $stmt = $pdo->prepare(
            "SELECT p.id, p.slug AS base_slug, p.code, p.main_image,
                    COALESCE(NULLIF(pt.name, ''), p.name) AS name,
                    COALESCE(NULLIF(pt.short_description, ''), p.short_description) AS short_description,
                    COALESCE(NULLIF(pt.slug, ''), p.slug) AS slug
             FROM products p
             LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language = :lang
             WHERE p.is_active = 1
               AND (
                    p.name LIKE :q1 OR p.code LIKE :q2 OR p.short_description LIKE :q3
                    OR pt.name LIKE :q4 OR pt.short_description LIKE :q5
               )
             ORDER BY {$orderClause}
             LIMIT 50"
        );
        $stmt->execute([
            ':lang' => CURRENT_LANG,
            ':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like, ':q5' => $like,
        ]);
        $resultsProducts = $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        $resultsProducts = [];
    }

    try {
        $stmt2 = $pdo->prepare(
            "SELECT c.id, c.image,
                    COALESCE(NULLIF(ct.name, ''), c.name) AS name,
                    COALESCE(NULLIF(ct.short_description, ''), c.short_description) AS short_description,
                    COALESCE(NULLIF(ct.slug, ''), c.slug) AS slug
             FROM categories c
             LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language = :lang
             WHERE c.is_active = 1
               AND (
                    c.name LIKE :q1 OR c.short_description LIKE :q2
                    OR ct.name LIKE :q3 OR ct.short_description LIKE :q4
               )
             ORDER BY {$orderClause}
             LIMIT 20"
        );
        $stmt2->execute([
            ':lang' => CURRENT_LANG,
            ':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like,
        ]);
        $resultsCats = $stmt2->fetchAll() ?: [];
    } catch (Throwable $e) {
        $resultsCats = [];
    }
}

$totalCount  = count($resultsProducts) + count($resultsCats);
$searchBase  = function_exists('localized_url') ? localized_url('/search') : '/search';
$sortOptions = [
    'relevance' => t('search_sort_relevance', 'Relevance'),
    'name_asc'  => t('search_sort_name_asc', 'Name A–Z'),
    'name_desc' => t('search_sort_name_desc', 'Name Z–A'),
];
?>

<section class="py-5">
    <div class="container">

        <?php if ($q === ''): ?>
            <!-- Boş arama kutusu -->
            <h1 class="h4 mb-3"><?= e(t('search_results_title', 'Search Results')) ?></h1>
            <p class="text-muted mb-4"><?= e(t('search_empty_prompt', 'Type a keyword in the search box.')) ?></p>
            <form action="<?= e($searchBase) ?>" method="get" class="mb-4" style="max-width:480px;">
                <div class="input-group">
                    <input type="search" name="q" class="form-control" placeholder="<?= e(t('search_placeholder_long', 'Search hoses or categories...')) ?>">
                    <button class="btn btn-primary" type="submit"><?= e(t('search_submit', 'Search')) ?></button>
                </div>
            </form>

        <?php else: ?>
            <!-- Sonuç başlığı — "Search "water" 10 results have been found." -->
            <?php
                $sentenceTpl = t('search_results_sentence', 'Search "{q}" {count} results have been found.');
                $sentence    = str_replace(['{q}', '{count}'], [$q, (string)$totalCount], $sentenceTpl);
            ?>
            <h1 class="h5 fw-semibold mb-2"><?= e($sentence) ?></h1>

            <!-- Order by satırı -->
            <div class="d-flex align-items-center gap-2 mb-4 pb-3 border-bottom">
                <span class="small text-muted fw-medium"><?= e(t('search_order_by', 'Order by')) ?></span>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= e($sortOptions[$sort] ?? $sortOptions['relevance']) ?>
                    </button>
                    <ul class="dropdown-menu">
                        <?php foreach ($sortOptions as $sortKey => $sortLabel): ?>
                            <li>
                                <a class="dropdown-item<?= $sort === $sortKey ? ' active' : '' ?>"
                                   href="<?= e($searchBase . '?' . http_build_query(['q' => $q, 'sort' => $sortKey])) ?>">
                                    <?= e($sortLabel) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <?php if ($totalCount === 0): ?>
                <p class="text-muted"><?= e(t('search_no_results', 'no results found.')) ?></p>
            <?php endif; ?>

            <!-- Kategoriler — ana sayfa kart görünümüyle birebir -->
            <?php if (!empty($resultsCats)): ?>
                <h2 class="h6 text-uppercase text-muted mb-3 fw-semibold"><?= e(t('search_categories_heading', 'Categories')) ?></h2>
                <div class="row g-3 mb-5">
                    <?php foreach ($resultsCats as $cat): ?>
                        <?php $catHref = localized_url('/' . ltrim((string)($cat['slug'] ?? ''), '/')); ?>
                        <div class="col-6 col-sm-4 col-md-3">
                            <a href="<?= e($catHref) ?>" class="card h-100 text-decoration-none text-dark border-0 overflow-hidden">
                                <?php if (!empty($cat['image'])): ?>
                                    <img src="<?= e(asset_url((string)$cat['image'])) ?>"
                                         class="fx-card-img"
                                         alt="<?= e((string)$cat['name']) ?>"
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="fx-card-img d-flex align-items-center justify-content-center bg-light">
                                        <i class="bi bi-grid text-muted fs-2"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="card-body py-2 px-3">
                                    <h3 class="h6 mb-0 fw-semibold"><?= e((string)$cat['name']) ?></h3>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Ürünler — mevcut ürün kartı sınıflarıyla birebir -->
            <?php if (!empty($resultsProducts)): ?>
                <h2 class="h6 text-uppercase text-muted mb-3 fw-semibold"><?= e(t('search_products_heading', 'Products')) ?></h2>
                <div class="row g-3">
                    <?php foreach ($resultsProducts as $product): ?>
                        <?php $prodSlug = (string)($product['slug'] ?? ''); ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <a href="<?= e($prodSlug !== '' ? product_url($prodSlug) : 'product?id=' . (int)$product['id']) ?>"
                               class="card h-100 text-decoration-none text-dark border-0 overflow-hidden">
                                <?php if (!empty($product['main_image'])): ?>
                                    <div class="fx-product-thumb">
                                        <img src="<?= e(asset_url((string)$product['main_image'])) ?>"
                                             class="fx-product-thumb-img"
                                             alt="<?= e((string)$product['name']) ?>"
                                             loading="lazy">
                                    </div>
                                <?php else: ?>
                                    <div class="fx-product-thumb-placeholder">
                                        <i class="bi bi-box-seam fs-1 text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="card-body py-2 px-3">
                                    <h3 class="h6 fw-semibold mb-1"><?= e((string)$product['name']) ?></h3>
                                    <?php if (!empty($product['code'])): ?>
                                        <p class="small text-muted mb-0"><?= e((string)$product['code']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
