<?php

require_once __DIR__ . '/includes/header.php';

$pdo  = db();
$slug = $_GET['slug'] ?? null;

if ($slug) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM news WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $article = $stmt->fetch();
    } catch (Throwable $e) {
        $article = null;
    }

    if (!$article) {
        http_response_code(404);
        ?>
        <div class="container py-5">
            <h1 class="h3 mb-3">Haber bulunamadı</h1>
            <p class="text-muted">Aradığınız içerik sistemde yer almıyor veya pasif durumda.</p>
            <a href="news.php" class="btn btn-outline-secondary btn-sm">Tüm haberlere dön</a>
        </div>
        <?php
        require_once __DIR__ . '/includes/footer.php';
        exit;
    }
    ?>
    <?php
    $bannerImg   = get_setting('news_banner_image', '');
    $bannerTitle = get_setting('news_banner_title', 'Haberler & Insights');
    if ($bannerImg): ?>
        <section class="fx-page-banner d-flex align-items-center mb-4"
                 style="background-image:url('<?= e($bannerImg) ?>');">
            <div class="container">
                <h1 class="h3 text-white mb-0"><?= e($bannerTitle) ?></h1>
            </div>
        </section>
    <?php endif; ?>

    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <h1 class="h3 mb-3"><?= e($article['title']) ?></h1>
                    <?php if (!empty($article['published_at'])): ?>
                        <p class="small text-muted mb-3">
                            <?= e(date('d.m.Y', strtotime($article['published_at']))) ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($article['image'])): ?>
                        <img src="<?= e($article['image']) ?>" alt="<?= e($article['title']) ?>" class="img-fluid rounded-3 mb-4">
                    <?php endif; ?>
                    <div class="text-muted small">
                        <?= $article['content'] ?>
                    </div>
                </div>
                <div class="col-lg-4">
                    <h2 class="h6 mb-3">Diğer Haberler</h2>
                    <ul class="list-unstyled small">
                        <?php
                        $sideNews = [];
                        try {
                            $side = $pdo->prepare('SELECT slug, title FROM news WHERE is_active = 1 AND id <> :id ORDER BY IFNULL(published_at, id) DESC LIMIT 6');
                            $side->execute([':id' => $article['id']]);
                            $sideNews = $side->fetchAll();
                        } catch (Throwable $e) {}
                        foreach ($sideNews as $n): ?>
                            <li class="mb-2">
                                <a href="news.php?slug=<?= e($n['slug']) ?>" class="text-decoration-none">
                                    <?= e($n['title']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </section>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Liste modu
$items = [];
try {
    $stmt  = $pdo->query('SELECT * FROM news WHERE is_active = 1 ORDER BY IFNULL(published_at, id) DESC');
    $items = $stmt->fetchAll();
} catch (Throwable $e) { /* tablo yoksa boş */ }
?>

<?php
$bannerImg   = get_setting('news_banner_image', '');
$bannerTitle = get_setting('news_banner_title', 'Haberler & Insights');
if ($bannerImg): ?>
    <section class="fx-page-banner d-flex align-items-center mb-4"
             style="background-image:url('<?= e($bannerImg) ?>');">
        <div class="container">
            <h1 class="h3 text-white mb-0"><?= e($bannerTitle) ?></h1>
        </div>
    </section>
<?php endif; ?>

<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h1 class="h3 mb-1">Haberler & Insights</h1>
                <p class="text-muted mb-0 small">Flexion ürünleri ve projeleri hakkında güncel içerikler.</p>
            </div>
        </div>
        <div class="row g-3">
            <?php foreach ($items as $news): ?>
                <div class="col-md-4">
                    <a href="news.php?slug=<?= e($news['slug']) ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                        <?php if (!empty($news['image'])): ?>
                            <img src="<?= e($news['image']) ?>" class="card-img-top" alt="<?= e($news['title']) ?>">
                        <?php endif; ?>
                        <div class="card-body">
                            <h2 class="h6 mb-2"><?= e($news['title']) ?></h2>
                            <?php if (!empty($news['published_at'])): ?>
                                <p class="small text-muted mb-1">
                                    <?= e(date('d.m.Y', strtotime($news['published_at']))) ?>
                                </p>
                            <?php endif; ?>
                            <p class="small text-muted mb-0"><?= e($news['summary'] ?? '') ?></p>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
            <?php if (empty($items)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        Henüz haber eklenmemiş.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

