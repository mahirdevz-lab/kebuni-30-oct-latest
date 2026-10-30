<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$rp = static fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
$q  = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',') ?: '0';
$s  = $d['sales'];
$ui = ['HABIS' => ['danger', 'Habis'], 'KRITIS' => ['danger', 'Di bawah minimum'], 'MENIPIS' => ['warning', 'Menipis']];
$stockBad = ($d['stock_counts']['HABIS'] ?? 0) + ($d['stock_counts']['KRITIS'] ?? 0);
$todo = [];
if (! $d['day']) { $todo[] = ['warning', 'Toko belum dibuka hari ini.', 'admin/pos/day', 'Buka toko']; }
if ((int) $d['qris_pending']['c'] > 0) { $todo[] = ['danger', $d['qris_pending']['c'] . ' pembayaran QRIS/Transfer menunggu verifikasi (' . $rp($d['qris_pending']['a']) . ').', 'admin/pos/sales', 'Verifikasi']; }
if ($d['exceptions_total'] > 0) { $todo[] = ['warning', $d['exceptions_total'] . ' pengecualian hari ini (override/diskon/void/dll).', 'admin/pos/exceptions', 'Tinjau']; }
if ($stockBad > 0) { $todo[] = ['danger', $stockBad . ' produk habis / di bawah stok minimum.', 'admin/stock/reorder', 'Saran pesan']; }
if ($d['broken_drafts'] > 0) { $todo[] = ['warning', $d['broken_drafts'] . ' laporan Broken Stock menunggu persetujuan.', 'admin/stock/broken', 'Setujui']; }
if ($d['opname']) { $todo[] = ['info', 'Stock opname ' . esc($d['opname']['session_no']) . ' berstatus ' . $d['opname']['status'] . '.', 'admin/stock/opname', 'Buka']; }
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <?php if ($d['day']): ?>
        <span class="badge text-bg-success fs-6"><i class="fa-solid fa-store me-1"></i> Toko BUKA · <?= date('d/m/Y', strtotime($d['day']['business_date'])) ?></span>
    <?php else: ?>
        <span class="badge text-bg-secondary fs-6"><i class="fa-solid fa-store-slash me-1"></i> Toko belum dibuka</span>
    <?php endif ?>
    <?php if ($d['last'] && $d['last']['status'] === 'CLOSED'): ?>
        <span class="small text-muted">EOD terakhir <?= date('d/m/Y H:i', strtotime($d['last']['closed_at'])) ?> · selisih kas <?= $rp($d['last']['difference']) ?></span>
    <?php endif ?>
    <button class="btn btn-sm btn-outline-secondary ms-auto" onclick="location.reload()"><i class="fa-solid fa-rotate"></i> Segarkan</button>
</div>

<?php if ($todo): ?>
<div class="card mb-3 border-warning">
    <div class="section-header"><i class="fa-solid fa-bell me-1"></i> Perlu perhatian</div>
    <ul class="list-group list-group-flush">
        <?php foreach ($todo as $t): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center gap-2"><span><span class="badge text-bg-<?= $t[0] ?> me-2">!</span><?= $t[1] ?></span><a class="btn btn-sm btn-outline-primary" href="<?= base_url($t[2]) ?>"><?= $t[3] ?></a></li>
        <?php endforeach ?>
    </ul>
</div>
<?php endif ?>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Penjualan bersih hari ini</div><div class="fs-4 fw-bold"><?= $rp($s['total']) ?></div><div class="small text-muted"><?= $s['count'] ?> transaksi · rata-rata <?= $rp($s['avg']) ?><?= $s['returns'] > 0 ? ' · retur ' . $rp($s['returns']) . ' sudah dikurangkan' : '' ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Laba kotor</div><div class="fs-4 fw-bold text-success"><?= $rp($s['profit']) ?></div><div class="small text-muted">Diskon <?= $rp($s['discount']) ?> · void <?= $s['voids'] ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Kas di laci (buku)</div><div class="fs-4 fw-bold"><?= $rp($d['cash_balance']) ?></div><div class="small text-muted">QRIS belum disetor: <?= $rp($d['qris_clearing']) ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Piutang pelanggan</div><div class="fs-4 fw-bold text-danger"><?= $rp($d['receivable']['a']) ?></div><div class="small text-muted"><?= (int) $d['receivable']['c'] ?> transaksi belum lunas</div></div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8"><div class="card h-100"><div class="section-header"><i class="fa-solid fa-chart-line me-1"></i> Penjualan & laba kotor 7 hari</div><div class="card-body"><canvas id="trend" height="110"></canvas></div></div></div>
    <div class="col-lg-4">
        <div class="card mb-3"><div class="section-header">Metode bayar hari ini</div>
            <ul class="list-group list-group-flush">
                <?php if (! $d['mix']): ?><li class="list-group-item text-muted">Belum ada transaksi.</li><?php endif ?>
                <?php foreach ($d['mix'] as $m): ?><li class="list-group-item d-flex justify-content-between"><span><?= esc($m['m']) ?></span><strong><?= $rp($m['a']) ?></strong></li><?php endforeach ?>
            </ul></div>
        <div class="card"><div class="section-header">Terlaris hari ini</div>
            <ul class="list-group list-group-flush">
                <?php if (! $d['top']): ?><li class="list-group-item text-muted">Belum ada transaksi.</li><?php endif ?>
                <?php foreach ($d['top'] as $r): ?><li class="list-group-item d-flex justify-content-between"><span><?= esc($r['name']) ?> <small class="text-muted">×<?= $q($r['q']) ?></small></span><strong><?= $rp($r['t']) ?></strong></li><?php endforeach ?>
            </ul></div>
    </div>
</div>

<div class="card">
    <div class="section-header d-flex justify-content-between"><span><i class="fa-solid fa-boxes-stacked me-1"></i> Stok perlu perhatian</span><a href="<?= base_url('admin/stock/reorder') ?>" class="small">Lihat semua</a></div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Produk</th><th>Status</th><th class="text-end">Stok</th><th class="text-end">Minimum</th><th class="text-end">Cukup (hari)</th><th class="text-end">Saran pesan</th></tr></thead>
        <tbody>
        <?php if (! $d['stock_urgent']): ?><tr><td colspan="6" class="text-center text-muted py-3">Semua stok aman.</td></tr><?php endif ?>
        <?php foreach ($d['stock_urgent'] as $r): ?>
            <tr><td><?= esc($r['name']) ?></td><td><span class="badge text-bg-<?= $ui[$r['status']][0] ?>"><?= $ui[$r['status']][1] ?></span></td>
                <td class="text-end"><?= $q($r['qty']) ?> <?= esc($r['unit']) ?></td><td class="text-end"><?= $r['min_stock'] !== null ? $q($r['min_stock']) : '-' ?></td>
                <td class="text-end"><?= $r['days_cover'] !== null ? $q($r['days_cover']) : '-' ?></td><td class="text-end fw-bold"><?= $r['suggested'] > 0 ? $q($r['suggested']) . ' ' . esc($r['unit']) : '-' ?></td></tr>
        <?php endforeach ?>
        </tbody></table></div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const T = <?= json_encode(array_map(static fn ($k, $v) => ['d' => date('d/m', strtotime($k))] + $v, array_keys($d['trend']), $d['trend'])) ?>;
new Chart(document.getElementById('trend'), {
    type: 'bar',
    data: { labels: T.map(x => x.d), datasets: [
        { label: 'Penjualan', data: T.map(x => x.total), backgroundColor: '#2563eb' },
        { label: 'Laba kotor', data: T.map(x => x.profit), backgroundColor: '#16a34a' }
    ] },
    options: { responsive: true, plugins: { tooltip: { callbacks: { label: c => c.dataset.label + ': ' + formatRupiah(c.parsed.y) } } }, scales: { y: { beginAtZero: true, ticks: { callback: v => formatRupiah(v) } } } }
});
</script>
<?= $this->endSection() ?>
