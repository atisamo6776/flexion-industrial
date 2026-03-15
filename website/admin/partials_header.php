<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_login();
require_password_change_if_needed();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f4f5f7;
        }
        .sidebar {
            min-height: 100vh;
            background: #111827;
            color: #e5e7eb;
        }
        .sidebar a {
            color: #9ca3af;
            text-decoration: none;
        }
        .sidebar a.active,
        .sidebar a:hover {
            color: #ffffff;
        }
        .sidebar .nav-link.active {
            background: #1f2937;
            border-radius: .5rem;
        }
        .brand-admin {
            font-weight: 700;
            letter-spacing: .08em;
            color: #f87171;
        }
        .topbar {
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-lg-2 d-md-block sidebar px-3 py-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <div class="brand-admin small">FLEXION</div>
                    <div class="small text-muted">Admin Panel</div>
                </div>
            </div>
            <nav class="nav flex-column gap-1">
                <a href="index.php" class="nav-link py-2 px-2 <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                </a>
                <a href="homepage.php" class="nav-link py-2 px-2 <?= basename($_SERVER['PHP_SELF']) === 'homepage.php' ? 'active' : '' ?>">
                    <i class="bi bi-layout-text-window-reverse me-2"></i>Ana Sayfa Blokları
                </a>
                <a href="menu.php" class="nav-link py-2 px-2 <?= basename($_SERVER['PHP_SELF']) === 'menu.php' ? 'active' : '' ?>">
                    <i class="bi bi-menu-button-wide me-2"></i>Menü
                </a>
                <a href="categories.php" class="nav-link py-2 px-2 <?= basename($_SERVER['PHP_SELF']) === 'categories.php' ? 'active' : '' ?>">
                    <i class="bi bi-grid-3x3-gap me-2"></i>Kategoriler
                </a>
                <a href="products.php" class="nav-link py-2 px-2 <?= basename($_SERVER['PHP_SELF']) === 'products.php' ? 'active' : '' ?>">
                    <i class="bi bi-box-seam me-2"></i>Ürünler
                </a>
                <a href="news.php" class="nav-link py-2 px-2 <?= basename($_SERVER['PHP_SELF']) === 'news.php' ? 'active' : '' ?>">
                    <i class="bi bi-newspaper me-2"></i>Haberler / Insights
                </a>
                <a href="pages.php" class="nav-link py-2 px-2 <?= basename($_SERVER['PHP_SELF']) === 'pages.php' ? 'active' : '' ?>">
                    <i class="bi bi-file-text me-2"></i>Kurumsal Sayfalar
                </a>
                <a href="header-footer.php" class="nav-link py-2 px-2 <?= basename($_SERVER['PHP_SELF']) === 'header-footer.php' ? 'active' : '' ?>">
                    <i class="bi bi-layout-three-columns me-2"></i>Header / Footer
                </a>
                <a href="translations.php" class="nav-link py-2 px-2 <?= basename($_SERVER['PHP_SELF']) === 'translations.php' ? 'active' : '' ?>">
                    <i class="bi bi-translate me-2"></i>Site Çevirileri
                </a>
                <a href="settings.php" class="nav-link py-2 px-2 <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>">
                    <i class="bi bi-gear me-2"></i>Genel Ayarlar
                </a>
                <a href="profile.php" class="nav-link py-2 px-2 <?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>">
                    <i class="bi bi-person-lock me-2"></i>Profil / Şifre
                </a>
                <a href="submissions.php" class="nav-link py-2 px-2 <?= basename($_SERVER['PHP_SELF']) === 'submissions.php' ? 'active' : '' ?>">
                    <i class="bi bi-chat-left-dots me-2"></i>Gelen Mesajlar
                    <?php
                    try {
                        $pdo2 = db();
                        $unread = (int)$pdo2->query('SELECT COUNT(*) FROM contact_submissions WHERE is_read = 0')->fetchColumn();
                        if ($unread > 0) echo '<span class="badge bg-danger ms-1 small">' . $unread . '</span>';
                    } catch (Throwable $e) {}
                    ?>
                </a>
                <hr class="border-secondary my-1">
                <a href="health.php" class="nav-link py-2 px-2 <?= basename($_SERVER['PHP_SELF']) === 'health.php' ? 'active' : '' ?>">
                    <i class="bi bi-heart-pulse me-2"></i>Sağlık Kontrolü
                </a>
                <a href="migrate.php" class="nav-link py-2 px-2 <?= basename($_SERVER['PHP_SELF']) === 'migrate.php' ? 'active' : '' ?>">
                    <i class="bi bi-database-gear me-2"></i>DB Migrasyonu
                </a>
                <a href="error_view.php" class="nav-link py-2 px-2 <?= basename($_SERVER['PHP_SELF']) === 'error_view.php' ? 'active' : '' ?>">
                    <i class="bi bi-bug me-2"></i>Hata Logu
                </a>
                <a href="logout.php" class="nav-link py-2 px-2">
                    <i class="bi bi-box-arrow-right me-2"></i>Çıkış
                </a>
            </nav>
        </aside>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 px-3">
            <header class="topbar d-flex justify-content-between align-items-center py-3 mb-3">
                <div>
                    <h1 class="h5 mb-0">Kontrol Paneli</h1>
                    <p class="text-muted small mb-0">Flexion web sitesindeki tüm içerikleri buradan yönet.</p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <a href="../index.php" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Siteyi Görüntüle
                    </a>
                    <span class="small text-muted">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= e($_SESSION['admin_username'] ?? 'admin') ?>
                    </span>
                    <a href="profile.php" class="btn btn-sm btn-outline-secondary">Profil</a>
                </div>
            </header>

