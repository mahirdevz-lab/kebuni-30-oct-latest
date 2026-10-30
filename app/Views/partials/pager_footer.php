<?php
/**
 * Footer standar tabel daftar: info baris + pagination Kebuni.
 *
 * Dipakai via:
 *   <?= $this->include('partials/pager_footer', ['pager' => $pager]) ?>
 *
 * Menampilkan "Menampilkan X–Y dari Z data" (BUG-7) dan link pagination
 * template kebuni_full (First/Last, ?page=N). Aman dipanggil meski $pager
 * tidak diset (tidak merender apapun).
 */
?>
<?php if (isset($pager)): ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
    <?php $total = (int) ($pager->getTotal() ?? 0); ?>
    <div class="pager-info" aria-live="polite">
        <?php if ($total > 0): ?>
            <?php $from = ($pager->getCurrentPage() - 1) * $pager->getPerPage() + 1;
                  $to   = min($pager->getCurrentPage() * $pager->getPerPage(), $total); ?>
            Menampilkan
            <span class="num"><?= number_format($from, 0, ',', '.') ?></span>&ndash;<span class="num"><?= number_format($to, 0, ',', '.') ?></span>
            dari <span class="num"><?= number_format($total, 0, ',', '.') ?></span> data
        <?php else: ?>
            Tidak ada data
        <?php endif ?>
    </div>
    <?= $pager->links() ?>
</div>
<?php endif ?>
