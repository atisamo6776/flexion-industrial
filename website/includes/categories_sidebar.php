<?php
/**
 * Kategori sidebar accordion partial'ı.
 *
 * Gereken değişkenler (çağıran sayfa tarafından tanımlanmalı):
 *   $categoriesTree  — get_categories_tree() çıktısı (array)
 *   $activeCategoryId — (int|null) Vurgulanacak aktif kategori ID'si; yoksa 0
 */

$activeCategoryId = (int) ($activeCategoryId ?? 0);

?>
<h2 class="h6 text-uppercase text-muted mb-3"><?= e(t('cat_categories_title', 'Categories')) ?></h2>
<div class="fx-cat-accordion">
    <?php foreach ($categoriesTree as $cat):
        $cid         = (int) $cat['id'];
        $hasChildren = !empty($cat['children']);
        $accId       = 'fx-cat-' . $cid;

        // Çeviriyi al
        $catTr   = get_translation('category_translations', 'category_id', $cid);
        $catName = $catTr['name'] ?: $cat['name'];
        $catSlug = $catTr['slug'] ?: ($cat['slug'] ?? '');
        $catUrl  = $catSlug ? '/' . ltrim($catSlug, '/') : 'category?id=' . $cid;

        // Bu item aktif mi veya aktif kategorinin ebeveyni mi?
        $isOpen = ($activeCategoryId > 0 && $cid === $activeCategoryId);
        if (!$isOpen && $hasChildren && $activeCategoryId > 0) {
            foreach ($cat['children'] as $ch) {
                if ((int) $ch['id'] === $activeCategoryId) {
                    $isOpen = true;
                    break;
                }
            }
        }
    ?>
    <div class="fx-cat-item">
        <?php if ($hasChildren): ?>
            <!-- Başlığa tıkla → tüm ürünler, oka tıkla → alt kategoriler aç/kapat -->
            <div class="fx-cat-btn<?= $isOpen ? ' fx-cat-active' : '' ?>">
                <a href="<?= e($catUrl) ?>"
                   class="fx-cat-btn-text<?= $cid === $activeCategoryId ? ' fx-cat-child-active' : '' ?>">
                    <?= e($catName) ?>
                </a>
                <button type="button"
                        class="fx-cat-chevron-btn"
                        data-bs-toggle="collapse"
                        data-bs-target="#<?= $accId ?>"
                        aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
                        aria-controls="<?= $accId ?>">
                    <i class="bi bi-chevron-down fx-cat-chevron"></i>
                </button>
            </div>
            <div class="collapse<?= $isOpen ? ' show' : '' ?>" id="<?= $accId ?>">
                <div class="fx-cat-children">
                    <?php foreach ($cat['children'] as $child):
                        $childTr   = get_translation('category_translations', 'category_id', (int)$child['id']);
                        $childName = $childTr['name'] ?: $child['name'];
                        $childSlug = $childTr['slug'] ?: ($child['slug'] ?? '');
                        $childUrl  = $childSlug ? '/' . ltrim($childSlug, '/') : 'category?id=' . (int)$child['id'];
                    ?>
                        <a href="<?= e($childUrl) ?>"
                           class="fx-cat-child-link<?= (int) $child['id'] === $activeCategoryId ? ' fx-cat-child-active' : '' ?>">
                            <?= e($childName) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <a href="<?= e($catUrl) ?>"
               class="fx-cat-btn text-decoration-none<?= $isOpen ? ' fx-cat-active' : '' ?>">
                <span class="fx-cat-btn-text"><?= e($catName) ?></span>
            </a>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
