<?php

require_once __DIR__ . '/includes/header.php';

$sections   = get_home_sections();
$categories = get_active_categories();
$latestNews = get_latest_news(3);

// Hiç aktif blok yoksa varsayılan fallback blokları göster
if (empty($sections)) {
    $sections = [
        [
            'section_type' => 'hero',
            'title'        => 'Endüstriyel Kablo Çözümleri',
            'content'      => [
                'eyebrow'     => 'Flexion Industrial',
                'subtitle'    => 'Yüksek performanslı kablo çözümleri sunan Flexion, zorlu endüstriyel ortamlar için güvenilir altyapı sağlar.',
                'button_text' => 'Ürünleri İncele',
                'button_url'  => 'sectors.php',
            ],
        ],
        [
            'section_type' => 'sectors',
            'title'        => 'Uygulama Sektörleri',
            'content'      => [
                'subtitle' => 'Enerji üretiminden denizciliğe kadar geniş bir uygulama yelpazesi.',
            ],
        ],
        [
            'section_type' => 'news',
            'title'        => 'Güncel Haberler',
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
        $style     = '';
        if ($isCover) {
            $style = "background-image:url('".htmlspecialchars($imgSrc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')."');";
        }
        ?>
        <section class="<?= $sectionCl ?>" style="<?= $style ?>">
            <?php if ($isCover): ?>
                <?php
                $opacityCss = $imageOpacity / 100;
                $blurCss    = $imageBlur > 0 ? 'blur(' . $imageBlur . 'px)' : 'none';
                ?>
                <div class="fx-hero-overlay" style="background:rgba(0,0,0,0.45);backdrop-filter:<?= $blurCss ?>;opacity:<?= $opacityCss ?>;"></div>
            <?php endif; ?>
            <div class="container position-relative">
                <div class="row align-items-center">
                    <div class="col-lg-6 mb-4 mb-lg-0">
                        <p class="text-uppercase small mb-2" style="color:#f87171;"><?= e($c['eyebrow'] ?? 'Flexion Industrial') ?></p>
                        <h1 class="display-5 fw-bold mb-3"><?= e($section['title'] ?? ($c['title'] ?? 'Industrial Cable Solutions')) ?></h1>
                        <p class="lead mb-4"><?= e($c['subtitle'] ?? '') ?></p>
                        <?php if (!empty($c['button_text'])): ?>
                            <a href="<?= e($c['button_url'] ?? '#') ?>" class="btn btn-primary btn-lg">
                                <?= e($c['button_text']) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php if (!$isCover): ?>
                        <div class="col-lg-6 text-center">
                            <?php if ($imgSrc): ?>
                                <img src="<?= e($imgSrc) ?>" alt="" class="img-fluid rounded-3 shadow-lg">
                            <?php else: ?>
                                <div class="bg-dark bg-opacity-25 rounded-3 d-flex align-items-center justify-content-center" style="min-height:280px;">
                                    <span class="text-white-50 small">Görsel yükleyin (Admin → Ana Sayfa Blokları)</span>
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
                    <a href="sectors.php" class="btn btn-outline-secondary btn-sm">Tüm sektörleri gör</a>
                </div>
                <div class="row g-3">
                    <?php foreach ($categories as $cat): ?>
                        <div class="col-6 col-md-3">
                            <a href="category.php?id=<?= e((string) $cat['id']) ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                                <?php if (!empty($cat['image'])): ?>
                                    <img src="<?= e($cat['image']) ?>" class="card-img-top" style="height:140px;object-fit:cover;" alt="<?= e($cat['name']) ?>">
                                <?php else: ?>
                                    <div class="bg-secondary-subtle d-flex align-items-center justify-content-center" style="height:140px;">
                                        <i class="bi bi-grid fs-2 text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="card-body py-3">
                                    <h3 class="h6 mb-1"><?= e($cat['name']) ?></h3>
                                    <p class="small text-muted mb-0"><?= e($cat['short_description'] ?? '') ?></p>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($categories)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                Henüz kategori eklenmemiş. Admin panelinden kategori ekleyebilirsin.
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
                            <img src="<?= e($imgSrc) ?>" alt="" class="img-fluid rounded-3 shadow-sm">
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
                    <a href="news.php" class="btn btn-outline-secondary btn-sm">Tüm haberleri gör</a>
                </div>
                <div class="row g-3">
                    <?php foreach ($latestNews as $news): ?>
                        <div class="col-md-4">
                            <a href="news.php?slug=<?= e($news['slug']) ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                                <?php if (!empty($news['image'])): ?>
                                    <img src="<?= e($news['image']) ?>" class="card-img-top" style="height:180px;object-fit:cover;" alt="<?= e($news['title']) ?>">
                                <?php endif; ?>
                                <div class="card-body">
                                    <h3 class="h6 mb-2"><?= e($news['title']) ?></h3>
                                    <p class="small text-muted mb-0"><?= e($news['summary'] ?? '') ?></p>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($latestNews)): ?>
                        <div class="col-12">
                            <p class="text-muted small">Henüz haber eklenmemiş.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
<?php endforeach; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
