<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$badge = ['PAID' => 'success', 'PARTIAL' => 'warning', 'UNPAID' => 'secondary', 'PENDING_VERIFICATION' => 'info'];
$label = ['PAID' => 'Lunas', 'PARTIAL' => 'Sebagian', 'UNPAID' => 'Belum bayar', 'PENDING_VERIFICATION' => 'Menunggu verifikasi'];
$sum   = ['omzet' => 0, 'paid' => 0, 'cogs' => 0, 'count' => 0];
foreach ($sales as $s) {
    if ($s['status'] === 'POSTED') {
        $sum['omzet'] += (float) $s['grand_total'];
        $sum['paid']  += (float) $s['paid_total'];
        $sum['cogs']  += (float) $s['cogs_total'];
        $sum['count']++;
    }
}
?>
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-sm-3"><label class="form-label mb-1">Dari</label><input type="date" name="from" value="<?= esc($from) ?>" class="form-control"></div>
            <div class="col-sm-3"><label class="form-label mb-1">Sampai</label><input type="date" name="to" value="<?= esc($to) ?>" class="form-control"></div>
            <div class="col-sm-3"><label class="form-label mb-1">Status bayar</label>
                <select name="payment_status" class="form-select">
                    <option value="">Semua</option>
                    <?php foreach ($label as $k => $v): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach ?>
                </select>
            </div>
            <div class="col-sm-3"><button class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i> Tampilkan</button></div>
        </form>
        <?php $rq = 'from=' . urlencode($from) . '&to=' . urlencode($to); ?>
        <div class="mt-2 d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= base_url('admin/pos/sales/report?' . $rq) ?>"><i class="fa-solid fa-file-pdf me-1"></i> Laporan harian (cetak / PDF)</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('admin/pos/sales/report?format=csv&type=daily&' . $rq) ?>"><i class="fa-solid fa-file-csv me-1"></i> CSV ringkasan harian</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('admin/pos/sales/report?format=csv&type=detail&' . $rq) ?>"><i class="fa-solid fa-file-csv me-1"></i> CSV rincian transaksi</a>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Transaksi (diposting)</div><div class="fs-4 fw-bold"><?= $sum['count'] ?></div></div></div></div>
    <?php $retV = (float) ($returns['v'] ?? 0); $retC = (float) ($returns['cv'] ?? 0); ?>
    <div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Penjualan bersih (omzet − retur)</div><div class="fs-4 fw-bold">Rp <?= number_format($sum['omzet'] - $retV, 0, ',', '.') ?></div>
        <div class="small text-muted">kotor Rp <?= number_format($sum['omzet'], 0, ',', '.') ?><?php if ((int) $returns['c']): ?> − retur <?= (int) $returns['c'] ?>× Rp <?= number_format($retV, 0, ',', '.') ?> · <a href="<?= base_url('admin/pos/returns?from=' . $from . '&to=' . $to) ?>">lihat</a><?php endif ?></div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Penerimaan terkonfirmasi</div><div class="fs-4 fw-bold">Rp <?= number_format($sum['paid'], 0, ',', '.') ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">HPP bersih (moving average)</div><div class="fs-4 fw-bold">Rp <?= number_format($sum['cogs'] - $retC, 0, ',', '.') ?></div><div class="small text-muted">laba kotor Rp <?= number_format(($sum['omzet'] - $retV) - ($sum['cogs'] - $retC), 0, ',', '.') ?></div></div></div></div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-receipt me-1"></i> Penjualan POS</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>No</th><th>Waktu</th><th>Kasir</th><th>Pelanggan</th><th class="text-end">Total</th><th class="text-end">Dibayar</th><th>Status bayar</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (! $sales): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">Belum ada penjualan pada periode ini.</td></tr>
            <?php endif ?>
            <?php foreach ($sales as $s): ?>
                <tr class="<?= $s['status'] === 'VOIDED' ? 'text-muted text-decoration-line-through' : '' ?>">
                    <td><a href="<?= base_url('admin/pos/sales/' . $s['id']) ?>"><?= esc($s['sale_no']) ?></a></td>
                    <td><?= date('d/m/Y H:i', strtotime($s['sale_date'])) ?></td>
                    <td><?= esc($s['cashier_name'] ?? '-') ?></td>
                    <td><?= esc($s['customer_name'] ?? '-') ?></td>
                    <td class="text-end"><?= number_format((float) $s['grand_total'], 0, ',', '.') ?></td>
                    <td class="text-end"><?= number_format((float) $s['paid_total'], 0, ',', '.') ?></td>
                    <td><span class="badge text-bg-<?= $badge[$s['payment_status']] ?? 'secondary' ?>"><?= $label[$s['payment_status']] ?? esc($s['payment_status']) ?></span></td>
                    <td><?= $s['status'] === 'VOIDED' ? '<span class="badge text-bg-danger">Dibatalkan</span>' : '<span class="badge text-bg-light">Diposting</span>' ?></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="<?= base_url('admin/pos/sales/' . $s['id']) ?>">Detail</a></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
