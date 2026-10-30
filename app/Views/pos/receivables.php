<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$rp    = static fn ($v) => number_format((float) $v, 0, ',', '.');
$total = array_sum(array_column($rows, 'balance_due'));
$byCust = [];
foreach ($rows as $r) {
    $k = $r['nama_account'] ?? '(tanpa pelanggan)';
    $byCust[$k] = ($byCust[$k] ?? 0) + (float) $r['balance_due'];
}
arsort($byCust);
?>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Total sisa tagihan</div><div class="fs-4 fw-bold text-danger">Rp <?= $rp($total) ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Penjualan belum lunas</div><div class="fs-4 fw-bold"><?= count($rows) ?></div></div></div></div>
    <div class="col-12 col-md-6"><div class="card h-100"><div class="card-body small">
        <div class="text-muted mb-1">Per pelanggan</div>
        <?php foreach (array_slice($byCust, 0, 4, true) as $n => $v): ?><div class="d-flex justify-content-between"><span><?= esc($n) ?></span><strong>Rp <?= $rp($v) ?></strong></div><?php endforeach ?>
        <?php if (! $byCust): ?><span class="text-muted">Tidak ada piutang.</span><?php endif ?>
    </div></div></div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-hand-holding-dollar me-1"></i> Sisa tagihan pelanggan (pembayaran sebagian)</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Penjualan</th><th>Tanggal</th><th>Pelanggan</th><th class="text-end">Total</th><th class="text-end">Dibayar</th><th class="text-end">Sisa</th><th>Umur</th><th></th></tr></thead>
            <tbody>
            <?php if (! $rows): ?><tr><td colspan="8" class="text-center text-muted py-4">Tidak ada sisa tagihan.</td></tr><?php endif ?>
            <?php foreach ($rows as $r): ?>
                <?php $age = (int) floor((time() - strtotime($r['sale_date'])) / 86400); ?>
                <tr>
                    <td><a href="<?= base_url('admin/pos/sales/' . $r['id']) ?>"><?= esc($r['sale_no']) ?></a></td>
                    <td><?= date('d/m/Y', strtotime($r['sale_date'])) ?></td>
                    <td><?= esc($r['nama_account'] ?? '-') ?><div class="small text-muted"><?= esc($r['nomor_telepon'] ?? '') ?></div></td>
                    <td class="text-end"><?= $rp($r['grand_total']) ?></td>
                    <td class="text-end"><?= $rp($r['paid_total']) ?></td>
                    <td class="text-end fw-bold text-danger"><?= $rp($r['balance_due']) ?><?= (float) $r['pending_amount'] > 0 ? '<div class="small text-info fw-normal">QRIS/Transfer ' . $rp($r['pending_amount']) . ' menunggu verifikasi</div>' : '' ?></td>
                    <td class="<?= $age > 14 ? 'text-danger' : ($age > 7 ? 'text-warning' : '') ?>"><?= $age ?> hari</td>
                    <td class="text-end"><a class="btn btn-sm btn-primary" href="<?= base_url('admin/pos/sales/' . $r['id'] . '#pelunasan') ?>">Terima Pembayaran</a></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
