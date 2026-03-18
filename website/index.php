<?php

require_once __DIR__ . '/includes/header.php';

$sections   = get_home_sections();
$categories = get_active_categories();
$latestNews = get_latest_news(3);

// Kategori çevirilerini yükle (toplu sorgu)
$_catTrans = [];
if (!empty($categories)) {
    $_lang = defined('CURRENT_LANG') ? CURRENT_LANG : 'en';
    try {
        $_pdo2 = db();
        $_catIds = array_column($categories, 'id');
        $_in   = implode(',', array_fill(0, count($_catIds), '?'));
        $_stmt2 = $_pdo2->prepare(
            "SELECT category_id, language, name, short_description, slug
             FROM category_translations
             WHERE category_id IN ({$_in}) AND language IN (?, 'en')"
        );
        $_stmt2->execute(array_merge($_catIds, [$_lang]));
        foreach ($_stmt2->fetchAll() as $_tr) {
            $_catTrans[$_tr['category_id']][$_tr['language']] = $_tr;
        }
    } catch (Throwable $_e) { }
}

// Haber çevirilerini yükle
$_newsTrans = [];
if (!empty($latestNews)) {
    $_lang = defined('CURRENT_LANG') ? CURRENT_LANG : 'en';
    try {
        $_pdo3 = db();
        $_nIds = array_column($latestNews, 'id');
        $_in3  = implode(',', array_fill(0, count($_nIds), '?'));
        $_stmt3 = $_pdo3->prepare(
            "SELECT news_id, language, title, summary, slug
             FROM news_translations
             WHERE news_id IN ({$_in3}) AND language IN (?, 'en')"
        );
        $_stmt3->execute(array_merge($_nIds, [$_lang]));
        foreach ($_stmt3->fetchAll() as $_ntr) {
            $_newsTrans[$_ntr['news_id']][$_ntr['language']] = $_ntr;
        }
    } catch (Throwable $_e) { }
}

// Hiç aktif blok yoksa varsayılan fallback blokları göster
if (empty($sections)) {
    $sections = [
        [
            'section_type' => 'hero',
            'title'        => t('home_hero_title', 'Industrial Cable Solutions'),
            'content'      => [
                'eyebrow'     => 'Flexion Industrial',
                'subtitle'    => t('home_hero_subtitle', 'High-performance cable solutions for demanding industrial environments.'),
                'button_text' => t('home_hero_btn', 'View Products'),
                'button_url'  => 'categories',
            ],
        ],
        [
            'section_type' => 'sectors',
            'title'        => t('home_sectors_title', 'Application Sectors'),
            'content'      => [
                'subtitle' => t('home_sectors_subtitle', 'A wide range of applications from energy to marine.'),
            ],
        ],
        [
            'section_type' => 'news',
            'title'        => t('home_news_title', 'Latest News'),
            'content'      => ['subtitle' => ''],
        ],
    ];
}
?>

<?php foreach ($sections as $section): ?>
    <?php
    $type = $section['section_type'];
    $c    = $section['content'];

    // Görsel: önce upload edilmiş dosya yolu, yoksa ham URL alanı
    $imgSrc = '';
    if (!empty($c['image'])) {
        $imgSrc = $c['image'];
    } elseif (!empty($c['image_url'])) {
        $imgSrc = $c['image_url'];
    }

    $imageMode    = $c['image_mode'] ?? 'normal';
    $imageOpacity = isset($c['image_opacity']) ? (int) $c['image_opacity'] : 100;
    $imageBlur    = isset($c['image_blur']) ? (int) $c['image_blur'] : 0;
    $imageOpacity = max(0, min(100, $imageOpacity));
    $imageBlur    = max(0, min(20, $imageBlur));
    ?>

    <?php if ($type === 'hero'): ?>
        <?php
        $isCover   = ($imageMode === 'cover' && $imgSrc);
        $sectionCl = $isCover ? 'fx-hero fx-hero-cover' : 'fx-hero';
        ?>
        <section class="<?= $sectionCl ?>">
            <?php if ($isCover): ?>
                <?php
                $bgImgEsc    = htmlspecialchars(asset_url($imgSrc), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $bgStyle     = "background-image:url('{$bgImgEsc}');";
                if ($imageBlur > 0) {
                    $bgStyle .= "filter:blur({$imageBlur}px);";
                }
                $overlayAlpha = round(max(0, min(0.85, 1 - $imageOpacity / 100)), 2);
                ?>
                <div class="fx-hero-bg" style="<?= $bgStyle ?>"></div>
                <div class="fx-hero-overlay" style="background:rgba(0,0,0,<?= $overlayAlpha ?>);"></div>
            <?php endif; ?>
            <div class="container position-relative">
                <div class="row align-items-center">
                    <div class="col-lg-6 mb-4 mb-lg-0">
                        <p class="text-uppercase small mb-2" style="color:#f87171;"><?= e($c['eyebrow'] ?? 'Flexion Industrial') ?></p>
                        <h1 class="display-5 fw-bold mb-3"><?= e($section['title'] ?? ($c['title'] ?? 'Industrial Cable Solutions')) ?></h1>
                        <p class="lead mb-4"><?= e($c['subtitle'] ?? '') ?></p>
                        <?php if (!empty($c['button_text'])): ?>
                            <?php
                            $btnUrl = $c['button_url'] ?? '#';
                            if ($btnUrl === 'categories' || $btnUrl === 'sectors' || $btnUrl === '/categories') {
                                $btnUrl = categories_list_url();
                            } elseif ($btnUrl !== '' && $btnUrl !== '#') {
                                $btnUrl = localized_url($btnUrl);
                            }
                            ?>
                            <a href="<?= e($btnUrl) ?>" class="btn btn-primary btn-lg">
                                <?= e($c['button_text']) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php if (!$isCover): ?>
                        <div class="col-lg-6 text-center">
                            <?php if ($imgSrc): ?>
                                <img src="<?= e(asset_url($imgSrc)) ?>" alt="" class="img-fluid rounded-3">
                            <?php else: ?>
                                <div class="bg-dark bg-opacity-25 rounded-3 d-flex align-items-center justify-content-center" style="min-height:280px;">
                                    <span class="text-white-50 small"><?= e(t('home_upload_image', 'Upload image (Admin → Home Sections)')) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

    <?php elseif ($type === 'sectors'): ?>
        <section class="py-5 bg-light">
            <div class="container">
                <div class="d-flex justify-content-between align-items-end mb-4">
                    <div>
                        <h2 class="h3 mb-1"><?= e($section['title'] ?? ($c['title'] ?? 'Uygulama Sektörleri')) ?></h2>
                        <p class="text-muted mb-0 small"><?= e($c['subtitle'] ?? '') ?></p>
                    </div>
                </div>
                <div class="row g-3">
                    <?php foreach ($categories as $cat):
                        $_cLang = defined('CURRENT_LANG') ? CURRENT_LANG : 'en';
                        $_cTr   = $_catTrans[$cat['id']][$_cLang]
                               ?? $_catTrans[$cat['id']]['en']
                               ?? null;
                        $_cName  = $_cTr['name']              ?? $cat['name'];
                        $_cDesc  = $_cTr['short_description'] ?? ($cat['short_description'] ?? '');
                        $_cSlug  = $_cTr['slug']              ?? ($cat['slug'] ?? '');
                        $_cHref  = $_cSlug ? localized_url('/' . $_cSlug) : localized_url('/categories');
                    ?>
                        <div class="col-6 col-md-3">
                            <a href="<?= e($_cHref) ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                                <?php if (!empty($cat['image'])): ?>
                                    <img src="<?= e(asset_url($cat['image'])) ?>" class="card-img-top" style="height:140px;object-fit:cover;" alt="<?= e($_cName) ?>">
                                <?php else: ?>
                                    <div class="bg-secondary-subtle d-flex align-items-center justify-content-center" style="height:140px;">
                                        <i class="bi bi-grid fs-2 text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="card-body py-3">
                                    <h3 class="h6 mb-1"><?= e($_cName) ?></h3>
                                    <p class="small text-muted mb-0"><?= e($_cDesc) ?></p>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($categories)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <?= e(t('home_no_categories', 'No categories added yet.')) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

    <?php elseif ($type === 'text_image'): ?>
        <section class="py-5">
            <div class="container">
                <?php
                $imgCol = (int) ($c['image_col'] ?? 6);
                if (!in_array($imgCol, [4, 6, 7], true)) {
                    $imgCol = 6;
                }
                $textCol = 12 - $imgCol;
                ?>
                <div class="row align-items-center <?= !empty($c['image_right']) ? 'flex-row-reverse' : '' ?>">
                    <div class="col-md-<?= $textCol ?> mb-4 mb-md-0">
                        <h2 class="h3 mb-3"><?= e($section['title'] ?? ($c['title'] ?? 'Hakkımızda')) ?></h2>
                        <p class="text-muted mb-3"><?= e($c['text'] ?? '') ?></p>
                        <?php if (!empty($c['button_text'])): ?>
                            <a href="<?= e($c['button_url'] ?? '#') ?>" class="btn btn-outline-secondary">
                                <?= e($c['button_text']) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-<?= $imgCol ?>">
                        <?php if ($imgSrc): ?>
                            <img src="<?= e(asset_url($imgSrc)) ?>" alt="" class="img-fluid rounded-3 shadow-sm">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

    <?php elseif ($type === 'news'): ?>
        <section class="py-5 bg-light">
            <div class="container">
                <div class="d-flex justify-content-between align-items-end mb-4">
                    <div>
                        <h2 class="h3 mb-1"><?= e($section['title'] ?? ($c['title'] ?? 'Güncel Haberler')) ?></h2>
                        <p class="text-muted mb-0 small"><?= e($c['subtitle'] ?? '') ?></p>
                    </div>
                </div>
                <div class="row g-3">
                    <?php foreach ($latestNews as $news):
                        $_nLang = defined('CURRENT_LANG') ? CURRENT_LANG : 'en';
                        $_nTr   = $_newsTrans[$news['id']][$_nLang]
                               ?? $_newsTrans[$news['id']]['en']
                               ?? null;
                        $_nTitle   = $_nTr['title']   ?? $news['title'];
                        $_nSummary = $_nTr['summary']  ?? ($news['summary'] ?? '');
                        $_nSlug    = $_nTr['slug']    ?? ($news['slug'] ?? '');
                        $_nHref    = $_nSlug ? '/news/' . ltrim($_nSlug, '/') : 'news?slug=' . urlencode($news['slug'] ?? '');
                    ?>
                        <div class="col-md-4">
                            <a href="<?= e($_nHref) ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                                <?php if (!empty($news['image'])): ?>
                                    <img src="<?= e(asset_url($news['image'])) ?>" class="card-img-top" style="height:180px;object-fit:cover;" alt="<?= e($_nTitle) ?>">
                                <?php endif; ?>
                                <div class="card-body">
                                    <h3 class="h6 mb-2"><?= e($_nTitle) ?></h3>
                                    <p class="small text-muted mb-0"><?= e($_nSummary) ?></p>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($latestNews)): ?>
                        <div class="col-12">
                            <p class="text-muted small"><?= e(t('home_no_news', 'No news added yet.')) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
<?php endforeach; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
