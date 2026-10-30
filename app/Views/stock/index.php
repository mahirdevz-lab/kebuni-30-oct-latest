<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$q = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
$totalValue = array_sum(array_map(static fn ($r) => (float) $r['value'], $rows));
?>
<div class="card">
    <div class="section-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-warehouse me-1"></i> Saldo Stok Toko (dari stock ledger)</span>
        <span class="small">Nilai persediaan: <strong>Rp <?= number_format($totalValue, 0, ',', '.') ?></strong></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Produk</th><th class="text-end">Saldo</th><th class="text-end">Stok min.</th><th class="text-end">Biaya rata-rata</th><th class="text-end">Nilai</th><th></th></tr></thead>
            <tbody>
            <?php if (! $rows): ?><tr><td colspan="6" class="text-center text-muted py-4">Belum ada produk POS. Atur di menu Katalog & Harga.</td></tr><?php endif ?>
            <?php foreach ($rows as $r): ?>
                <?php $low = $r['min_stock'] !== null && (float) $r['qty'] <= (float) $r['min_stock']; ?>
                <tr>
                    <td><?= esc($r['product_name']) ?> <span class="small text-muted"><?= esc($r['product_code']) ?></span></td>
                    <td class="text-end <?= (float) $r['qty'] < 0 ? 'text-danger fw-bold' : ($low ? 'text-warning fw-bold' : '') ?>"><?= $q($r['qty']) ?> <?= esc($r['unit']) ?></td>
                    <td class="text-end"><?= $r['min_stock'] !== null ? $q($r['min_stock']) : '-' ?></td>
                    <td class="text-end"><?= number_format((float) $r['avg_cost'], 2, ',', '.') ?></td>
                    <td class="text-end"><?= number_format((float) $r['value'], 0, ',', '.') ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= base_url('admin/stock/book?product_id=' . $r['id']) ?>">Kartu stok</a></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
