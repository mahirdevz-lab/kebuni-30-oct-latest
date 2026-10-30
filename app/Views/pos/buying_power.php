<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$rp    = static fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
$q     = static fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',') ?: '0';
$o     = $r['overall'];
$quick = [
    'Bulan ini'  => [date('Y-m-01'), date('Y-m-d')],
    'Bulan lalu' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
    '30 hari'    => [date('Y-m-d', strtotime('-29 days')), date('Y-m-d')],
    '7 hari'     => [date('Y-m-d', strtotime('-6 days')), date('Y-m-d')],
];
$rq = 'from=' . urlencode($from) . '&to=' . urlencode($to);
?>
<form class="card mb-3" method="get">
    <div class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-3"><label class="form-label mb-1">Dari</label><input type="date" name="from" class="form-control" value="<?= esc($from) ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label mb-1">Sampai</label><input type="date" name="to" class="form-control" value="<?= esc($to) ?>"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Tampilkan</button></div>
        <div class="col-md-4 d-flex flex-wrap gap-1">
            <?php foreach ($quick as $lbl => [$f, $t]): ?><a class="btn btn-sm btn-outline-secondary" href="?from=<?= $f ?>&to=<?= $t ?>"><?= $lbl ?></a><?php endforeach ?>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">NoI (jumlah invoice)</div><div class="fs-3 fw-bold"><?= number_format($o['noi'], 0, ',', '.') ?></div><div class="small text-muted">transaksi diposting</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Sales</div><div class="fs-3 fw-bold"><?= $rp($o['sales']) ?></div><div class="small text-muted">omzet kotor, sebelum retur</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100 border-success"><div class="card-body"><div class="small text-muted">Buying power (Sales ÷ NoI)</div><div class="fs-3 fw-bold text-success"><?= $rp($o['buying_power']) ?></div><div class="small text-muted">rata-rata belanja per invoice</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Isi keranjang rata-rata</div><div class="fs-3 fw-bold"><?= $q($o['lines_per_invoice']) ?> <span class="fs-6 fw-normal">jenis</span></div><div class="small text-muted"><?= $q($o['qty_per_invoice']) ?> qty per invoice</div></div></div></div>
</div>

<div class="card mb-3">
    <div class="section-header"><i class="fa-solid fa-chart-line me-1"></i> Buying power per hari</div>
    <div class="card-body"><?php if (! $r['daily']): ?><div class="text-muted">Belum ada transaksi pada periode ini.</div><?php else: ?><canvas id="bpChart" height="90"></canvas><?php endif ?></div>
</div>

<div class="card mb-3">
    <div class="section-header d-flex justify-content-between flex-wrap gap-2">
        <span><i class="fa-solid fa-basket-shopping me-1"></i> NoI by article — berapa invoice &amp; berapa qty tiap produk</span>
        <span class="d-flex gap-2">
            <input type="search" id="bpFind" class="form-control form-control-sm" placeholder="Cari produk…" style="width:180px">
            <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= base_url('admin/pos/buying-power?print=1&' . $rq) ?>"><i class="fa-solid fa-file-pdf me-1"></i> Cetak / PDF</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('admin/pos/buying-power?format=csv&type=product&' . $rq) ?>"><i class="fa-solid fa-file-csv me-1"></i> CSV produk</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('admin/pos/buying-power?format=csv&type=daily&' . $rq) ?>"><i class="fa-solid fa-file-csv me-1"></i> CSV harian</a>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0" id="bpTable">
            <thead><tr><th>Produk</th><th class="text-end" title="Number of Invoice yang memuat artikel. Tinggi = most wanted item">NoI</th><th class="text-end">Penetrasi</th><th class="text-end">Total qty</th><th class="text-end">Total sales</th><th class="text-end">Rata-rata qty / invoice</th><th class="text-end">Rata-rata sales / invoice</th><th class="text-end" title="Rata-rata total invoice yang memuat produk ini">Basket size</th></tr></thead>
            <tbody>
            <?php if (! $r['products']): ?><tr><td colspan="8" class="text-center text-muted py-3">Belum ada penjualan.</td></tr><?php endif ?>
            <?php foreach ($r['products'] as $i => $p): ?>
                <tr data-name="<?= esc(mb_strtolower($p['name'] . ' ' . $p['code'])) ?>">
                    <td><?= esc($p['name']) ?> <span class="small text-muted"><?= esc($p['code']) ?></span><?= $i < 3 && $p['noi'] > 1 ? ' <span class="badge text-bg-warning" title="NoI by article tertinggi"><i class="fa-solid fa-fire me-1"></i>Most wanted</span>' : '' ?></td>
                    <td class="text-end fw-semibold"><?= number_format($p['noi'], 0, ',', '.') ?>×</td>
                    <td class="text-end"><?= $q($p['penetration']) ?>%</td>
                    <td class="text-end"><?= $q($p['qty']) ?> <?= esc($p['unit']) ?><?= $p['free_qty'] > 0 ? '<div class="small text-success">+' . $q($p['free_qty']) . ' gratis</div>' : '' ?></td>
                    <td class="text-end"><?= $rp($p['sales']) ?></td>
                    <td class="text-end"><?= $q($p['avg_qty']) ?> <?= esc($p['unit']) ?></td>
                    <td class="text-end"><?= $rp($p['avg_sales']) ?></td>
                    <td class="text-end"><?= $rp($p['basket']) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">
        <b>NoI</b> = jumlah invoice (transaksi). <b>Buying power / basket size</b> = Sales ÷ NoI. <b>NoI by article</b> = jumlah invoice yang memuat artikel itu; <b>NoI tinggi = most wanted item</b> (artikel yang paling dicari pelanggan). <b>Penetrasi</b> = NoI artikel ÷ NoI total.
        <b>Basket size</b> per produk = rata-rata total belanja pada invoice yang berisi produk itu (produk pemicu belanja besar punya angka tinggi). Void tidak dihitung; retur belum dikurangkan.
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$('#bpFind').on('input', function () { const q = this.value.trim().toLowerCase(); $('#bpTable tbody tr[data-name]').each(function () { $(this).toggle(!q || String($(this).data('name')).includes(q)); }); });
<?php if ($r['daily']): ?>
const D = <?= json_encode(array_map(static fn ($d) => ['d' => date('d/m', strtotime($d['date'])), 'bp' => $d['buying_power'], 'noi' => $d['noi']], $r['daily'])) ?>;
new Chart(document.getElementById('bpChart'), {
    data: { labels: D.map(x => x.d), datasets: [
        { type: 'line', label: 'Buying power (Rp)', data: D.map(x => x.bp), borderColor: '#16a34a', backgroundColor: '#16a34a', yAxisID: 'y', tension: .25 },
        { type: 'bar', label: 'NoI (invoice)', data: D.map(x => x.noi), backgroundColor: '#93c5fd', yAxisID: 'y1' }
    ] },
    options: { responsive: true, scales: { y: { beginAtZero: true, position: 'left', ticks: { callback: v => formatRupiah(v) } }, y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } } } }
});
<?php endif ?>
</script>
<?= $this->endSection() ?>
