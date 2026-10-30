<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$rp = static fn ($v) => number_format((float) $v, 0, ',', '.');
$tot = ['v' => array_sum(array_column($rows, 'return_value')), 'r' => array_sum(array_column($rows, 'refund_amount'))];
?>
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-sm-3"><label class="form-label mb-1">Dari</label><input type="date" name="from" value="<?= esc($from) ?>" class="form-control"></div>
            <div class="col-sm-3"><label class="form-label mb-1">Sampai</label><input type="date" name="to" value="<?= esc($to) ?>" class="form-control"></div>
            <div class="col-sm-2"><button class="btn btn-primary w-100">Tampilkan</button></div>
        </form>
        <div class="small text-muted mt-2">Retur dibuat dari halaman detail penjualan (tombol <em>Retur Barang</em>). Koreksi barang/qty yang salah = retur barang yang salah + transaksi baru yang benar.</div>
    </div>
</div>

<div class="card mb-3">
    <div class="section-header d-flex justify-content-between"><span><i class="fa-solid fa-rotate-left me-1"></i> Retur pelanggan</span><span class="small">Nilai Rp <?= $rp($tot['v']) ?> · Refund Rp <?= $rp($tot['r']) ?></span></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>No</th><th>Waktu</th><th>Penjualan</th><th>Pelanggan</th><th>Alasan</th><th class="text-end">Nilai</th><th class="text-end">Refund</th><th>Cara</th><th>Oleh</th></tr></thead>
            <tbody>
            <?php if (! $rows): ?><tr><td colspan="9" class="text-center text-muted py-3">Belum ada retur.</td></tr><?php endif ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a href="<?= base_url('admin/pos/returns/' . $r['id']) ?>"><?= esc($r['return_no']) ?></a></td>
                    <td><?= date('d/m/Y H:i', strtotime($r['return_date'])) ?></td>
                    <td><a href="<?= base_url('admin/pos/sales/' . $r['sale_id']) ?>"><?= esc($r['sale_no']) ?></a><div class="small text-muted"><?= date('d/m/Y', strtotime($r['sale_date'])) ?></div></td>
                    <td><?= esc($r['customer_name'] ?? '-') ?></td>
                    <td class="small"><?= esc($reasons[$r['reason_code']] ?? $r['reason_code']) ?></td>
                    <td class="text-end"><?= $rp($r['return_value']) ?></td>
                    <td class="text-end"><?= $rp($r['refund_amount']) ?></td>
                    <td class="small"><?= $r['refund_method'] === 'CASH' ? 'Tunai' : ($r['refund_method'] === 'TRANSFER' ? 'Transfer · ' . esc($r['account_name']) : '-') ?></td>
                    <td class="small"><?= esc($r['user_name'] ?? '-') ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-right-left me-1"></i> Koreksi metode pembayaran</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Waktu koreksi</th><th>Penjualan</th><th>Koreksi</th><th class="text-end">Nilai</th><th>Tanggal bisnis</th><th>Alasan</th><th>Oleh</th></tr></thead>
            <tbody>
            <?php if (! $corrections): ?><tr><td colspan="7" class="text-center text-muted py-3">Belum ada koreksi.</td></tr><?php endif ?>
            <?php foreach ($corrections as $c): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                    <td><a href="<?= base_url('admin/pos/sales/' . $c['sale_id']) ?>"><?= esc($c['sale_no']) ?></a></td>
                    <td><?= \App\Services\PosSaleService::label($c['from_method']) ?> → <?= \App\Services\PosSaleService::label($c['to_method']) ?></td>
                    <td class="text-end"><?= $rp($c['amount']) ?></td>
                    <td><?= date('d/m/Y', strtotime($c['business_date'])) ?></td>
                    <td class="small"><?= esc($c['reason']) ?></td>
                    <td class="small"><?= esc($c['user_name'] ?? '-') ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
