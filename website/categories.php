<?php

require_once __DIR__ . '/includes/header.php';

$categoriesTree   = get_categories_tree();
$categories       = $categoriesTree;
$activeCategoryId = 0;

// Kategori çevirilerini yükle
$_catTrans = [];
if (!empty($categories)) {
    $_lang = defined('CURRENT_LANG') ? CURRENT_LANG : 'en';
    try {
        $_pdo = db();
        $_ids = array_column($categories, 'id');
        $_in  = implode(',', array_fill(0, count($_ids), '?'));
        $_st  = $_pdo->prepare(
            "SELECT category_id, language, name, short_description, slug
             FROM category_translations
             WHERE category_id IN ({$_in}) AND language IN (?, 'en')"
        );
        $_st->execute(array_merge($_ids, [$_lang]));
        foreach ($_st->fetchAll() as $_tr) {
            $_catTrans[$_tr['category_id']][$_tr['language']] = $_tr;
        }
    } catch (Throwable $_e) {}
}
?>

<section class="py-5">
    <div class="container">
        <div class="row">
            <aside class="col-lg-3 mb-4 mb-lg-0">
                <?php require __DIR__ . '/includes/categories_sidebar.php'; ?>
            </aside>
            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-end mb-4">
                    <div>
                        <h1 class="h3 mb-1"><?= e(t('cat_categories_title', 'All Categories')) ?></h1>
                        <p class="text-muted mb-0 small">
                            <?= e(t('cat_categories_subtitle', 'Industrial cable and hose solutions for every application.')) ?>
                        </p>
                    </div>
                </div>
                <div class="row g-3">
                    <?php foreach ($categories as $cat): ?>
                        <?php
                        $_lang = defined('CURRENT_LANG') ? CURRENT_LANG : 'en';
                        $_ctr  = $_catTrans[$cat['id']][$_lang]
                              ?? $_catTrans[$cat['id']]['en']
                              ?? null;
                        $catName  = $_ctr['name']              ?? $cat['name'];
                        $catDesc  = $_ctr['short_description'] ?? ($cat['short_description'] ?? '');
                        $catSlug  = $_ctr['slug']              ?? $cat['slug'] ?? '';
                        $catHref  = $catSlug ? localized_url('/' . $catSlug) : '#';
                        ?>
                        <div class="col-6 col-md-4 fx-animate">
                            <a href="<?= e($catHref) ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-dark overflow-hidden">
                                <?php if (!empty($cat['image'])): ?>
                                    <img src="<?= e(asset_url($cat['image'])) ?>" class="card-img-top fx-card-img" alt="<?= e($catName) ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="fx-card-img bg-light text-muted d-flex align-items-center justify-content-center">
                                        <i class="bi bi-image fs-2"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="card-body py-3">
                                    <h2 class="h6 mb-1"><?= e($catName) ?></h2>
                                    <p class="small text-muted mb-0"><?= e($catDesc) ?></p>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
