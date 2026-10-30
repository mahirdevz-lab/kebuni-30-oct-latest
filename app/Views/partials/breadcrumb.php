<?php if (! empty($breadcrumbs)): ?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="page-title mb-0"><?= esc($title ?? end($breadcrumbs)['label']) ?></h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <?php foreach ($breadcrumbs as $i => $crumb): ?>
                    <?php if ($i === array_key_last($breadcrumbs)): ?>
                        <li class="breadcrumb-item active" aria-current="page"><?= esc($crumb['label']) ?></li>
                    <?php else: ?>
                        <li class="breadcrumb-item"><a href="<?= esc($crumb['url'] ?? '#') ?>"><?= esc($crumb['label']) ?></a></li>
                    <?php endif ?>
                <?php endforeach ?>
            </ol>
        </nav>
    </div>
</div>
<?php endif ?>
