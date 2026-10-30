<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<style>
    /* Palet chart (tervalidasi light & dark): slot 1 biru = known, slot 2 oranye = unknown. */
    .viz-root { --viz-s1: #2a78d6; --viz-s2: #eb6834; --viz-grid: #e1e0d9; --viz-axis: #898781; --viz-ink: #0b0b0b; --viz-ink2: #52514e; --viz-surface: #fcfcfb; }
    [data-bs-theme="dark"] .viz-root { --viz-s1: #3987e5; --viz-s2: #d95926; --viz-grid: #2c2c2a; --viz-axis: #898781; --viz-ink: #f0efec; --viz-ink2: #c3c2b7; --viz-surface: #1a1a19; }
    .kpi .val { font-size: 1.5rem; font-weight: 700; }
    .kpi .sub { font-size: .8rem; color: var(--bs-secondary-color); }
    .swatch { display: inline-block; width: 10px; height: 10px; border-radius: 2px; margin-right: 6px; vertical-align: middle; }
    .pl-table td { padding: .35rem .5rem; }
    .pl-table td.text-end, .pl-table th.text-end { white-space: nowrap; }
    .pl-table .total td { font-weight: 700; border-top: 2px solid var(--bs-border-color); }
    .num { font-variant-numeric: tabular-nums; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$rp  = static fn ($v) => ($v < 0 ? '−' : '') . 'Rp ' . number_format(abs((float) $v), 0, ',', '.');
$pct = static fn ($v) => number_format((float) $v, 1, ',', '.') . '%';
$q   = static fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',') ?: '0';
$pl  = $a['pl'];
$of  = static fn ($v) => $pl['revenue'] > 0 ? $pct($v / $pl['revenue'] * 100) : '-';
$trendUi = ['NAIK' => ['fa-arrow-trend-up', 'text-success', 'Naik'], 'TURUN' => ['fa-arrow-trend-down', 'text-danger', 'Turun'], 'STABIL' => ['fa-minus', 'text-secondary', 'Stabil'], 'BARU' => ['fa-star', 'text-primary', 'Baru']];
$quick = [
    'Bulan ini'  => [date('Y-m-01'), date('Y-m-d')],
    'Bulan lalu' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
    '30 hari'    => [date('Y-m-d', strtotime('-29 days')), date('Y-m-d')],
    '7 hari'     => [date('Y-m-d', strtotime('-6 days')), date('Y-m-d')],
];
?>
<div class="viz-root">
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-sm-3"><label class="form-label mb-1">Dari</label><input type="date" name="from" value="<?= esc($a['from']) ?>" class="form-control"></div>
            <div class="col-sm-3"><label class="form-label mb-1">Sampai</label><input type="date" name="to" value="<?= esc($a['to']) ?>" class="form-control"></div>
            <div class="col-sm-2"><button class="btn btn-primary w-100">Tampilkan</button></div>
            <div class="col-sm-4 d-flex flex-wrap gap-1">
                <?php foreach ($quick as $label => [$f, $t]): ?>
                    <a class="btn btn-sm <?= $a['from'] === $f && $a['to'] === $t ? 'btn-secondary' : 'btn-outline-secondary' ?>" href="?from=<?= $f ?>&to=<?= $t ?>"><?= $label ?></a>
                <?php endforeach ?>
            </div>
        </form>
        <div class="small text-muted mt-2">
            Tren dibandingkan dengan <?= date('d/m/Y', strtotime($a['prevFrom'])) ?>–<?= date('d/m/Y', strtotime($a['prevTo'])) ?> (<?= $a['days'] ?> hari).
            <?php if ($a['lastOpname']): ?>
                Opname terakhir diposting: cut-off <strong><?= date('d/m/Y H:i', strtotime($a['lastOpname']['cutoff_at'])) ?></strong> — unknown shrinkage setelah tanggal itu belum terlihat.
            <?php else: ?>
                <span class="text-danger">Belum ada opname yang diposting — unknown shrinkage belum terukur.</span>
            <?php endif ?>
            <?php if ($a['pendingBroken']['count']): ?> · <span class="text-warning"><?= $a['pendingBroken']['count'] ?> Broken Stock belum disetujui (~<?= $rp($a['pendingBroken']['value']) ?>) belum masuk hitungan.</span><?php endif ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card h-100 kpi"><div class="card-body"><div class="sub">Penjualan</div><div class="val num"><?= $rp($pl['revenue']) ?></div><div class="sub"><?= count(array_filter($a['products'], fn ($p) => $p['revenue'] > 0)) ?> produk terjual</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100 kpi"><div class="card-body"><div class="sub">Laba kotor</div><div class="val num"><?= $rp($pl['gross']) ?></div><div class="sub">margin <?= $of($pl['gross']) ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100 kpi"><div class="card-body"><div class="sub">Total penyusutan</div><div class="val num"><?= $rp($pl['shrink']) ?></div><div class="sub"><?= $of($pl['shrink']) ?> dari penjualan · <?= $pl['gross'] > 0 ? $pct($pl['shrink'] / $pl['gross'] * 100) . ' dari laba kotor' : '' ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100 kpi"><div class="card-body"><div class="sub">Laba setelah penyusutan</div><div class="val num <?= $pl['net'] < 0 ? 'text-danger' : '' ?>"><?= $rp($pl['net']) ?></div><div class="sub">margin <?= $of($pl['net']) ?></div></div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="section-header"><i class="fa-solid fa-file-invoice-dollar me-1"></i> Laba rugi ringkas (POS)</div>
            <div class="card-body p-0">
                <table class="table pl-table mb-0">
                    <thead><tr><th></th><th class="text-end">Rp</th><th class="text-end">% penjualan</th></tr></thead>
                    <tbody class="num">
                        <tr><td>Penjualan bersih<?= $pl['returns'] > 0 ? ' <span class="small text-muted">(setelah retur ' . $rp($pl['returns']) . ')</span>' : '' ?></td><td class="text-end"><?= $rp($pl['revenue']) ?></td><td class="text-end">100%</td></tr>
                        <tr><td>− HPP terjual (moving average, setelah retur)</td><td class="text-end"><?= $rp(-$pl['cogs']) ?></td><td class="text-end"><?= $of($pl['cogs']) ?></td></tr>
                        <tr><td>− Biaya barang gratis promo</td><td class="text-end"><?= $rp(-$pl['promo_cost']) ?></td><td class="text-end"><?= $of($pl['promo_cost']) ?></td></tr>
                        <tr class="total"><td>Laba kotor</td><td class="text-end"><?= $rp($pl['gross']) ?></td><td class="text-end"><?= $of($pl['gross']) ?></td></tr>
                        <tr><td><span class="swatch" style="background:var(--viz-s1)"></span>− Known shrinkage (Broken Stock)</td><td class="text-end"><?= $rp(-$pl['known']) ?></td><td class="text-end"><?= $of($pl['known']) ?></td></tr>
                        <tr><td><span class="swatch" style="background:var(--viz-s2)"></span>− Unknown shrinkage (opname)</td><td class="text-end"><?= $rp(-$pl['unknown']) ?></td><td class="text-end"><?= $of($pl['unknown']) ?></td></tr>
                        <tr><td>+ Selisih lebih opname</td><td class="text-end"><?= $rp($pl['surplus']) ?></td><td class="text-end"><?= $of($pl['surplus']) ?></td></tr>
                        <tr class="total"><td>Laba setelah penyusutan</td><td class="text-end <?= $pl['net'] < 0 ? 'text-danger' : '' ?>"><?= $rp($pl['net']) ?></td><td class="text-end"><?= $of($pl['net']) ?></td></tr>
                    </tbody>
                </table>
                <div class="small text-muted px-3 py-2">Belum termasuk biaya operasional (OPEX), gaji, sewa, dll. yang tercatat di modul Keuangan.</div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="section-header"><i class="fa-solid fa-chart-bar me-1"></i> Kontribusi penyusutan</div>
            <div class="card-body">
                <?php if ($pl['shrink'] > 0): ?>
                    <div class="d-flex flex-wrap gap-3 small mb-2" aria-hidden="true">
                        <span><span class="swatch" style="background:var(--viz-s1)"></span>Known <strong class="num"><?= $rp($pl['known']) ?></strong> (<?= $pct($pl['known'] / $pl['shrink'] * 100) ?>)</span>
                        <span><span class="swatch" style="background:var(--viz-s2)"></span>Unknown <strong class="num"><?= $rp($pl['unknown']) ?></strong> (<?= $pct($pl['unknown'] / $pl['shrink'] * 100) ?>)</span>
                    </div>
                    <div style="height:90px"><canvas id="mixChart" role="img" aria-label="Komposisi penyusutan: known <?= $rp($pl['known']) ?>, unknown <?= $rp($pl['unknown']) ?>"></canvas></div>
                    <div class="small text-muted mt-2">Total penyusutan <?= $rp($pl['shrink']) ?> = <?= $of($pl['shrink']) ?> dari penjualan<?= $pl['gross'] > 0 ? ', memakan ' . $pct($pl['shrink'] / $pl['gross'] * 100) . ' laba kotor' : '' ?>.</div>
                <?php else: ?>
                    <div class="text-muted">Belum ada penyusutan pada periode ini.</div>
                <?php endif ?>
                <hr>
                <div class="small fw-semibold mb-1">Rekomendasi produk</div>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($actions as $code => [$label, $desc, $color]): ?>
                        <span class="badge text-bg-<?= $color ?>" title="<?= esc($desc) ?>"><?= $label ?>: <?= (int) ($a['actionCount'][$code] ?? 0) ?></span>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="section-header"><i class="fa-solid fa-table-cells-large me-1"></i> Matriks produk: kecepatan jual × risiko penyusutan</div>
    <div class="card-body">
        <?php $plotted = array_values(array_filter($a['products'], fn ($p) => $p['revenue'] > 0 || $p['shrink'] > 0)); ?>
        <?php if ($plotted): ?>
            <div style="height:360px"><canvas id="matrixChart" role="img" aria-label="Diagram sebar produk: sumbu X penjualan, sumbu Y persentase penyusutan"></canvas></div>
            <div class="small text-muted mt-2">
                Garis vertikal: batas <strong>fast moving</strong> (produk pembentuk <?= (int) \App\Services\ProfitShrinkageService::FAST_SHARE ?>% nilai penjualan, kelas A).
                Garis horizontal: batas <strong>high risk</strong> penyusutan ≥ <?= $q($a['riskPct']) ?>% dari persediaan keluar (Setting).
                Detail per produk ada di tabel di bawah.
            </div>
        <?php else: ?>
            <div class="text-muted">Belum ada penjualan atau penyusutan pada periode ini.</div>
        <?php endif ?>
    </div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-list-check me-1"></i> Keputusan per produk</div>
    <div class="px-3 pt-2 small text-muted">
        <?php foreach ($actions as [$label, $desc, $color]): ?><span class="me-3"><span class="badge text-bg-<?= $color ?>"><?= $label ?></span> <?= esc($desc) ?></span><br class="d-md-none"><?php endforeach ?>
        <?php if ($a['days'] < 28): ?><div class="text-warning mt-1">Periode <?= $a['days'] ?> hari — rekomendasi lebih andal untuk periode ≥ 30 hari.</div><?php endif ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 num">
            <thead><tr>
                <th>Produk</th><th class="text-end">Penjualan</th><th class="text-end">Porsi</th><th class="text-end">Qty/hari</th><th>Tren</th>
                <th class="text-end">Laba kotor</th><th class="text-end">Known</th><th class="text-end">Unknown</th><th class="text-end">% susut</th>
                <th class="text-end">Laba stlh susut</th><th>Kelas</th><th>Rekomendasi</th>
            </tr></thead>
            <tbody>
            <?php if (! $a['products']): ?><tr><td colspan="12" class="text-center text-muted py-3">Belum ada data.</td></tr><?php endif ?>
            <?php foreach ($a['products'] as $p): ?>
                <?php [$aLabel, $aDesc, $aColor] = $actions[$p['action']]; [$ti, $tc, $tl] = $trendUi[$p['trend']]; ?>
                <tr>
                    <td style="min-width:150px"><?= esc($p['product_name']) ?> <span class="small text-muted"><?= esc($p['product_code']) ?></span></td>
                    <td class="text-end"><?= number_format($p['revenue'], 0, ',', '.') ?></td>
                    <td class="text-end"><?= $pct($p['share']) ?></td>
                    <td class="text-end"><?= $q($p['qty_per_day']) ?> <?= esc($p['unit']) ?></td>
                    <td class="<?= $tc ?> text-nowrap"><i class="fa-solid <?= $ti ?> me-1"></i><?= $tl ?><?= $p['trend_pct'] !== null && $p['trend'] !== 'BARU' ? ' <span class="small">' . ($p['trend_pct'] > 0 ? '+' : '') . $pct($p['trend_pct']) . '</span>' : '' ?></td>
                    <td class="text-end"><?= number_format($p['gross'], 0, ',', '.') ?></td>
                    <td class="text-end"><?= number_format($p['known'], 0, ',', '.') ?></td>
                    <td class="text-end"><?= number_format($p['unknown'], 0, ',', '.') ?></td>
                    <td class="text-end <?= $p['high_risk'] ? 'text-danger fw-semibold' : '' ?>"><?= $pct($p['shrink_rate']) ?></td>
                    <td class="text-end <?= $p['net'] < 0 ? 'text-danger fw-semibold' : '' ?>"><?= number_format($p['net'], 0, ',', '.') ?></td>
                    <td class="small text-nowrap"><?= $p['fast'] ? 'Fast' : 'Slow' ?> · <?= $p['high_risk'] ? '<span class="text-danger">High risk</span>' : 'Low risk' ?></td>
                    <td class="text-nowrap"><span class="badge text-bg-<?= $aColor ?>" title="<?= esc($aDesc) ?>"><?= $aLabel ?></span></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    if (typeof Chart === 'undefined') return;
    const PL = <?= json_encode(['known' => $pl['known'], 'unknown' => $pl['unknown']]) ?>;
    const PTS = <?= json_encode(array_map(fn ($p) => [
        'x' => round($p['revenue']), 'y' => round($p['shrink_rate'], 2), 'name' => $p['product_name'],
        'action' => $actions[$p['action']][0], 'net' => round($p['net']), 'shrink' => round($p['shrink']),
    ], $plotted ?? [])) ?>;
    const FAST_CUT = <?= json_encode($a['fastCut']) ?>, RISK = <?= json_encode($a['riskPct']) ?>;
    const rp = v => 'Rp ' + Number(v).toLocaleString('id-ID', { maximumFractionDigits: 0 });
    const css = n => getComputedStyle(document.querySelector('.viz-root')).getPropertyValue(n).trim();
    let charts = [];

    // Garis referensi kuadran (fast moving & high risk) + label kuadran, digambar di bawah titik.
    const refLines = {
        id: 'refLines',
        beforeDatasetsDraw(chart) {
            const { ctx, chartArea: a, scales: { x, y } } = chart;
            ctx.save();
            ctx.strokeStyle = css('--viz-axis'); ctx.setLineDash([4, 4]); ctx.lineWidth = 1;
            if (FAST_CUT !== null) { const px = x.getPixelForValue(FAST_CUT); if (px > a.left && px < a.right) { ctx.beginPath(); ctx.moveTo(px, a.top); ctx.lineTo(px, a.bottom); ctx.stroke(); } }
            const py = y.getPixelForValue(RISK); if (py > a.top && py < a.bottom) { ctx.beginPath(); ctx.moveTo(a.left, py); ctx.lineTo(a.right, py); ctx.stroke(); }
            ctx.setLineDash([]); ctx.fillStyle = css('--viz-ink2'); ctx.font = '12px system-ui, sans-serif';
            ctx.textAlign = 'left'; ctx.fillText('Slow · high risk', a.left + 6, a.top + 14);
            ctx.textAlign = 'right'; ctx.fillText('Fast · high risk', a.right - 6, a.top + 14);
            ctx.textAlign = 'left'; ctx.fillText('Slow · low risk', a.left + 6, a.bottom - 6);
            ctx.textAlign = 'right'; ctx.fillText('Fast · low risk', a.right - 6, a.bottom - 6);
            ctx.restore();
        },
    };

    function draw() {
        charts.forEach(c => c.destroy()); charts = [];
        const grid = css('--viz-grid'), axis = css('--viz-axis'), ink2 = css('--viz-ink2'), surface = css('--viz-surface');
        const tooltip = { backgroundColor: css('--viz-surface'), titleColor: css('--viz-ink'), bodyColor: ink2, borderColor: grid, borderWidth: 1, padding: 10 };

        const mix = document.getElementById('mixChart');
        if (mix) {
            const total = PL.known + PL.unknown;
            charts.push(new Chart(mix, {
                type: 'bar',
                data: { labels: ['Penyusutan'], datasets: [
                    { label: 'Known (Broken Stock)', data: [PL.known], backgroundColor: css('--viz-s1'), borderColor: surface, borderWidth: { right: 2 }, borderSkipped: false, borderRadius: { topLeft: 4, bottomLeft: 4 }, barThickness: 28 },
                    { label: 'Unknown (opname)', data: [PL.unknown], backgroundColor: css('--viz-s2'), borderRadius: { topRight: 4, bottomRight: 4 }, borderSkipped: false, barThickness: 28 },
                ] },
                options: {
                    indexAxis: 'y', responsive: true, maintainAspectRatio: false, animation: false,
                    scales: { x: { stacked: true, max: total, grid: { display: false }, border: { color: axis }, ticks: { color: axis, callback: v => rp(v), maxTicksLimit: 4 } },
                              y: { stacked: true, display: false } },
                    plugins: { legend: { display: false }, tooltip: Object.assign({}, tooltip, { callbacks: { label: c => `${c.dataset.label}: ${rp(c.raw)} (${(c.raw / total * 100).toLocaleString('id-ID', { maximumFractionDigits: 1 })}%)` } }) },
                },
            }));
        }

        // Label nama produk langsung di samping titik (selektif: hanya bila titik sedikit, agar tidak bertumpuk).
        const pointLabels = {
            id: 'pointLabels',
            afterDatasetsDraw(chart) {
                if (PTS.length > 15) return;
                const { ctx } = chart, meta = chart.getDatasetMeta(0);
                ctx.save(); ctx.fillStyle = css('--viz-ink2'); ctx.font = '11px system-ui, sans-serif';
                meta.data.forEach((el, i) => {
                    const right = el.x > chart.chartArea.right - 120;
                    ctx.textAlign = right ? 'right' : 'left';
                    ctx.fillText(PTS[i].name, el.x + (right ? -10 : 10), el.y + 4);
                });
                ctx.restore();
            },
        };

        const mx = document.getElementById('matrixChart');
        if (mx) {
            const maxX = Math.max(...PTS.map(p => p.x), FAST_CUT || 0, 1), maxY = Math.max(...PTS.map(p => p.y), 0);
            charts.push(new Chart(mx, {
                type: 'scatter',
                data: { datasets: [{ label: 'Produk', data: PTS, backgroundColor: css('--viz-s1'), borderColor: surface, borderWidth: 2, pointRadius: 6, pointHoverRadius: 9, pointHitRadius: 12, clip: false }] },
                options: {
                    responsive: true, maintainAspectRatio: false, animation: false,
                    layout: { padding: { top: 8, right: 8 } },
                    scales: {
                        x: { title: { display: true, text: 'Penjualan periode (Rp)', color: ink2 }, beginAtZero: true, suggestedMax: maxX * 1.12, grid: { color: grid }, border: { color: axis }, ticks: { color: axis, callback: v => rp(v), maxTicksLimit: 6 } },
                        y: { title: { display: true, text: 'Penyusutan (% persediaan keluar)', color: ink2 }, beginAtZero: true, suggestedMax: Math.max(maxY * 1.12, RISK * 2, 10), grid: { color: grid }, border: { color: axis }, ticks: { color: axis, callback: v => Math.round(v) + '%' } },
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: Object.assign({}, tooltip, { callbacks: {
                            title: items => items[0].raw.name,
                            label: c => [`Penjualan: ${rp(c.raw.x)}`, `Penyusutan: ${c.raw.y.toLocaleString('id-ID')}% (${rp(c.raw.shrink)})`, `Laba stlh susut: ${rp(c.raw.net)}`, `Rekomendasi: ${c.raw.action}`],
                        } }),
                    },
                },
                plugins: [refLines, pointLabels],
            }));
        }
    }
    draw();
    $(document).on('themechange', () => setTimeout(draw, 0));
})();
</script>
<?= $this->endSection() ?>
