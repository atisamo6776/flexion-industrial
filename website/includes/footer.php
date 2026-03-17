<?php

require_once __DIR__ . '/functions.php';

$companyName = get_setting('company_name', 'Flexion Industrial');
$address     = get_setting('company_address', '');
$email       = get_setting('contact_email', '');
$phone       = get_setting('contact_phone', '');
$linkedin    = get_setting('social_linkedin', '');
$youtube     = get_setting('social_youtube', '');
$logoPath    = get_setting('logo_path', '');
$siteTitle   = t('site_title', get_setting('site_title', 'Flexion Industrial'));
$logoHeight  = max(20, min(120, (int) get_setting('logo_height', '36')));

// Footer linkleri + dinamik kategori linkleri
$footerCols = [];
$lang = defined('CURRENT_LANG') ? CURRENT_LANG : 'en';

// 1) Company / Information / Products sütunları DB'den (varsa)
$footerLinksRaw = [];
try {
    $pdo = db();
    $stmt = $pdo->query('SELECT * FROM footer_links WHERE is_active = 1 ORDER BY column_key ASC, sort_order ASC, id ASC');
    $footerLinksRaw = $stmt->fetchAll();
} catch (Throwable $e) {
    $footerLinksRaw = [];
}

// footer_link_translations çevirilerini al
$_footerLinkTrans = [];
if (!empty($footerLinksRaw)) {
    try {
        $ids  = array_column($footerLinksRaw, 'id');
        $in   = implode(',', array_fill(0, count($ids), '?'));
        $stmt2 = $pdo->prepare(
            "SELECT footer_link_id, language, title
             FROM footer_link_translations
             WHERE footer_link_id IN ({$in}) AND language IN (?, 'en')"
        );
        $stmt2->execute(array_merge($ids, [$lang]));
        foreach ($stmt2->fetchAll() as $flt) {
            $_footerLinkTrans[$flt['footer_link_id']][$flt['language']] = $flt['title'];
        }
    } catch (Throwable $e) {
        $_footerLinkTrans = [];
    }
}

$colLabelMap = [
    'company'     => t('footer_col_company', 'Company'),
    'products'    => t('footer_col_products', 'Products'),
    'information' => t('footer_col_information', 'Information'),
    'contact'     => t('footer_col_contact', 'Contact'),
    'categories'  => t('footer_col_categories', 'Categories'),
];

foreach ($footerLinksRaw as $fl) {
    $colKey = $fl['column_key'];
    if (!isset($footerCols[$colKey])) {
        $footerCols[$colKey] = ['label' => $colLabelMap[$colKey] ?? ucfirst($colKey), 'links' => []];
    }
    $flTitle = $_footerLinkTrans[$fl['id']][$lang]
            ?? $_footerLinkTrans[$fl['id']]['en']
            ?? $fl['title'];
    $fl['title'] = $flTitle;
    $footerCols[$colKey]['links'][] = $fl;
}

// 2) Categories sütunu: aktif dilde dinamik (üst seviye ilk 6)
try {
    $pdo3 = db();
    $cats = $pdo3->query("SELECT id, name, slug FROM categories WHERE is_active = 1 AND (parent_id IS NULL OR parent_id = 0) ORDER BY sort_order ASC, name ASC LIMIT 6")->fetchAll();
    if ($cats) {
        $ids = array_column($cats, 'id');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $tStmt = $pdo3->prepare("SELECT category_id, name, slug FROM category_translations WHERE category_id IN ($in) AND language = ?");
        $tStmt->execute(array_merge($ids, [$lang]));
        $trMap = [];
        foreach ($tStmt->fetchAll() as $tr) $trMap[$tr['category_id']] = $tr;
        $prefix = function_exists('lang_prefix') ? lang_prefix() : '';
        $footerCols['categories'] = $footerCols['categories'] ?? ['label' => $colLabelMap['categories'], 'links' => []];
        foreach ($cats as $cat) {
            $name = $cat['name'];
            $slug = $cat['slug'];
            if (isset($trMap[$cat['id']])) {
                if (!empty($trMap[$cat['id']]['name'])) $name = $trMap[$cat['id']]['name'];
                if (!empty($trMap[$cat['id']]['slug'])) $slug = $trMap[$cat['id']]['slug'];
            }
            $url = $prefix !== '' ? $prefix . '/' . $slug : '/' . $slug;
            $footerCols['categories']['links'][] = ['title' => $name, 'url' => $url];
        }
        // "View all categories" linki
        $footerCols['categories']['links'][] = [
            'title' => t('footer_view_all_categories', 'View all categories'),
            'url'   => function_exists('categories_list_url') ? categories_list_url() : '/categories',
        ];
    }
} catch (Throwable $e) {
    // sessiz
}
?>
</main>

<footer class="fx-footer text-light mt-5">
    <div class="container">
        <div class="row g-4 py-4">
            <!-- Company info -->
            <div class="col-md-4 col-lg-3">
                <?php if ($logoPath): ?>
                    <a href="<?= e(home_url()) ?>" class="d-inline-block mb-3">
                        <img src="<?= e(asset_url($logoPath)) ?>" alt="<?= e($siteTitle) ?>"
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

            <!-- Footer link columns -->
            <?php if (!empty($footerCols)): ?>
                <?php foreach ($footerCols as $colData): ?>
                    <div class="col-6 col-md-2 col-lg-2">
                        <h6 class="text-white mb-3 fw-semibold"><?= e($colData['label']) ?></h6>
                        <ul class="list-unstyled small">
                            <?php foreach ($colData['links'] as $fl): ?>
                                <li class="mb-1">
                                    <?php
                                    $raw = (string) ($fl['url'] ?? '#');
                                    $flHref = page_clean_url($raw);
                                    if ($flHref === $raw) { $flHref = localized_url($raw); }
                                    ?>
                                    <a href="<?= e($flHref) ?>" class="fx-footer-link"><?= e($fl['title']) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Social media -->
            <div class="col-md-3 col-lg-3 ms-lg-auto">
                <?php if ($linkedin || $youtube): ?>
                    <h6 class="text-white mb-3 fw-semibold"><?= e(t('footer_social_title', 'Social Media')) ?></h6>
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
            </div>
        </div>

        <div class="fx-footer-bottom d-flex flex-column flex-sm-row justify-content-sm-between align-items-center gap-2">
            <span class="small text-white">
                &copy; <?= date('Y') ?> <?= e($companyName) ?> &mdash; <?= e(t('footer_rights', 'All rights reserved.')) ?>
            </span>
            <span class="small text-white">
                Powered by <a href="https://kesicioglu.com" target="_blank" rel="noopener" class="fw-bold text-white text-decoration-none">Kesicioglu</a>
            </span>
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

        // Desktop'ta dropdown toggle'a tıklanınca href'e git (hover zaten menüyü açıyor)
        toggle.addEventListener('click', function(e) {
            var href = this.getAttribute('href');
            if (href && href !== '#') {
                e.stopImmediatePropagation();
                window.location.href = href;
            }
        }, true); // capture phase — Bootstrap'ten önce yakala

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
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                var el = entry.target;
                var delay = parseInt(el.dataset.delay || 0, 10);
                setTimeout(function () { el.classList.add('fx-visible'); }, delay);
                io.unobserve(el);
            }
        });
    }, { threshold: 0.12 });
    // Mobilde gecikmeyi sınırla (maks 150ms) — masaüstünde 300ms
    var maxDelay = window.innerWidth < 768 ? 150 : 300;
    els.forEach(function (el, i) {
        if (!el.dataset.delay) el.dataset.delay = Math.min(i * 60, maxDelay);
        io.observe(el);
    });
}());
</script>
<!-- ========== Çerez Onay Banner'ı ========== -->
<div id="fx-cookie-banner" class="fx-cookie-banner" role="dialog" aria-label="Cookie notice" aria-live="polite">
    <div class="fx-cookie-inner">
        <p class="fx-cookie-text small mb-0">
            <?= e(t('cookie_message', 'This website uses cookies to provide the best experience.')) ?>
            <a href="<?= e(function_exists('page_url') ? page_url('privacy-policy') : '/privacy-policy') ?>" class="fw-semibold text-white"><?= e(t('cookie_policy_link', 'Privacy Policy')) ?></a>
        </p>
        <div class="fx-cookie-actions">
            <button id="fx-cookie-accept" class="btn btn-sm btn-light fw-semibold px-3"><?= e(t('cookie_accept', 'Accept')) ?></button>
            <button id="fx-cookie-reject" class="btn btn-sm btn-outline-light px-3"><?= e(t('cookie_reject', 'Decline')) ?></button>
        </div>
    </div>
</div>
<script>
(function () {
    var banner = document.getElementById('fx-cookie-banner');
    if (!banner) return;
    if (localStorage.getItem('fx_cookie_consent') !== null) return;
    setTimeout(function () { banner.classList.add('fx-cookie-show'); }, 800);
    function dismiss(accept) {
        localStorage.setItem('fx_cookie_consent', accept ? '1' : '0');
        banner.classList.remove('fx-cookie-show');
        setTimeout(function () { banner.style.display = 'none'; }, 350);
    }
    document.getElementById('fx-cookie-accept').addEventListener('click', function () { dismiss(true); });
    document.getElementById('fx-cookie-reject').addEventListener('click', function () { dismiss(false); });
}());
</script>
</body>
</html>
