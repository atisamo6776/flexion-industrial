<?php

require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/functions.php';

$siteTitle   = t('site_title', get_setting('site_title', 'Flexion Industrial'));
$topbarText  = t('topbar_text', get_setting('topbar_text', 'Industrial rubber and cable solutions'));
$logoPath    = get_setting('logo_path', '');
$logoHeight  = max(20, min(120, (int) get_setting('logo_height', '36')));
$menu        = get_main_menu();

// Products menü öğesine DB'den gelen kategorileri otomatik enjekte et
$_menuCats = _get_categories_for_menu();
if (!empty($_menuCats)) {
    $categoriesListSlugs = ['categories', 'kategorien', 'categorie'];
    foreach ($menu as &$_mi) {
        $rawUrl = ltrim(trim(parse_url($_mi['url'] ?? '#', PHP_URL_PATH) ?: '#'), '/');
        // Dil prefix'ini soy
        foreach (['de', 'it', 'fr'] as $_lp) {
            if (str_starts_with($rawUrl, $_lp . '/')) {
                $rawUrl = substr($rawUrl, strlen($_lp) + 1);
                break;
            }
        }
        if (in_array($rawUrl, $categoriesListSlugs, true)) {
            $_mi['children'] = $_menuCats;
            break;
        }
    }
    unset($_mi);
}

// ── Aktif sayfa tespiti ──────────────────────────────────────────────────────
$_navFile  = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: 'index.php');
$_navQuery = $_SERVER['QUERY_STRING'] ?? '';

function nav_is_active(string $url): bool {
    global $_navFile, $_navQuery;
    if ($url === '#' || $url === '' || $url === '/') return false;
    $p       = parse_url($url);
    $file    = preg_replace('/\.php$/', '', basename($p['path'] ?? ''));
    $query   = $p['query'] ?? '';
    $reqFile = preg_replace('/\.php$/', '', $_navFile);
    if ($file !== $reqFile) return false;
    if ($query !== '' && $query !== $_navQuery) return false;
    return true;
}

$_langLabels = ['en' => 'English', 'de' => 'Deutsch', 'it' => 'Italiano', 'fr' => 'Français'];
$_langFlags  = ['en' => '🇬🇧', 'de' => '🇩🇪', 'it' => '🇮🇹', 'fr' => '🇫🇷'];
?>
<!DOCTYPE html>
<html lang="<?= e(CURRENT_LANG) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($siteTitle) ?></title>
    <meta name="description" content="<?= e(t('meta_description', get_setting('meta_description', 'Flexion industrial hose and cable solutions'))) ?>">
    <!-- Open Graph / Social sharing -->
    <meta property="og:type"        content="website">
    <meta property="og:title"       content="<?= e($siteTitle) ?>">
    <meta property="og:description" content="<?= e(t('meta_description', get_setting('meta_description', 'Flexion industrial hose and cable solutions'))) ?>">
    <meta property="og:url"         content="<?= e((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/')) ?>">
    <?php $ogImage = get_setting('og_image', ''); if ($ogImage): ?>
    <meta property="og:image"       content="<?= e($ogImage) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/main.css')) ?>">
</head>
<body>
<!-- Topbar -->
<div class="fx-topbar py-1">
    <div class="container d-flex justify-content-between align-items-center">
        <span class="text-muted"><?= e($topbarText) ?></span>
        <span class="text-muted small d-none d-md-inline">
            <i class="bi bi-telephone me-1"></i><?= e(get_setting('contact_phone', '+90 ... ... .. ..')) ?>
        </span>
    </div>
</div>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg bg-white border-bottom fx-sticky-nav">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="<?= e(home_url()) ?>">
            <?php if ($logoPath): ?>
                <img src="<?= e(asset_url($logoPath)) ?>" alt="<?= e($siteTitle) ?>" height="<?= $logoHeight ?>" class="me-2">
            <?php endif; ?>
            <?php if (get_setting('show_header_title', '1') === '1'): ?>
            <span class="fx-header-logo">FLEXION</span>
            <?php endif; ?>
        </a>

        <!-- Burger: mobilde tam ekran overlay'i açar -->
        <button class="navbar-toggler" type="button" id="fxBurger" aria-label="<?= e(t('nav_open_menu', 'Open menu')) ?>" aria-expanded="false" aria-controls="fxMobileOverlay">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Desktop navbar collapse -->
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto fx-main-nav">
                <?php foreach ($menu as $item): ?>
                    <?php
                    $itemHref = page_clean_url($item['url']);
                    if ($itemHref === $item['url']) { $itemHref = localized_url($item['url']); }
                    $selfActive   = nav_is_active($itemHref);
                    $childActive  = false;
                    if (!empty($item['children'])) {
                        foreach ($item['children'] as $ch) {
                            $chHref = page_clean_url($ch['url']);
                            if ($chHref === $ch['url']) { $chHref = localized_url($ch['url']); }
                            if (nav_is_active($chHref)) { $childActive = true; break; }
                        }
                    }
                    $isActive = $selfActive || $childActive;
                    ?>
                    <?php if (!empty($item['children'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle<?= $isActive ? ' active' : '' ?>"
                               href="<?= e($itemHref) ?>"
                               data-bs-toggle="dropdown"
                               data-bs-auto-close="true"
                               aria-expanded="false">
                                <?= e($item['title']) ?>
                            </a>
                            <?php $childCount = count($item['children']); ?>
                            <div class="dropdown-menu fx-dd<?= $childCount > 3 ? ' fx-dd--wide' : '' ?>">
                                <div class="container">
                                    <?php foreach ($item['children'] as $child): ?>
                                        <?php $childHref = page_clean_url($child['url']); if ($childHref === $child['url']) { $childHref = localized_url($child['url']); } ?>
                                        <a class="dropdown-item<?= nav_is_active($childHref) ? ' active' : '' ?>"
                                           href="<?= e($childHref) ?>">
                                            <?= e($child['title']) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $isActive ? ' active' : '' ?>"
                               href="<?= e($itemHref) ?>">
                                <?= e($item['title']) ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>

            <!-- Arama formu -->
            <form action="search" method="get" class="d-flex ms-lg-3 mt-2 mt-lg-0">
                <input type="search" name="q" class="form-control form-control-sm" placeholder="<?= e(t('search_placeholder', 'Search products...')) ?>">
                <button class="btn btn-sm btn-primary ms-1" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </form>

            <!-- Dil seçici -->
            <div class="fx-lang-dropdown ms-2" id="fxLangDropdown">
                <button class="fx-lang-btn" id="fxLangBtn" aria-haspopup="true" aria-expanded="false" type="button">
                    <?= strtoupper(CURRENT_LANG) ?>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="fx-lang-menu" id="fxLangMenu" role="menu">
                    <?php foreach (SUPPORTED_LANGS as $_l): ?>
                        <a href="<?= e(smart_lang_switch_url($_l)) ?>"
                           class="<?= $_l === CURRENT_LANG ? 'active' : '' ?>"
                           role="menuitem"
                           <?php if ($_l !== CURRENT_LANG): ?>
                           onclick="document.cookie='fx_lang=<?= $_l ?>;path=/;max-age=31536000';"
                           <?php endif; ?>>
                            <?= e($_langLabels[$_l] ?? strtoupper($_l)) ?>
                            <?php if ($_l === CURRENT_LANG): ?><i class="bi bi-check ms-auto"></i><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div><!-- /navbar-collapse -->
    </div>
</nav>

<!-- =====================================================
     Mobil Tam Ekran Menü Overlay (< 992px)
===================================================== -->
<div class="fx-mobile-overlay" id="fxMobileOverlay" aria-hidden="true" role="dialog" aria-label="Navigasyon menüsü">
    <!-- Başlık: logo + kapat butonu -->
    <div class="fx-mobile-overlay-head">
        <a href="<?= e(home_url()) ?>" class="text-decoration-none d-flex align-items-center gap-2" onclick="closeMobileMenu()">
            <?php if ($logoPath): ?>
                <img src="<?= e(asset_url($logoPath)) ?>" alt="<?= e($siteTitle) ?>" height="32" style="filter:brightness(0) invert(1);">
            <?php endif; ?>
            <?php if (get_setting('show_header_title', '1') === '1'): ?>
            <span class="fw-bold text-white fs-5 letter-spacing-1">FLEXION</span>
            <?php endif; ?>
        </a>
        <button class="fx-mobile-close-btn" id="fxMobileClose" aria-label="<?= e(t('nav_close_menu', 'Close menu')) ?>" type="button">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <!-- Menü linkleri -->
    <ul class="fx-mobile-nav list-unstyled mb-0">
        <?php foreach ($menu as $_mItem): ?>
            <?php if (!empty($_mItem['children'])): ?>
                <li class="fx-mob-item fx-mob-has-children">
                    <div class="fx-mob-item-row">
                        <a href="<?= e(localized_url($_mItem['url'])) ?>" class="fx-mob-link" onclick="closeMobileMenu()">
                            <?= e($_mItem['title']) ?>
                        </a>
                        <button type="button" class="fx-mob-toggle" aria-label="Alt menüyü aç/kapat">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                    <ul class="fx-mob-children list-unstyled mb-0">
                        <?php foreach ($_mItem['children'] as $_mChild): ?>
                            <li>
                                <a href="<?= e(localized_url($_mChild['url'])) ?>" class="fx-mob-child-link" onclick="closeMobileMenu()">
                                    <?= e($_mChild['title']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php else: ?>
                <li class="fx-mob-item">
                    <div class="fx-mob-item-row">
                        <a href="<?= e(localized_url($_mItem['url'])) ?>" class="fx-mob-link" onclick="closeMobileMenu()">
                            <?= e($_mItem['title']) ?>
                        </a>
                    </div>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>

    <!-- Dil seçimi (mobil overlay içinde) -->
    <div class="px-4 py-3 border-top" style="border-color:rgba(255,255,255,.08)!important; flex-shrink:0;">
        <p class="small text-secondary mb-2 text-uppercase fw-semibold" style="font-size:.7rem;"><?= e(t('nav_language', 'Language')) ?></p>
        <div class="d-flex gap-2 flex-wrap">
            <?php foreach (SUPPORTED_LANGS as $_ml): ?>
                <a href="<?= e(smart_lang_switch_url($_ml)) ?>"
                   class="btn btn-sm <?= $_ml === CURRENT_LANG ? 'btn-primary' : 'btn-outline-secondary' ?>"
                   style="font-size:.8rem;"
                   onclick="document.cookie='fx_lang=<?= $_ml ?>;path=/;max-age=31536000';">
                    <?= strtoupper($_ml) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div><!-- /fx-mobile-overlay -->

<script>
// Mobil menü aç/kapat
function openMobileMenu() {
    var overlay = document.getElementById('fxMobileOverlay');
    var burger  = document.getElementById('fxBurger');
    if (!overlay) return;
    overlay.classList.add('fx-mob-open');
    overlay.setAttribute('aria-hidden', 'false');
    if (burger) burger.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
}
function closeMobileMenu() {
    var overlay = document.getElementById('fxMobileOverlay');
    var burger  = document.getElementById('fxBurger');
    if (!overlay) return;
    overlay.classList.remove('fx-mob-open');
    overlay.setAttribute('aria-hidden', 'true');
    if (burger) burger.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
}
(function () {
    var burger = document.getElementById('fxBurger');
    var close  = document.getElementById('fxMobileClose');
    if (burger) burger.addEventListener('click', openMobileMenu);
    if (close)  close.addEventListener('click', closeMobileMenu);

    // Alt menü + butonu
    document.querySelectorAll('.fx-mob-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var item = this.closest('.fx-mob-has-children');
            var icon = this.querySelector('i');
            var expanded = item.classList.toggle('fx-mob-expanded');
            if (icon) {
                icon.className = expanded ? 'bi bi-dash-lg' : 'bi bi-plus-lg';
            }
        });
    });

    // ESC ile kapat
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeMobileMenu();
    });
}());

// Dil seçici dropdown (desktop)
(function () {
    var dd  = document.getElementById('fxLangDropdown');
    var btn = document.getElementById('fxLangBtn');
    if (!dd || !btn) return;
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = dd.classList.toggle('fx-lang-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function () {
        dd.classList.remove('fx-lang-open');
        btn.setAttribute('aria-expanded', 'false');
    });
}());
</script>
<main>
