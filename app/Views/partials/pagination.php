<?php
/**
 * Pagination partial reusable.
 *
 * Sejak PRD-BACKOFFICE-UI T3, partial ini resmi mendelegasikan ke
 * partials/pager_footer (info "Menampilkan X–Y dari Z data" + template
 * pagination kebuni_full). Pemakaian lama tetap jalan tanpa perubahan.
 *
 * Dipakai via: $this->include('partials/pagination', ['pager' => $pager])
 */
?>
<?= $this->include('partials/pager_footer', ['pager' => $pager ?? null]) ?>
