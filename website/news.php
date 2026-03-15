<?php

require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/header.php';

$pdo  = db();
$slug = $_GET['slug'] ?? null;

if ($slug) {
    $article = null;
    try {
        // Önce çeviri tablosunda slug ara
        $stmt = $pdo->prepare(
            'SELECT n.* FROM news n
             JOIN news_translations nt ON nt.news_id = n.id
             WHERE nt.slug = :slug AND nt.language = :lang AND n.is_active = 1 LIMIT 1'
        );
        $stmt->execute([':slug' => $slug, ':lang' => CURRENT_LANG]);
        $article = $stmt->fetch() ?: null;
        if (!$article) {
            $stmt2 = $pdo->prepare('SELECT * FROM news WHERE slug = :slug AND is_active = 1 LIMIT 1');
            $stmt2->execute([':slug' => $slug]);
            $article = $stmt2->fetch() ?: null;
        }
        if ($article) {
            $newsTr = get_translation('news_translations', 'news_id', (int)$article['id']);
            if ($newsTr) {
                $article['title']   = $newsTr['title']   ?: $article['title'];
                $article['summary'] = $newsTr['summary'] ?? $article['summary'];
                $article['content'] = $newsTr['content'] ?? $article['content'];
            }
        }
    } catch (Throwable $e) {
        error_log('[flexion] news article query failed: ' . $e->getMessage());
        $article = null;
    }

    if (!$article) {
        http_response_code(404);
        ?>
        <div class="container py-5">
            <h1 class="h3 mb-3">Haber bulunamadı</h1>
            <p class="text-muted">Aradığınız içerik sistemde yer almıyor veya pasif durumda.</p>
            <a href="news" class="btn btn-outline-secondary btn-sm">Tüm haberlere dön</a>
        </div>
        <?php
        require_once __DIR__ . '/includes/footer.php';
        exit;
    }
    ?>
    <?php render_news_banner(); ?>

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
                        <?= sanitize_html($article['content']) ?>
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
                        } catch (Throwable $e) {
                            error_log('[flexion] side news query failed: ' . $e->getMessage());
                        }
                        foreach ($sideNews as $n): ?>
                            <li class="mb-2">
                                <a href="news?slug=<?= e($n['slug']) ?>" class="text-decoration-none">
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
} catch (Throwable $e) {
    error_log('[flexion] news list query failed: ' . $e->getMessage());
}
?>

<?php render_news_banner(); ?>

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
                <div class="col-md-4 fx-animate">
                    <a href="news?slug=<?= e($news['slug']) ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                <?php if (!empty($news['image'])): ?>
                    <img src="<?= e($news['image']) ?>" class="card-img-top fx-card-img" alt="<?= e($news['title']) ?>" loading="lazy">
                <?php else: ?>
                            <div class="fx-card-img bg-light d-flex align-items-center justify-content-center text-muted">
                                <i class="bi bi-newspaper fs-2"></i>
                            </div>
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

