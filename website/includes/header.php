<?php

require_once __DIR__ . '/functions.php';

$siteTitle   = get_setting('site_title', 'Flexion Industrial');
$topbarText  = get_setting('topbar_text', 'Industrial rubber and cable solutions');
$logoPath    = get_setting('logo_path', '');
$menu        = get_main_menu();
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
                    <img src="<?= e($logoPath) ?>" alt="<?= e($siteTitle) ?>" height="36" class="me-2">
                <?php endif; ?>
                <span class="fx-header-logo">FLEXION</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto fx-main-nav">
                    <?php foreach ($menu as $item): ?>
                        <?php if (!empty($item['children'])): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="<?= e($item['url']) ?>" data-bs-toggle="dropdown">
                                    <?= e($item['title']) ?>
                                </a>
                                <ul class="dropdown-menu">
                                    <?php foreach ($item['children'] as $child): ?>
                                        <li>
                                            <a class="dropdown-item" href="<?= e($child['url']) ?>"><?= e($child['title']) ?></a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= e($item['url']) ?>"><?= e($item['title']) ?></a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
                <form action="search.php" method="get" class="d-flex ms-lg-3 mt-2 mt-lg-0">
                    <input type="search" name="q" class="form-control form-control-sm" placeholder="Ürün ara...">
                    <button class="btn btn-sm btn-outline-secondary ms-1" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </form>
            </div>
        </div>
    </nav>
</header>
<main>

