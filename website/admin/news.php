<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload_helper.php';

require_admin_login();

$pdo        = db();
$error      = null;
$success    = null;
$uploadDir  = __DIR__ . '/../assets/uploads/news/';
$uploadBase = 'assets/uploads/news/';

function news_slug(string $title): string
{
    $s = mb_strtolower($title, 'UTF-8');
    $s = preg_replace('/\s+/', '-', $s);
    $s = preg_replace('/[^a-z0-9\-]/', '', $s);
    return trim($s, '-') ?: bin2hex(random_bytes(4));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Güvenlik doğrulaması başarısız.';
    } else {
        if (isset($_POST['save_news'])) {
            $id      = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $title   = trim($_POST['title'] ?? '');
            $summary = trim($_POST['summary'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $pubAt   = trim($_POST['published_at'] ?? '');
            $active  = isset($_POST['is_active']) ? 1 : 0;

            if (!$title) {
                $error = 'Başlık zorunludur.';
            } else {
                $slug = news_slug($title);

                $imagePath = null;
                if (!empty($_FILES['image']['name'])) {
                    $fn = upload_file($_FILES['image'], $uploadDir);
                    if ($fn) {
                        $imagePath = $uploadBase . $fn;
                    }
                }

                $pubAtVal = $pubAt ? date('Y-m-d H:i:s', strtotime($pubAt)) : null;

                try {
                    if ($id > 0) {
                        $imgSql = $imagePath ? ', image = :img' : '';
                        $params = [
                            ':title'   => $title,
                            ':slug'    => $slug,
                            ':summary' => $summary ?: null,
                            ':content' => $content ?: null,
                            ':pub'     => $pubAtVal,
                            ':active'  => $active,
                            ':id'      => $id,
                        ];
                        if ($imagePath) {
                            $params[':img'] = $imagePath;
                        }
                        $pdo->prepare("UPDATE news SET title = :title, slug = :slug, summary = :summary,
                                       content = :content, published_at = :pub, is_active = :active $imgSql WHERE id = :id")
                            ->execute($params);
                        $success = 'Haber güncellendi.';
                    } else {
                        $pdo->prepare('INSERT INTO news (title, slug, summary, content, image, published_at, is_active)
                                       VALUES (:title, :slug, :summary, :content, :img, :pub, :active)')
                            ->execute([
                                ':title'   => $title,
                                ':slug'    => $slug,
                                ':summary' => $summary ?: null,
                                ':content' => $content ?: null,
                                ':img'     => $imagePath,
                                ':pub'     => $pubAtVal,
                                ':active'  => $active,
                            ]);
                        $success = 'Haber eklendi.';
                    }
                } catch (Throwable $e) {
                    error_log('[news.php save_news] ' . $e->getMessage());
                    $error = 'Haber kaydedilemedi. Lütfen <a href="migrate.php">migrasyonu</a> kontrol edin.';
                }
            }
        } elseif (isset($_POST['delete_news'])) {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $pdo->prepare('DELETE FROM news WHERE id = ?')->execute([$id]);
                    $success = 'Haber silindi.';
                } catch (Throwable $e) {
                    error_log('[news.php delete_news] ' . $e->getMessage());
                    $error = 'Haber silinemedi.';
                }
            }
        }
    }
}

$editId   = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editNews = null;
if ($editId) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM news WHERE id = ?');
        $stmt->execute([$editId]);
        $editNews = $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        $editNews = null;
        $error = 'Haber verisi alınamadı.';
    }
}

try {
    $newsList = $pdo->query('SELECT id, title, published_at, is_active FROM news ORDER BY IFNULL(published_at, id) DESC')->fetchAll();
} catch (Throwable $e) {
    $newsList = [];
    $error = 'Haber listesi alınamadı. Lütfen <a href="migrate.php">migrasyonu</a> çalıştırın.';
}
$token = csrf_token();

include __DIR__ . '/partials_header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Haberler &amp; Insights (<?= count($newsList) ?>)</strong>
                <a href="news.php" class="btn btn-sm btn-primary">+ Yeni Haber</a>
            </div>
            <div class="card-body p-0">
                <?php if ($error): ?>
                    <div class="alert alert-danger m-3 py-2"><?= $error ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success m-3 py-2"><?= $success ?></div>
                <?php endif; ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($newsList as $item): ?>
                        <li class="list-group-item d-flex align-items-center justify-content-between py-2">
                            <div>
                                <div><?= e($item['title']) ?></div>
                                <div class="small text-muted">
                                    <?= $item['published_at'] ? e(date('d.m.Y', strtotime($item['published_at']))) : 'Tarih yok' ?>
                                </div>
                                <?php if (!(bool)$item['is_active']): ?>
                                    <span class="badge bg-secondary-subtle text-secondary border small">Pasif</span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="?edit=<?= e((string) $item['id']) ?>" class="btn btn-sm btn-outline-secondary">Düzenle</a>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                    <input type="hidden" name="delete_news" value="1">
                                    <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Haberi silmek istediğinize emin misiniz?')">Sil</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($newsList)): ?>
                        <li class="list-group-item text-muted small">Henüz haber yok.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <strong><?= $editNews ? 'Haberi Düzenle' : 'Yeni Haber Ekle' ?></strong>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                    <input type="hidden" name="save_news" value="1">
                    <?php if ($editNews): ?>
                        <input type="hidden" name="id" value="<?= e((string) $editNews['id']) ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Başlık <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required
                               value="<?= e($editNews['title'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Özet</label>
                        <textarea name="summary" class="form-control" rows="2"><?= e($editNews['summary'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">İçerik</label>
                        <textarea name="content" class="form-control" rows="6"><?= e($editNews['content'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Yayın Tarihi</label>
                        <input type="date" name="published_at" class="form-control"
                               value="<?= $editNews && $editNews['published_at'] ? e(date('Y-m-d', strtotime($editNews['published_at']))) : '' ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Görsel</label>
                        <?php if (!empty($editNews['image'])): ?>
                            <div class="mb-2">
                                <img src="<?= e('../' . $editNews['image']) ?>" height="60" class="rounded border">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">JPG, PNG veya WEBP. Maks 5 MB.</div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="news_active"
                            <?= !isset($editNews['is_active']) || (int)($editNews['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="news_active">Aktif</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <?= $editNews ? 'Güncelle' : 'Ekle' ?>
                    </button>
                    <?php if ($editNews): ?>
                        <a href="news.php" class="btn btn-link w-100 mt-1">İptal</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials_footer.php'; ?>
