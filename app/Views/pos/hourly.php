<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<style>
    .heat td { text-align: center; font-size: .72rem; padding: .25rem .1rem; min-width: 34px; border: 1px solid var(--bs-border-color); }
    .heat th { font-size: .7rem; text-align: center; padding: .25rem .1rem; }
    .heat td.d { text-align: left; font-weight: 600; white-space: nowrap; padding-left: .4rem; }
    .gold { border-left: 4px solid #f59e0b; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php

$rp    = static fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
$q     = static fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',') ?: '0';
$t     = $r['totals'];
$ins   = $r['insights'];
$lbl   = static fn (int $h) => sprintf('%02d.00–%02d.00', $h, ($h + 1) % 24);
$quick = [
    '7 hari'    => [date('Y-m-d', strtotime('-6 days')), date('Y-m-d')],
    '30 hari'   => [date('Y-m-d', strtotime('-29 days')), date('Y-m-d')],
    'Bulan ini' => [date('Y-m-01'), date('Y-m-d')],
    'Bulan lalu' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
];
$rq = 'from=' . urlencode($from) . '&to=' . urlencode($to) . '&day=' . urlencode($day);
// rentang jam yang ditampilkan: dari jam transaksi pertama - 1 sampai terakhir + 1
$act = array_values(array_filter($r['hours'], static fn ($h) => $h['noi'] > 0));
$h0  = $act ? max(0, $act[0]['hour'] - 1) : 6;
$h1  = $act ? min(23, end($act)['hour'] + 1) : 22;
?>
<form class="card mb-3" method="get">
    <div class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-2"><label class="form-label mb-1">Dari</label><input type="date" name="from" class="form-control" value="<?= esc($from) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label mb-1">Sampai</label><input type="date" name="to" class="form-control" value="<?= esc($to) ?>"></div>
        <div class="col-md-3"><label class="form-label mb-1">Hari</label>
            <select name="day" class="form-select">
                <option value="ALL" <?= $day === 'ALL' ? 'selected' : '' ?>>Semua hari</option>
                <option value="WEEKDAY" <?= $day === 'WEEKDAY' ? 'selected' : '' ?>>Hari kerja (Senin–Jumat)</option>
                <option value="WEEKEND" <?= $day === 'WEEKEND' ? 'selected' : '' ?>>Akhir pekan (Sabtu–Minggu)</option>
                <?php foreach (\App\Services\PosHourlySalesService::DAYS as $i => $n): ?><option value="<?= $i ?>" <?= $day === (string) $i ? 'selected' : '' ?>><?= $n ?></option><?php endforeach ?>
            </select></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Tampilkan</button></div>
        <div class="col-md-3 d-flex flex-wrap gap-1"><?php foreach ($quick as $l => [$f, $e]): ?><a class="btn btn-sm btn-outline-secondary" href="?from=<?= $f ?>&to=<?= $e ?>&day=<?= esc($day) ?>"><?= $l ?></a><?php endforeach ?></div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">NoI (jumlah invoice)</div><div class="fs-3 fw-bold"><?= number_format($t['noi'], 0, ',', '.') ?></div><div class="small text-muted"><?= $t['active_days'] ?> hari ada transaksi</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Sales</div><div class="fs-3 fw-bold"><?= $rp($t['sales']) ?></div><div class="small text-muted">omzet kotor, sebelum retur</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100 border-success"><div class="card-body"><div class="small text-muted">Buying power</div><div class="fs-3 fw-bold text-success"><?= $rp($t['buying_power']) ?></div><div class="small text-muted">Sales ÷ NoI</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100 gold"><div class="card-body"><div class="small text-muted"><i class="fa-solid fa-trophy text-warning me-1"></i>Golden time</div><div class="fs-3 fw-bold"><?= $ins['golden_hour'] !== null ? $lbl($ins['golden_hour']) : '-' ?></div><div class="small text-muted"><?= $ins['golden_window'] ? 'window 3 jam: ' . sprintf('%02d.00–%02d.00', $ins['golden_window']['start'], $ins['golden_window']['start'] + 3) . ' (' . $q($ins['golden_window']['share']) . '% sales)' : '' ?></div></div></div></div>
</div>

<div class="card mb-3">
    <div class="section-header"><i class="fa-solid fa-lightbulb me-1"></i> Kesimpulan otomatis</div>
    <ul class="list-group list-group-flush">
        <?php foreach ($ins['lines'] as $l): ?><li class="list-group-item"><?= esc($l) ?></li><?php endforeach ?>
    </ul>
</div>

<div class="card mb-3">
    <div class="section-header d-flex justify-content-between flex-wrap gap-2">
        <span><i class="fa-solid fa-chart-column me-1"></i> Sales & NoI per jam</span>
        <span class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= base_url('admin/pos/hourly?print=1&' . $rq) ?>"><i class="fa-solid fa-file-pdf me-1"></i> Cetak / PDF</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('admin/pos/hourly?format=csv&type=hourly&' . $rq) ?>"><i class="fa-solid fa-file-csv me-1"></i> CSV per jam</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('admin/pos/hourly?format=csv&type=daypart&' . $rq) ?>"><i class="fa-solid fa-file-csv me-1"></i> CSV bagian hari</a>
        </span>
    </div>
    <div class="card-body"><?php if (! $t['noi']): ?><div class="text-muted">Belum ada transaksi pada periode ini.</div><?php else: ?><canvas id="hrChart" height="95"></canvas><?php endif ?></div>
</div>

<?php if ($t['noi']): ?>
<div class="card mb-3">
    <div class="section-header"><i class="fa-solid fa-table-cells me-1"></i> Heatmap hari × jam <span class="small text-muted fw-normal">(makin pekat = sales makin besar; angka = NoI)</span></div>
    <div class="table-responsive"><table class="heat table table-sm mb-0">
        <thead><tr><th></th><?php for ($h = $h0; $h <= $h1; $h++): ?><th><?= sprintf('%02d', $h) ?></th><?php endfor ?></tr></thead>
        <tbody>
        <?php foreach (\App\Services\PosHourlySalesService::DAYS as $d => $name): ?>
            <tr><td class="d"><?= $name ?></td>
            <?php for ($h = $h0; $h <= $h1; $h++): $c = $r['heat'][$d][$h]; $a = $r['heat_max'] > 0 ? $c['sales'] / $r['heat_max'] : 0; ?>
                <td title="<?= $name ?> <?= sprintf('%02d.00', $h) ?> — <?= $c['noi'] ?> invoice, <?= $rp($c['sales']) ?>" style="background: rgba(22,163,74,<?= round(0.06 + $a * 0.85, 2) ?>); <?= $a > 0.55 ? 'color:#fff;' : '' ?>"><?= $c['noi'] ?: '' ?></td>
            <?php endfor ?>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table></div>
</div>

<div class="card mb-3">
    <div class="section-header"><i class="fa-solid fa-users-viewfinder me-1"></i> Karakter belanja per bagian hari</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Bagian hari</th><th class="text-end">NoI</th><th class="text-end">Sales</th><th class="text-end">% sales</th><th class="text-end">Buying power</th><th class="text-end">Qty / invoice</th><th>Artikel paling dicari (NoI)</th></tr></thead>
        <tbody>
        <?php foreach ($r['dayparts'] as $d): ?>
            <tr class="<?= $d['noi'] ? '' : 'text-muted' ?>"><td><strong><?= $d['label'] ?></strong> <span class="small text-muted"><?= $d['range'] ?></span></td>
                <td class="text-end"><?= number_format($d['noi'], 0, ',', '.') ?></td><td class="text-end"><?= $rp($d['sales']) ?></td><td class="text-end"><?= $q($d['share']) ?>%</td>
                <td class="text-end"><?= $d['noi'] ? $rp($d['buying_power']) : '-' ?></td><td class="text-end"><?= $d['noi'] ? $q($d['qty_per_invoice']) : '-' ?></td>
                <td class="small"><?= $d['top'] ? esc(implode(', ', array_map(static fn ($p) => $p['name'] . ' (' . $p['noi'] . '×)', $d['top']))) : '-' ?></td></tr>
        <?php endforeach ?>
        </tbody>
    </table></div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-clock me-1"></i> Detail per jam</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Jam</th><th class="text-end">NoI</th><th class="text-end">Rata-rata NoI / hari aktif</th><th class="text-end">Sales</th><th class="text-end">% sales</th><th class="text-end">Buying power</th><th class="text-end">Total qty</th></tr></thead>
        <tbody>
        <?php foreach ($r['hours'] as $x): if ($x['hour'] < $h0 || $x['hour'] > $h1) { continue; } ?>
            <tr class="<?= $x['hour'] === $ins['golden_hour'] ? 'table-warning' : '' ?>"><td><?= $lbl($x['hour']) ?><?= $x['hour'] === $ins['golden_hour'] ? ' <span class="badge text-bg-warning"><i class="fa-solid fa-trophy me-1"></i>Golden</span>' : '' ?></td>
                <td class="text-end"><?= $x['noi'] ?></td><td class="text-end"><?= $q($x['avg_noi_day']) ?></td><td class="text-end"><?= $rp($x['sales']) ?></td><td class="text-end"><?= $q($x['share']) ?>%</td>
                <td class="text-end"><?= $x['noi'] ? $rp($x['buying_power']) : '-' ?></td><td class="text-end"><?= $q($x['qty']) ?></td></tr>
        <?php endforeach ?>
        </tbody>
    </table></div>
    <div class="card-footer small text-muted">Jam diambil dari waktu transaksi. Sales = omzet kotor transaksi Diposting (void tidak dihitung, retur belum dikurangkan). Buying power = Sales ÷ NoI. Bagian hari: dini 00–04, pagi 05–10, siang 11–14, sore 15–17, malam 18–23.</div>
</div>
<?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if ($t['noi']): ?>
<script>
const HR = <?= json_encode(array_values(array_filter($r['hours'], static fn ($x) => $x['hour'] >= $h0 && $x['hour'] <= $h1))) ?>;
const GOLD = <?= json_encode($ins['golden_hour']) ?>;
new Chart(document.getElementById('hrChart'), {
    data: { labels: HR.map(x => String(x.hour).padStart(2, '0') + '.00'), datasets: [
        { type: 'bar', label: 'Sales (Rp)', data: HR.map(x => x.sales), backgroundColor: HR.map(x => x.hour === GOLD ? '#f59e0b' : '#16a34a'), yAxisID: 'y' },
        { type: 'line', label: 'NoI (invoice)', data: HR.map(x => x.noi), borderColor: '#2563eb', backgroundColor: '#2563eb', yAxisID: 'y1', tension: .25 }
    ] },
    options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { callback: v => formatRupiah(v) } }, y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { precision: 0 } } } }
});
</script>
<?php endif ?>
<?= $this->endSection() ?>
