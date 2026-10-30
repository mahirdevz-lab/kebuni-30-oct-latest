<?php

use CodeIgniter\Pager\PagerRenderer;

/**
 * Template pagination Kebuni (pengganti default_full CI4).
 *
 * - Tombol «Awal» (First) dan «Akhir» (Last) + Prev/Next halaman aktif.
 * - Semua link memakai query string (?page=N) agar cocok dengan
 *   handler AJAX loadTable() (baca url.searchParams.get('page')).
 * - Gaya visual diatur di public/assets/css/app.css (blok "PAGINATION KEBUNI").
 *
 * @var PagerRenderer $pager
 * @var string        $pagerGroup
 */
$pager->setSurroundCount(2);

// Satu halaman atau kurang: tidak ada yang perlu dinavigasi.
if ($pager->getPageCount() <= 1) {
    return;
}

$currentPage = $pager->getCurrentPageNumber();
?>
<nav aria-label="Navigasi halaman">
    <ul class="pagination pagination-sm mb-0">
        <?php /* ---------- Awal (First) ---------- */ ?>
        <?php if ($currentPage <= 1): ?>
            <li class="page-item disabled" aria-disabled="true">
                <span class="page-link" aria-hidden="true">&laquo;</span>
            </li>
        <?php else: ?>
            <li class="page-item">
                <a class="page-link" href="<?= $pager->getFirst() ?>" aria-label="Halaman pertama">
                    <span aria-hidden="true">&laquo;</span><span class="d-none d-md-inline ms-1">Awal</span>
                </a>
            </li>
        <?php endif ?>

        <?php /* ---------- Sebelumnya (Prev) ---------- */ ?>
        <?php if ($pager->hasPreviousPage()): ?>
            <li class="page-item">
                <a class="page-link" href="<?= $pager->getPreviousPage() ?>" aria-label="Halaman sebelumnya">
                    <span aria-hidden="true">&lsaquo;</span>
                </a>
            </li>
        <?php else: ?>
            <li class="page-item disabled" aria-disabled="true">
                <span class="page-link" aria-hidden="true">&lsaquo;</span>
            </li>
        <?php endif ?>

        <?php /* ---------- Nomor halaman ---------- */ ?>
        <?php foreach ($pager->links() as $link): ?>
            <li class="page-item <?= $link['active'] ? 'active' : '' ?>" <?= $link['active'] ? 'aria-current="page"' : '' ?>>
                <?php if ($link['active']): ?>
                    <span class="page-link num"><?= $link['title'] ?></span>
                <?php else: ?>
                    <a class="page-link num" href="<?= $link['uri'] ?>" aria-label="Halaman <?= $link['title'] ?>"><?= $link['title'] ?></a>
                <?php endif ?>
            </li>
        <?php endforeach ?>

        <?php /* ---------- Berikutnya (Next) ---------- */ ?>
        <?php if ($pager->hasNextPage()): ?>
            <li class="page-item">
                <a class="page-link" href="<?= $pager->getNextPage() ?>" aria-label="Halaman berikutnya">
                    <span aria-hidden="true">&rsaquo;</span>
                </a>
            </li>
        <?php else: ?>
            <li class="page-item disabled" aria-disabled="true">
                <span class="page-link" aria-hidden="true">&rsaquo;</span>
            </li>
        <?php endif ?>

        <?php /* ---------- Akhir (Last) ---------- */ ?>
        <?php if ($currentPage >= $pager->getPageCount()): ?>
            <li class="page-item disabled" aria-disabled="true">
                <span class="page-link" aria-hidden="true">&raquo;</span>
            </li>
        <?php else: ?>
            <li class="page-item">
                <a class="page-link" href="<?= $pager->getLast() ?>" aria-label="Halaman terakhir">
                    <span class="d-none d-md-inline me-1">Akhir</span><span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        <?php endif ?>
    </ul>
</nav>
