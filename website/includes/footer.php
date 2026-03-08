<?php

require_once __DIR__ . '/functions.php';

$companyName = get_setting('company_name', 'Flexion Industrial');
$address     = get_setting('company_address', 'Adres bilgisi');
$email       = get_setting('contact_email', 'info@example.com');
$phone       = get_setting('contact_phone', '+90 ... ... .. ..');
$footerText  = get_setting('footer_text', 'All rights reserved.');
$linkedin    = get_setting('social_linkedin', '');
$youtube     = get_setting('social_youtube', '');
$logoPath    = get_setting('logo_path', '');
$siteTitle   = get_setting('site_title', 'Flexion Industrial');
$logoHeight  = max(20, min(120, (int) get_setting('logo_height', '36')));

// Footer linkleri - column_key'e göre grupla
$footerLinksRaw = [];
try {
    $pdo = db();
    $stmt = $pdo->query('SELECT * FROM footer_links WHERE is_active = 1 ORDER BY column_key ASC, sort_order ASC, id ASC');
    $footerLinksRaw = $stmt->fetchAll();
} catch (Exception $e) {
    // footer_links tablosu henüz yoksa sessizce geç
}

$footerCols = [];
foreach ($footerLinksRaw as $fl) {
    $key = $fl['column_key'];
    if (!isset($footerCols[$key])) {
        $footerCols[$key] = ['label' => $fl['column_label'] ?: $key, 'links' => []];
    }
    $footerCols[$key]['links'][] = $fl;
}
?>
</main>

<footer class="fx-footer text-light mt-5">
    <div class="container">
        <div class="row g-4 py-4">
            <!-- Şirket bilgisi -->
            <div class="col-md-4 col-lg-3">
                <?php if ($logoPath): ?>
                    <a href="/" class="d-inline-block mb-3">
                        <img src="<?= e($logoPath) ?>" alt="<?= e($siteTitle) ?>"
                             height="<?= $logoHeight ?>" style="max-height:<?= $logoHeight ?>px;filter:brightness(0) invert(1);">
                    </a>
                <?php else: ?>
                    <div class="fw-bold text-white fs-5 mb-3"><?= e($companyName) ?></div>
                <?php endif; ?>
                <p class="small mb-1"><?= e($address) ?></p>
                <?php if ($phone): ?>
                    <p class="small mb-1">
                        <i class="bi bi-telephone me-1"></i>
                        <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>" class="text-reset"><?= e($phone) ?></a>
                    </p>
                <?php endif; ?>
                <?php if ($email): ?>
                    <p class="small mb-0">
                        <i class="bi bi-envelope me-1"></i>
                        <a href="mailto:<?= e($email) ?>" class="text-reset"><?= e($email) ?></a>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Dinamik link sütunları -->
            <?php if (!empty($footerCols)): ?>
                <?php foreach ($footerCols as $colData): ?>
                    <div class="col-6 col-md-2 col-lg-2">
                        <h6 class="text-white mb-3 fw-semibold"><?= e($colData['label']) ?></h6>
                        <ul class="list-unstyled small">
                            <?php foreach ($colData['links'] as $fl): ?>
                                <li class="mb-1">
                                    <a href="<?= e($fl['url']) ?>"><?= e($fl['title']) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Varsayılan statik sütunlar (footer_links tablosu boşken) -->
                <div class="col-6 col-md-2 col-lg-2">
                    <h6 class="text-white mb-3 fw-semibold">Kurumsal</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-1"><a href="/hakkimizda">Hakkımızda</a></li>
                        <li class="mb-1"><a href="/iletisim">İletişim</a></li>
                    </ul>
                </div>
                <div class="col-6 col-md-2 col-lg-2">
                    <h6 class="text-white mb-3 fw-semibold">Ürünler</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-1"><a href="/sectors">Tüm Ürünler</a></li>
                        <li class="mb-1"><a href="/news">Haberler</a></li>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Sosyal medya / İletişim -->
            <div class="col-md-3 col-lg-3 ms-lg-auto">
                <?php if ($linkedin || $youtube): ?>
                    <h6 class="text-white mb-3 fw-semibold">Sosyal Medya</h6>
                    <div class="d-flex gap-3 mb-3">
                        <?php if ($linkedin): ?>
                            <a href="<?= e($linkedin) ?>" target="_blank" rel="noopener" title="LinkedIn">
                                <i class="bi bi-linkedin fs-4"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($youtube): ?>
                            <a href="<?= e($youtube) ?>" target="_blank" rel="noopener" title="YouTube">
                                <i class="bi bi-youtube fs-4"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <p class="small mb-0">
                    <?= e(get_setting('newsletter_text', 'Yeni ürün ve projelerden haberdar olmak için bültenimize abone olun.')) ?>
                </p>
            </div>
        </div>

        <div class="fx-footer-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="small"><?= e($footerText) ?></span>
            <span class="small text-muted">Powered by Flexion</span>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Desktop (≥992px): hover ile dropdown aç/kapat
// position:fixed menü, parent'ın dışında göründüğünden mouseleave anında tetiklenir.
// Küçük gecikme + menü üzerinde fare kalırsa iptal et.
(function () {
    if (window.innerWidth < 992) return;
    document.querySelectorAll('.fx-main-nav .dropdown').forEach(function (el) {
        var toggle = el.querySelector('[data-bs-toggle="dropdown"]');
        if (!toggle) return;
        var dd   = bootstrap.Dropdown.getOrCreateInstance(toggle);
        var menu = el.querySelector('.dropdown-menu');
        var timer = null;

        function openDD()  { clearTimeout(timer); dd.show(); }
        function closeDD() {
            clearTimeout(timer);
            timer = setTimeout(function () { dd.hide(); }, 150);
        }

        el.addEventListener('mouseenter', openDD);
        el.addEventListener('mouseleave', closeDD);

        // Menü position:fixed olduğu için parent'ın dışında; menü üzerinde
        // fareyi tutunca kapanmayı iptal et
        if (menu) {
            menu.addEventListener('mouseenter', function () { clearTimeout(timer); });
            menu.addEventListener('mouseleave', closeDD);
        }
    });
}());
</script>
<script>
// Scroll tabanlı animasyon
(function () {
    var els = document.querySelectorAll('.fx-animate');
    if (!els.length) return;
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry, i) {
            if (entry.isIntersecting) {
                var el = entry.target;
                var delay = (el.dataset.delay || 0);
                setTimeout(function () { el.classList.add('fx-visible'); }, delay);
                io.unobserve(el);
            }
        });
    }, { threshold: 0.12 });
    els.forEach(function (el, i) {
        if (!el.dataset.delay) el.dataset.delay = i * 60;
        io.observe(el);
    });
}());
</script>
</body>
</html>
