<?php
/**
 * Kategori sidebar accordion partial'ı.
 *
 * Gereken değişkenler (çağıran sayfa tarafından tanımlanmalı):
 *   $categoriesTree  — get_categories_tree() çıktısı (array)
 *   $activeCategoryId — (int|null) Vurgulanacak aktif kategori ID'si; yoksa 0
 */

$activeCategoryId = (int) ($activeCategoryId ?? 0);

$_sidebarTitle = defined('CURRENT_LANG') ? [
    'en' => 'Categories',
    'de' => 'Kategorien',
    'it' => 'Categorie',
    'fr' => 'Catégories',
][CURRENT_LANG] ?? 'Categories' : 'Kategoriler';
?>
<h2 class="h6 text-uppercase text-muted mb-3"><?= e($_sidebarTitle) ?></h2>
<div class="fx-cat-accordion">
    <?php foreach ($categoriesTree as $cat):
        $cid         = (int) $cat['id'];
        $hasChildren = !empty($cat['children']);
        $accId       = 'fx-cat-' . $cid;

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

        $catUrl = defined('CURRENT_LANG') && CURRENT_LANG !== 'en'
            ? '/' . CURRENT_LANG . '/category?id=' . $cid
            : 'category?id=' . $cid;
    ?>
    <div class="fx-cat-item">
        <?php if ($hasChildren): ?>
            <!-- Başlığa tıkla → tüm ürünler, oka tıkla → alt kategoriler aç/kapat -->
            <div class="fx-cat-btn<?= $isOpen ? ' fx-cat-active' : '' ?>">
                <a href="<?= $catUrl ?>"
                   class="fx-cat-btn-text<?= $cid === $activeCategoryId ? ' fx-cat-child-active' : '' ?>">
                    <?= e($cat['name']) ?>
                </a>
                <button type="button"
                        class="fx-cat-chevron-btn"
                        data-bs-toggle="collapse"
                        data-bs-target="#<?= $accId ?>"
                        aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
                        aria-controls="<?= $accId ?>"
                        aria-label="Alt kategorileri <?= $isOpen ? 'kapat' : 'aç' ?>">
                    <i class="bi bi-chevron-down fx-cat-chevron"></i>
                </button>
            </div>
            <div class="collapse<?= $isOpen ? ' show' : '' ?>" id="<?= $accId ?>">
                <div class="fx-cat-children">
                    <?php foreach ($cat['children'] as $child):
                        $childUrl = defined('CURRENT_LANG') && CURRENT_LANG !== 'en'
                            ? '/' . CURRENT_LANG . '/category?id=' . (int) $child['id']
                            : 'category?id=' . (int) $child['id'];
                    ?>
                        <a href="<?= $childUrl ?>"
                           class="fx-cat-child-link<?= (int) $child['id'] === $activeCategoryId ? ' fx-cat-child-active' : '' ?>">
                            <?= e($child['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <a href="<?= $catUrl ?>"
               class="fx-cat-btn text-decoration-none<?= $isOpen ? ' fx-cat-active' : '' ?>">
                <span class="fx-cat-btn-text"><?= e($cat['name']) ?></span>
            </a>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
