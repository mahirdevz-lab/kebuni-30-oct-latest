<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$rp = static fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
$s  = $report['summary'];
$exportUrl = base_url('admin/report/transaction') . '?' . http_build_query(['from' => $from, 'to' => $to, 'status' => $status, 'account_id' => $account_id, 'export' => 1]);
?>
<div class="card mb-4 no-print">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-2"><label class="form-label small text-muted">Dari</label><input type="date" name="from" class="form-control form-control-sm" value="<?= esc($from) ?>"></div>
            <div class="col-md-2"><label class="form-label small text-muted">Sampai</label><input type="date" name="to" class="form-control form-control-sm" value="<?= esc($to) ?>"></div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Customer</label>
                <select name="account_id" class="form-select form-select-sm">
                    <option value="">Semua Customer</option>
                    <?php foreach ($accounts as $a): ?><option value="<?= $a['id'] ?>" <?= (int) $account_id === (int) $a['id'] ? 'selected' : '' ?>><?= esc($a['nama_account']) ?></option><?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Valid</option>
                    <?php foreach (['Draft', 'Submitted', 'Approved', 'Processing', 'Completed', 'Cancelled'] as $st): ?><option <?= $status === $st ? 'selected' : '' ?>><?= $st ?></option><?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-fill">Terapkan</button>
                <a href="<?= esc($exportUrl) ?>" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-file-csv"></i> CSV</a>
                <button aria-label="Cetak halaman" type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-2"><div class="kpi-card"><div class="kpi-value"><?= $s['orders'] ?></div><div class="kpi-label">Jumlah Order</div></div></div>
    <div class="col-md-2"><div class="kpi-card"><div class="kpi-value"><?= $rp($s['omzet']) ?></div><div class="kpi-label">Total Omzet (<?= number_format($s['qty'], 0, ',', '.') ?> Kg)</div></div></div>
    <div class="col-md-2"><div class="kpi-card"><div class="kpi-value"><?= $rp($s['cogs']) ?></div><div class="kpi-label">Total COGS</div></div></div>
    <div class="col-md-3"><div class="kpi-card"><div class="kpi-value <?= $s['profit'] >= 0 ? 'profit-positive' : 'profit-negative' ?>"><?= $rp($s['profit']) ?></div><div class="kpi-label">Estimasi Profit (sebelum komisi)</div></div></div>
    <div class="col-md-3"><div class="kpi-card"><div class="kpi-value <?= $s['net_profit'] >= 0 ? 'profit-positive' : 'profit-negative' ?>"><?= $rp($s['net_profit']) ?></div><div class="kpi-label">Laba Bersih (setelah komisi <?= $rp($s['commission']) ?>)</div></div></div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-file-lines me-1"></i> Report Transaksi <?= tgl_id($from) ?> &ndash; <?= tgl_id($to) ?></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>No PO</th><th>Tanggal</th><th>Customer</th><th>Term</th><th>Status</th><th class="text-end">Qty (Kg)</th><th class="text-end">Omzet</th><th class="text-end">COGS</th><th class="text-end">Profit</th><th class="text-end">Komisi</th><th class="text-end">Laba Bersih</th></tr></thead>
                <tbody>
                    <?php if (empty($report['rows'])): ?>
                        <tr><td colspan="11"><div class="empty-state"><i class="fa-solid fa-inbox"></i><p class="mb-0">Tidak ada transaksi pada filter ini.</p></div></td></tr>
                    <?php else: foreach ($report['rows'] as $r): ?>
                        <tr>
                            <td><span class="badge bg-light text-dark border"><?= esc($r['order_no']) ?></span></td>
                            <td><?= tgl_id($r['order_date']) ?></td>
                            <td><?= esc($r['nama_account']) ?></td>
                            <td><?= esc($r['payment_term']) ?></td>
                            <td><?= esc($r['status']) ?></td>
                            <td class="text-end"><?= number_format((float) $r['total_qty'], 2, ',', '.') ?></td>
                            <td class="text-end"><?= $rp($r['grand_total']) ?></td>
                            <td class="text-end"><?= $rp($r['total_cogs']) ?></td>
                            <td class="text-end <?= (float) $r['estimated_profit'] >= 0 ? 'profit-positive' : 'profit-negative' ?>"><?= $rp($r['estimated_profit']) ?></td>
                            <td class="text-end"><?= $rp($r['commission_amount'] ?? 0) ?></td>
                            <?php $net = (float) $r['estimated_profit'] - (float) ($r['commission_amount'] ?? 0); ?><td class="text-end fw-semibold <?= $net >= 0 ? 'profit-positive' : 'profit-negative' ?>"><?= $rp($net) ?></td>
                        </tr>
                    <?php endforeach; endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
