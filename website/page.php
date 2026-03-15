<?php
// ════════════════════════════════════════════════════════════════════
//  FORM İŞLEME — HTML output'tan ÖNCE (PRG pattern)
// ════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = db();

// Slug tespiti (GET veya temiz URL path'inden)
$slug = trim($_GET['slug'] ?? '');
if (!$slug) {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $slug = trim(basename($path), '/');
    if (pathinfo($slug, PATHINFO_EXTENSION)) {
        $slug = '';
    }
}

// Sayfa verisini erkenden çek (form işleme ve redirect için gerekli)
$page = null;
if ($slug) {
    try {
        // Çeviri tablosunda slug ara
        $stmtTr = $pdo->prepare(
            'SELECT p.* FROM pages p
             JOIN page_translations pt ON pt.page_id = p.id
             WHERE pt.slug = :slug AND pt.language = :lang AND p.is_active = 1 LIMIT 1'
        );
        $stmtTr->execute([':slug' => $slug, ':lang' => CURRENT_LANG]);
        $page = $stmtTr->fetch() ?: null;
        if (!$page) {
            $stmt = $pdo->prepare('SELECT * FROM pages WHERE slug = :slug AND is_active = 1 LIMIT 1');
            $stmt->execute([':slug' => $slug]);
            $page = $stmt->fetch() ?: null;
        }
        if ($page) {
            $pageTr = get_translation('page_translations', 'page_id', (int)$page['id']);
            if ($pageTr) {
                $page['title']            = $pageTr['title']            ?: $page['title'];
                $page['content']          = $pageTr['content']          ?? $page['content'];
                $page['meta_description'] = $pageTr['meta_description'] ?? $page['meta_description'];
                $page['banner_title']     = $pageTr['banner_title']     ?? $page['banner_title'];
            }
        }
    } catch (Throwable $e) {
        $page = null;
    }
}

// İletişim formu PRG — başarı bayrağı GET ile taşınır
$formSent  = isset($_GET['sent']) && $_GET['sent'] === '1';
$formError = null;

if ($page && $slug === 'iletisim'
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && !empty($_POST['contact_submit'])
) {
    // Honeypot: bot doldurursa sessizce geç
    if (!empty($_POST['website_url'])) {
        header('Location: /iletisim?sent=1');
        exit;
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $formError = 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyin ve tekrar deneyin.';
    } else {
        $cName    = trim($_POST['contact_name']    ?? '');
        $cSurname = trim($_POST['contact_surname'] ?? '');
        $cEmail2  = trim($_POST['contact_email']   ?? '');
        $cPhone2  = trim($_POST['contact_phone']   ?? '');
        $cCompany = trim($_POST['contact_company'] ?? '');
        $cMsg     = trim($_POST['contact_message'] ?? '');

        if (!$cName || !$cEmail2 || !$cMsg) {
            $formError = 'Lütfen zorunlu alanları doldurun (Ad, E-posta, Mesaj).';
        } elseif (!filter_var($cEmail2, FILTER_VALIDATE_EMAIL)) {
            $formError = 'Geçerli bir e-posta adresi girin.';
        } else {
            $fullName = trim($cName . ' ' . $cSurname);
            try {
                $ins = $pdo->prepare('INSERT INTO contact_submissions
                    (type,name,email,phone,company,message)
                    VALUES(:type,:name,:email,:phone,:company,:msg)');
                $ins->execute([
                    ':type'    => 'contact',
                    ':name'    => $fullName,
                    ':email'   => $cEmail2,
                    ':phone'   => $cPhone2  ?: null,
                    ':company' => $cCompany ?: null,
                    ':msg'     => $cMsg,
                ]);
            } catch (Throwable $e2) {
                error_log('[flexion] contact form DB insert failed: ' . $e2->getMessage());
            }

            $toMail = get_setting('contact_email', '');
            if ($toMail) {
                $subj = 'Flexion İletişim: ' . $fullName;
                $body = "Ad: $fullName\nE-posta: $cEmail2\nTelefon: $cPhone2\nŞirket: $cCompany\n\nMesaj:\n$cMsg";
                send_notification_mail($toMail, $subj, $body);
            }

            // PRG: Yönlendir → double-submit ve 500 engeli
            header('Location: page.php?slug=' . urlencode($slug) . '&sent=1');
            exit;
        }
    }
}

// ════ HTML çıktısı başlıyor ════════════════════════════════════════
require_once __DIR__ . '/includes/header.php';

// 404 — Slug yok
if (!$slug) {
    http_response_code(404);
    echo '<div class="container py-5"><h1 class="h3">' . e(t('404_title', 'Page Not Found')) . '</h1></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// 404 — DB'de sayfa yok
if (!$page) {
    http_response_code(404);
    ?>
    <div class="container py-5">
        <h1 class="h3 mb-3"><?= e(t('404_title', 'Page Not Found')) ?></h1>
        <p class="text-muted"><?= e(t('404_desc', 'The page you are looking for does not exist or is inactive.')) ?></p>
        <a href="<?= e(home_url()) ?>" class="btn btn-outline-secondary btn-sm"><?= e(t('404_back', 'Back to homepage')) ?></a>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// ---- Banner ----
$bannerImg   = $page['banner_image'] ?? '';
$bannerTitle = !empty($page['banner_title']) ? $page['banner_title'] : $page['title'];
$bOpacity    = max(0, min(100, (int)($page['banner_opacity'] ?? 50)));
$bBlur       = max(0, min(20,  (int)($page['banner_blur'] ?? 0)));
$bTitleColor = $page['banner_title_color'] ?? '#ffffff';
$bTitleSize  = $page['banner_title_size']  ?? '2rem';
$bTitlePos   = $page['banner_title_position'] ?? 'center';

$textAlignMap   = ['left' => 'text-start', 'center' => 'text-center', 'right' => 'text-end'];
$textAlignClass = $textAlignMap[$bTitlePos] ?? 'text-center';
?>

<?php if ($bannerImg): ?>
<section class="fx-page-banner mb-0">
    <div class="fx-banner-bg" style="background-image:url('<?= e(asset_url($bannerImg)) ?>');
         filter:blur(<?= $bBlur ?>px); transform:scale(1.05);"></div>
    <div class="fx-banner-overlay" style="background:rgba(0,0,0,<?= round($bOpacity/100,2) ?>);"></div>
    <div class="fx-banner-content">
        <div class="container <?= $textAlignClass ?>">
            <h1 class="fx-banner-title"
                style="color:<?= e($bTitleColor) ?>;font-size:<?= e($bTitleSize) ?>;">
                <?= e($bannerTitle) ?>
            </h1>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="py-5">
    <div class="container">

        <?php if ($slug !== 'iletisim'): ?>
        <div class="row justify-content-center">
            <div class="col-lg-8 fx-animate">
                <?php if (!$bannerImg): ?>
                <h1 class="h2 mb-4"><?= e($page['title']) ?></h1>
                <?php endif; ?>
                <div class="page-content">
                    <?= sanitize_html($page['content']) ?>
                </div>
            </div>
        </div>
        <?php else: ?>

        <!-- ================================================
             İletişim Sayfası
        ================================================ -->
        <div class="row g-5">
            <!-- Sol: Form + bilgiler -->
            <div class="col-lg-6 fx-animate">
                <?php if (!$bannerImg): ?>
                <h1 class="h2 mb-4"><?= e($page['title']) ?></h1>
                <?php endif; ?>
                <?php if ($page['content']): ?>
                    <div class="page-content mb-4"><?= sanitize_html($page['content']) ?></div>
                <?php endif; ?>

                <!-- İletişim bilgileri -->
                <?php
                $cPhone   = get_setting('contact_phone', '');
                $cEmail   = get_setting('contact_email', '');
                $cAddress = get_setting('company_address', '') ?: get_setting('contact_address', '');
                ?>
                <div class="d-flex flex-column gap-3 mb-5">
                    <?php if ($cPhone): ?>
                    <div class="d-flex align-items-center gap-3">
                        <div class="fx-contact-icon"><i class="bi bi-telephone-fill"></i></div>
                        <div>
                            <div class="small text-muted fw-semibold text-uppercase"><?= e(t('form_phone', 'Phone')) ?></div>
                            <a href="tel:<?= e(preg_replace('/\s+/', '', $cPhone)) ?>"
                               class="text-decoration-none fw-semibold"><?= e($cPhone) ?></a>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($cEmail): ?>
                    <div class="d-flex align-items-center gap-3">
                        <div class="fx-contact-icon"><i class="bi bi-envelope-fill"></i></div>
                        <div>
                            <div class="small text-muted fw-semibold text-uppercase"><?= e(t('form_email', 'E-mail')) ?></div>
                            <a href="mailto:<?= e($cEmail) ?>"
                               class="text-decoration-none fw-semibold"><?= e($cEmail) ?></a>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($cAddress): ?>
                    <div class="d-flex align-items-center gap-3">
                        <div class="fx-contact-icon"><i class="bi bi-geo-alt-fill"></i></div>
                        <div>
                            <div class="small text-muted fw-semibold text-uppercase"><?= e(t('contact_address_label', 'Address')) ?></div>
                            <span><?= e($cAddress) ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Form -->
                <?php if ($formSent): ?>
                <div class="fx-success-anim text-center py-5">
                    <div class="mb-3">
                        <span style="font-size:3.5rem;color:#e61421;"><i class="bi bi-check-circle-fill"></i></span>
                    </div>
                    <h2 class="h4 mb-2"><?= e(t('form_success_title', 'Message received!')) ?></h2>
                    <p class="text-muted"><?= e(t('form_contact_success', 'Your message has been sent. We will contact you shortly.')) ?></p>
                    <a href="<?= e($slug) ?>" class="btn btn-outline-secondary btn-sm mt-2"><?= e(t('form_send_new', 'Send a new message')) ?></a>
                </div>
                <?php else: ?>
                <div class="fx-contact-form-wrap">
                    <h2 class="h5 mb-4"><?= e(t('contact_form_title', 'Write to Us')) ?></h2>
                    <?php if ($formError): ?>
                        <div class="alert alert-danger py-2 small"><?= e($formError) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <div style="display:none;" aria-hidden="true">
                            <input type="text" name="website_url" tabindex="-1" autocomplete="off" value="">
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label"><?= e(t('form_name', 'Name')) ?> <span class="text-danger">*</span></label>
                                <input type="text" name="contact_name" class="form-control"
                                       value="<?= e($_POST['contact_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label"><?= e(t('form_surname', 'Surname')) ?></label>
                                <input type="text" name="contact_surname" class="form-control"
                                       value="<?= e($_POST['contact_surname'] ?? '') ?>">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label"><?= e(t('form_email', 'E-mail')) ?> <span class="text-danger">*</span></label>
                                <input type="email" name="contact_email" class="form-control"
                                       value="<?= e($_POST['contact_email'] ?? '') ?>" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label"><?= e(t('form_phone', 'Phone')) ?></label>
                                <input type="tel" name="contact_phone" class="form-control"
                                       value="<?= e($_POST['contact_phone'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?= e(t('form_company', 'Company')) ?></label>
                                <input type="text" name="contact_company" class="form-control"
                                       value="<?= e($_POST['contact_company'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?= e(t('form_message', 'Message')) ?> <span class="text-danger">*</span></label>
                                <textarea name="contact_message" class="form-control" rows="5" required><?= e($_POST['contact_message'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="contact_submit" value="1"
                                        class="btn btn-primary px-5">
                                    <i class="bi bi-send me-2"></i><?= e(t('btn_submit', 'Send')) ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sağ: Google Maps -->
            <div class="col-lg-6 fx-animate" data-delay="120">
                <?php $mapsEmbed = get_setting('google_maps_embed', ''); ?>
                <?php if ($mapsEmbed): ?>
                    <div class="fx-map-wrap" style="border-radius:1rem;overflow:hidden;height:500px;">
                    <?php if (stripos(trim($mapsEmbed), '<iframe') === 0): ?>
                        <?php
                        $mapsEmbed = preg_replace('/width="[^"]*"/', 'width="100%"', $mapsEmbed);
                        $mapsEmbed = preg_replace('/height="[^"]*"/', 'height="500"', $mapsEmbed);
                        $mapsEmbed = preg_replace('/style="[^"]*"/', 'style="border:0;width:100%;height:500px;"', $mapsEmbed);
                        echo $mapsEmbed;
                        ?>
                    <?php else: ?>
                        <iframe src="<?= e($mapsEmbed) ?>"
                                width="100%" height="500"
                                style="border:0;" allowfullscreen=""
                                loading="lazy" referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                    <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="fx-map-wrap bg-light rounded-4 d-flex align-items-center justify-content-center" style="height:500px;">
                        <div class="text-center text-muted">
                            <i class="bi bi-map fs-1 mb-3 d-block"></i>
                            <p class="small">Google Maps haritası admin panelinden<br>
                               <a href="admin/settings.php" class="text-reset fw-semibold">Genel Ayarlar</a>'dan eklenebilir.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
