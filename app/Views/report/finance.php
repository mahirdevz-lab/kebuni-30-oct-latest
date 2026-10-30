<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$rp = static fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
$s  = $report['summary'];
$exportUrl = base_url('admin/report/finance') . '?' . http_build_query(['from' => $from, 'to' => $to, 'type' => $type, 'export' => 1]);
$refLabel = ['SALE' => 'Penjualan (PO)', 'MANUAL_INCOME' => 'Manual', 'MANUAL_EXPENSE' => 'Manual', 'RECEIVABLE_PAYMENT' => 'Bayar Piutang', 'PAYABLE_PAYMENT' => 'Bayar Hutang'];
$aging = ['current_amt' => 'Belum Jatuh Tempo', 'd1_30' => '1-30 hari', 'd31_60' => '31-60 hari', 'd60_plus' => '> 60 hari'];
?>
<div class="card mb-4 no-print">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-3"><label class="form-label small text-muted">Dari</label><input type="date" name="from" class="form-control form-control-sm" value="<?= esc($from) ?>"></div>
            <div class="col-md-3"><label class="form-label small text-muted">Sampai</label><input type="date" name="to" class="form-control form-control-sm" value="<?= esc($to) ?>"></div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Jenis</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="INCOME" <?= $type === 'INCOME' ? 'selected' : '' ?>>Pemasukan</option>
                    <option value="EXPENSE" <?= $type === 'EXPENSE' ? 'selected' : '' ?>>Pengeluaran</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-fill">Terapkan</button>
                <a href="<?= esc($exportUrl) ?>" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-file-csv"></i> CSV</a>
                <button aria-label="Cetak halaman" type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="kpi-card"><div class="kpi-value profit-positive"><?= $rp($s['income']) ?></div><div class="kpi-label">Total Pemasukan</div></div></div>
    <div class="col-md-4"><div class="kpi-card"><div class="kpi-value profit-negative"><?= $rp($s['expense']) ?></div><div class="kpi-label">Total Pengeluaran</div></div></div>
    <div class="col-md-4"><div class="kpi-card"><div class="kpi-value <?= $s['net'] >= 0 ? 'profit-positive' : 'profit-negative' ?>"><?= $rp($s['net']) ?></div><div class="kpi-label">Arus Kas Bersih</div></div></div>
</div>

<div class="row g-3 mb-4">
    <?php foreach (['receivable' => 'Umur Piutang', 'payable' => 'Umur Hutang'] as $key => $label): ?>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="section-header"><?= $label ?> (Outstanding)</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <?php foreach ($aging as $k => $l): ?>
                            <tr><td><?= $l ?></td><td class="text-end"><?= $rp($report[$key][$k]) ?></td></tr>
                        <?php endforeach ?>
                        <tr class="fw-bold"><td>Total</td><td class="text-end"><?= $rp($report[$key]['total']) ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach ?>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-file-lines me-1"></i> Buku Kas <?= tgl_id($from) ?> &ndash; <?= tgl_id($to) ?></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Tanggal</th><th>Jenis</th><th>Kategori</th><th>Keterangan</th><th>Sumber</th><th>Pihak</th><th class="text-end">Jumlah</th></tr></thead>
                <tbody>
                    <?php if (empty($report['rows'])): ?>
                        <tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-inbox"></i><p class="mb-0">Tidak ada transaksi pada rentang ini.</p></div></td></tr>
                    <?php else: foreach ($report['rows'] as $r): ?>
                        <tr>
                            <td><?= tgl_id($r['transaction_date']) ?></td>
                            <td><?= $r['type'] === 'INCOME' ? '<span class="badge bg-success">Masuk</span>' : '<span class="badge bg-danger">Keluar</span>' ?></td>
                            <td><?= esc($r['category_name'] ?? '-') ?></td>
                            <td><?= esc($r['description'] ?? '-') ?></td>
                            <td><?= esc($refLabel[$r['reference_type']] ?? $r['reference_type']) ?></td>
                            <td><?= esc($r['nama_account'] ?? $r['nama_supplier'] ?? '-') ?></td>
                            <td class="text-end fw-semibold <?= $r['type'] === 'INCOME' ? 'profit-positive' : 'profit-negative' ?>"><?= $rp($r['amount']) ?></td>
                        </tr>
                    <?php endforeach; endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
