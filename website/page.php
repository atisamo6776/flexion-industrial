<?php

require_once __DIR__ . '/includes/header.php';

$pdo  = db();
$slug = trim($_GET['slug'] ?? '');

if (!$slug) {
    http_response_code(404);
    echo '<div class="container py-5"><h1 class="h3">Sayfa bulunamadı</h1></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM pages WHERE slug = :slug AND is_active = 1 LIMIT 1');
$stmt->execute([':slug' => $slug]);
$page = $stmt->fetch();

if (!$page) {
    http_response_code(404);
    ?>
    <div class="container py-5">
        <h1 class="h3 mb-3">Sayfa bulunamadı</h1>
        <p class="text-muted">Aradığınız sayfa sistemde yer almıyor veya pasif durumda.</p>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">Ana sayfaya dön</a>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
?>

<?php if (!empty($page['banner_image'])): ?>
    <section class="fx-page-banner d-flex align-items-center mb-4"
             style="background-image:url('<?= e($page['banner_image']) ?>');">
        <div class="container">
            <?php if (!empty($page['banner_title'])): ?>
                <h1 class="h3 text-white mb-0"><?= e($page['banner_title']) ?></h1>
            <?php else: ?>
                <h1 class="h3 text-white mb-0"><?= e($page['title']) ?></h1>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h1 class="h2 mb-4"><?= e($page['title']) ?></h1>
                <div class="page-content">
                    <?= $page['content'] ?>
                </div>

                <?php if ($slug === 'iletisim'): ?>
                    <hr class="my-4">
                    <h2 class="h4 mb-3">İletişim Bilgileri</h2>
                    <div class="row g-3 mb-4">
                        <?php $phone = get_setting('contact_phone', ''); ?>
                        <?php $email = get_setting('contact_email', ''); ?>
                        <?php $address = get_setting('contact_address', ''); ?>
                        <?php if ($phone): ?>
                            <div class="col-sm-6">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bi bi-telephone text-primary mt-1"></i>
                                    <div>
                                        <div class="small fw-semibold">Telefon</div>
                                        <a href="tel:<?= e($phone) ?>" class="text-decoration-none"><?= e($phone) ?></a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($email): ?>
                            <div class="col-sm-6">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bi bi-envelope text-primary mt-1"></i>
                                    <div>
                                        <div class="small fw-semibold">E-posta</div>
                                        <a href="mailto:<?= e($email) ?>" class="text-decoration-none"><?= e($email) ?></a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($address): ?>
                            <div class="col-12">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bi bi-geo-alt text-primary mt-1"></i>
                                    <div>
                                        <div class="small fw-semibold">Adres</div>
                                        <p class="mb-0"><?= e($address) ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <h2 class="h4 mb-3">Bize Yazın</h2>
                    <?php
                    $formSent  = false;
                    $formError = null;
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['contact_submit'])) {
                        $cName    = trim($_POST['contact_name'] ?? '');
                        $cEmail   = trim($_POST['contact_email'] ?? '');
                        $cMessage = trim($_POST['contact_message'] ?? '');
                        if (!$cName || !$cEmail || !$cMessage) {
                            $formError = 'Lütfen tüm alanları doldurun.';
                        } elseif (!filter_var($cEmail, FILTER_VALIDATE_EMAIL)) {
                            $formError = 'Geçerli bir e-posta adresi girin.';
                        } else {
                            $to      = $email ?: get_setting('contact_email', '');
                            $subject = 'Flexion Web - İletişim Formu: ' . $cName;
                            $body    = "Ad: $cName\nE-posta: $cEmail\n\nMesaj:\n$cMessage";
                            if ($to) {
                                @mail($to, $subject, $body, "From: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'flexion.com'));
                            }
                            $formSent = true;
                        }
                    }
                    ?>
                    <?php if ($formSent): ?>
                        <div class="alert alert-success">Mesajınız alındı. En kısa sürede geri dönüş yapacağız.</div>
                    <?php else: ?>
                        <?php if ($formError): ?>
                            <div class="alert alert-danger"><?= e($formError) ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label">Ad Soyad</label>
                                <input type="text" name="contact_name" class="form-control"
                                       value="<?= e($_POST['contact_name'] ?? '') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">E-posta</label>
                                <input type="email" name="contact_email" class="form-control"
                                       value="<?= e($_POST['contact_email'] ?? '') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Mesaj</label>
                                <textarea name="contact_message" class="form-control" rows="5" required><?= e($_POST['contact_message'] ?? '') ?></textarea>
                            </div>
                            <button type="submit" name="contact_submit" value="1" class="btn btn-primary">
                                Gönder
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
