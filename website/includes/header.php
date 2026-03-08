<?php

require_once __DIR__ . '/functions.php';

$siteTitle   = get_setting('site_title', 'Flexion Industrial');
$topbarText  = get_setting('topbar_text', 'Industrial rubber and cable solutions');
$logoPath    = get_setting('logo_path', '');
$logoHeight  = max(20, min(120, (int) get_setting('logo_height', '36')));
$menu        = get_main_menu();

// ── Aktif sayfa tespiti ──────────────────────────────────────────────────────
$_navFile  = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: 'index.php');
$_navQuery = $_SERVER['QUERY_STRING'] ?? '';

function nav_is_active(string $url): bool {
    global $_navFile, $_navQuery;
    if ($url === '#' || $url === '' || $url === '/') return false;
    $p     = parse_url($url);
    $file  = basename($p['path'] ?? '');
    $query = $p['query'] ?? '';
    if ($file !== $_navFile) return false;
    // Eğer item URL'de query varsa tam eşleşme gerekir, yoksa sadece dosya adı yeter
    if ($query !== '' && $query !== $_navQuery) return false;
    return true;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($siteTitle) ?></title>
    <meta name="description" content="<?= e(get_setting('meta_description', 'Flexion industrial hose and cable solutions')) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
<header>
    <div class="fx-topbar py-1">
        <div class="container d-flex justify-content-between align-items-center">
            <span class="text-muted"><?= e($topbarText) ?></span>
            <span class="text-muted small">
                <i class="bi bi-telephone me-1"></i><?= e(get_setting('contact_phone', '+90 ... ... .. ..')) ?>
            </span>
        </div>
    </div>
    <nav class="navbar navbar-expand-lg bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <?php if ($logoPath): ?>
                    <img src="<?= e($logoPath) ?>" alt="<?= e($siteTitle) ?>" height="<?= $logoHeight ?>" class="me-2">
                <?php endif; ?>
                <?php if (get_setting('show_header_title', '1') === '1'): ?>
                <span class="fx-header-logo">FLEXION</span>
                <?php endif; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto fx-main-nav">
                    <?php foreach ($menu as $item): ?>
                        <?php
                        $selfActive   = nav_is_active($item['url']);
                        $childActive  = false;
                        if (!empty($item['children'])) {
                            foreach ($item['children'] as $ch) {
                                if (nav_is_active($ch['url'])) { $childActive = true; break; }
                            }
                        }
                        $isActive = $selfActive || $childActive;
                        ?>
                        <?php if (!empty($item['children'])): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle<?= $isActive ? ' active' : '' ?>"
                                   href="<?= e($item['url']) ?>"
                                   data-bs-toggle="dropdown"
                                   data-bs-auto-close="true"
                                   aria-expanded="false">
                                    <?= e($item['title']) ?>
                                </a>
                                <?php $childCount = count($item['children']); ?>
                                <div class="dropdown-menu fx-dd<?= $childCount > 3 ? ' fx-dd--wide' : '' ?>">
                                    <?php foreach ($item['children'] as $child): ?>
                                        <a class="dropdown-item<?= nav_is_active($child['url']) ? ' active' : '' ?>"
                                           href="<?= e($child['url']) ?>">
                                            <?= e($child['title']) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link<?= $isActive ? ' active' : '' ?>"
                                   href="<?= e($item['url']) ?>">
                                    <?= e($item['title']) ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
                <form action="search.php" method="get" class="d-flex ms-lg-3 mt-2 mt-lg-0">
                    <input type="search" name="q" class="form-control form-control-sm" placeholder="Ürün ara...">
                    <button class="btn btn-sm btn-primary ms-1" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </form>
            </div>
        </div>
    </nav>
</header>
<main>
