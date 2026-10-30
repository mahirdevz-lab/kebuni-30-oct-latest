<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<style>
    .kpi-card { border: none; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,.06); overflow: hidden; }
    .kpi-card .kpi-accent { height: 4px; }
    .kpi-card .kpi-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
    .kpi-value { font-size: 1.5rem; font-weight: 700; letter-spacing: -0.02em; }
    .kpi-trend { font-size: .78rem; font-weight: 600; }
    .kpi-trend.up { color: #16a34a; }
    .kpi-trend.down { color: #dc2626; }
    .chart-card { border: none; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,.06); }
    .chart-card .section-header { border-radius: 14px 14px 0 0; }
    .quadrant-legend { display: flex; flex-wrap: wrap; gap: .5rem; font-size: .78rem; }
    .quadrant-legend .badge-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 5px; }
    .margin-badge { font-size: .72rem; padding: .25em .55em; border-radius: 6px; font-weight: 600; }
    .margin-badge.low { background: #fee2e2; color: #b91c1c; }
    .margin-badge.mid { background: #fef3c7; color: #92400e; }
    .margin-badge.high { background: #dcfce7; color: #15803d; }
</style>

<div class="row g-3 mb-3">
    <div class="col-md-2">
        <label class="form-label">Dari Tanggal</label>
        <input type="date" id="filterFrom" class="form-control">
    </div>
    <div class="col-md-2">
        <label class="form-label">Sampai Tanggal</label>
        <input type="date" id="filterTo" class="form-control">
    </div>
    <div class="col-md-2">
        <label class="form-label">Status Order</label>
        <select id="filterStatus" class="form-select">
            <option value="">Semua Status Valid</option>
            <option value="Submitted">Submitted</option>
            <option value="Approved">Approved</option>
            <option value="Processing">Processing</option>
            <option value="Completed">Completed</option>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label">Category</label>
        <select id="filterCategory" class="form-select">
            <option value="">Semua Category</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label">Window Moving Average</label>
        <select id="filterWindow" class="form-select">
            <option value="3">3 titik data</option>
            <option value="7" selected>7 titik data</option>
            <option value="14">14 titik data</option>
            <option value="30">30 titik data</option>
        </select>
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <button type="button" class="btn btn-primary w-100" id="btnApplyFilter">
            <i class="fa-solid fa-filter me-1"></i> Terapkan
        </button>
    </div>
</div>

<!-- KPI CARDS -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="kpi-accent" style="background:#4f46e5"></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Total Omzet</div>
                        <div class="kpi-value" id="kpiOmzet">-</div>
                        <div class="kpi-trend" id="kpiOmzetTrend"></div>
                    </div>
                    <div class="kpi-icon" style="background:#eef2ff;color:#4f46e5"><i class="fa-solid fa-sack-dollar"></i></div>
                </div>
                <div id="sparkOmzet" class="mt-2"></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="kpi-accent" style="background:#dc2626"></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Total COGS</div>
                        <div class="kpi-value" id="kpiCogs">-</div>
                        <div class="kpi-trend text-muted" id="kpiCogsSub"></div>
                    </div>
                    <div class="kpi-icon" style="background:#fef2f2;color:#dc2626"><i class="fa-solid fa-boxes-packing"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="kpi-accent" style="background:#16a34a"></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Total Profit</div>
                        <div class="kpi-value" id="kpiProfit">-</div>
                        <div class="kpi-trend text-muted" id="kpiProfitSub"></div>
                    </div>
                    <div class="kpi-icon" style="background:#f0fdf4;color:#16a34a"><i class="fa-solid fa-coins"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="kpi-accent" style="background:#0891b2"></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Rata-rata Margin (MA)</div>
                        <div class="kpi-value" id="kpiMargin">-</div>
                        <div class="kpi-trend" id="kpiMarginTrend"></div>
                    </div>
                    <div class="kpi-icon" style="background:#ecfeff;color:#0891b2"><i class="fa-solid fa-percent"></i></div>
                </div>
                <div id="sparkMargin" class="mt-2"></div>
            </div>
        </div>
    </div>
</div>

<!-- TREND CHART -->
<div class="card chart-card mb-4">
    <div class="section-header"><i class="fa-solid fa-chart-line me-1"></i> Moving Average Margin Trend</div>
    <div class="card-body">
        <div id="chartTrend"></div>
    </div>
</div>

<!-- QUADRANT CHART -->
<div class="card chart-card mb-4">
    <div class="section-header"><i class="fa-solid fa-braille me-1"></i> Product Performance Matrix — Omzet vs Margin</div>
    <div class="card-body">
        <p class="text-muted small mb-2">Ukuran bubble = besar profit. Warna = kuadran. Hover tiap bubble buat detail produk.</p>
        <div id="chartQuadrant"></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card chart-card mb-4">
            <div class="section-header"><i class="fa-solid fa-chart-pie me-1"></i> Kontribusi Profit per Produk</div>
            <div class="card-body">
                <div id="chartDonut"></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card chart-card mb-4">
            <div class="section-header"><i class="fa-solid fa-layer-group me-1"></i> Omzet vs COGS vs Profit (Top Produk)</div>
            <div class="card-body">
                <div id="chartCompare"></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card chart-card mb-4">
            <div class="section-header"><i class="fa-solid fa-arrow-trend-down me-1"></i> Margin Terendah (perlu review harga)</div>
            <div class="card-body">
                <div id="chartLowest"></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card chart-card mb-4">
            <div class="section-header"><i class="fa-solid fa-arrow-trend-up me-1"></i> Margin Tertinggi</div>
            <div class="card-body">
                <div id="chartHighest"></div>
            </div>
        </div>
    </div>
</div>

<div class="card chart-card">
    <div class="section-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-table me-1"></i> Detail Margin per Produk</span>
        <span>
            <button type="button" class="btn btn-sm btn-success" id="btnExportExcel">
                <i class="fa-solid fa-file-excel me-1"></i> Export Excel
            </button>
            <button type="button" class="btn btn-sm btn-danger" id="btnExportPdf">
                <i class="fa-solid fa-file-pdf me-1"></i> Export PDF
            </button>
        </span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Product</th>
                        <th class="text-end">Qty Terjual</th>
                        <th class="text-end">Omzet</th>
                        <th class="text-end">COGS</th>
                        <th class="text-end">Profit</th>
                        <th class="text-end">Margin %</th>
                        <th class="text-center">Jml Order</th>
                        <th class="text-center">Kuadran</th>
                    </tr>
                </thead>
                <tbody id="marginTableBody">
                    <tr><td colspan="9" class="text-center text-muted py-3">Belum ada data.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.45.1/apexcharts.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script>
const ENDPOINT = {
    trend:   "<?= base_url('admin/dashboard/margin/trend') ?>",
    ranking: "<?= base_url('admin/dashboard/margin/ranking') ?>",
};

const PALETTE = { primary: '#4f46e5', danger: '#dc2626', success: '#16a34a', warning: '#f59e0b', info: '#0891b2', gray: '#94a3b8' };

// Deteksi dark mode (sinkron dengan app-ui.js: data-bs-theme di <html>)
const isDarkMode = () => document.documentElement.getAttribute('data-bs-theme') === 'dark';
// Warna teks chart ngikutin tema; background dibiarkan transparan (jangan pakai theme.mode bawaan).
const apexForeColor = () => isDarkMode() ? '#e2e8f0' : '#475569';
const apexGridColor = () => isDarkMode() ? 'rgba(148,163,184,.15)' : 'rgba(100,116,139,.15)';

let charts = {};
let latestRows = [];

function formatRupiah(num) {
    return 'Rp ' + Number(num || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
}
function formatCompact(num) {
    return new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 }).format(num || 0);
}
function currentFilters() {
    return {
        from: $('#filterFrom').val(),
        to: $('#filterTo').val(),
        status: $('#filterStatus').val(),
        category_id: $('#filterCategory').val(),
        window: $('#filterWindow').val(),
    };
}
function renderOrUpdate(id, options) {
    if (charts[id]) { charts[id].destroy(); }
    // Inject warna teks + grid + tooltip ngikutin tema (background tetap transparan)
    options.chart = options.chart || {};
    options.chart.foreColor = apexForeColor();
    options.chart.background = 'transparent';
    options.grid = options.grid || {};
    options.grid.borderColor = apexGridColor();
    options.tooltip = options.tooltip || {};
    options.tooltip.theme = isDarkMode() ? 'dark' : 'light';
    charts[id] = new ApexCharts(document.querySelector('#' + id), options);
    charts[id].render();
}
// Update warna saat theme berubah (tanpa destroy)
$(document).on('themechange', function () {
    Object.keys(charts).forEach(id => {
        if (charts[id]) {
            charts[id].updateOptions({
                chart: { foreColor: apexForeColor(), background: 'transparent' },
                grid: { borderColor: apexGridColor() },
                tooltip: { theme: isDarkMode() ? 'dark' : 'light' },
            }, false, true);
        }
    });
});
// mode 'pts': selisih poin persentase (untuk margin %), mode 'pct': perubahan relatif % (untuk nominal Rupiah).
function trendBadge(values, mode = 'pts') {
    if (values.length < 2) return { text: '-', cls: '' };
    const first = values[0], last = values[values.length - 1];
    const diff = last - first;
    const cls = diff >= 0 ? 'up' : 'down';
    const icon = diff >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
    if (mode === 'pct') {
        if (first === 0) return { text: '-', cls: '' };
        return { text: `<i class="fa-solid ${icon}"></i> ${Math.abs(diff / first * 100).toFixed(1)}% dari hari pertama`, cls };
    }
    return { text: `<i class="fa-solid ${icon}"></i> ${Math.abs(diff).toFixed(2)} pts periode ini`, cls };
}

// Margin keseluruhan terbobot omzet = total profit / total omzet (bukan rata-rata sederhana persentase per produk).
function overallMargin(rows) {
    const omzet = rows.reduce((a, r) => a + r.omzet, 0);
    const profit = rows.reduce((a, r) => a + r.profit, 0);
    return omzet > 0 ? (profit / omzet) * 100 : 0;
}

// ---------------- TREND ----------------
function renderTrendChart(data) {
    renderOrUpdate('chartTrend', {
        chart: { type: 'line', height: 340, toolbar: { show: true }, fontFamily: 'inherit' },
        series: [
            { name: 'Margin Harian %', type: 'column', data: data.margin_percent },
            { name: `Moving Average (${data.window} titik)`, type: 'line', data: data.moving_average },
        ],
        stroke: { width: [0, 3], curve: 'smooth' },
        colors: [PALETTE.gray, PALETTE.primary],
        fill: { opacity: [0.35, 1], type: ['solid', 'solid'] },
        xaxis: { categories: data.labels, labels: { rotate: -45 } },
        yaxis: { labels: { formatter: (v) => v.toFixed(1) + '%' } },
        markers: { size: [0, 3] },
        legend: { position: 'top' },
        tooltip: { y: { formatter: (v) => v.toFixed(2) + '%' } },
    });

    renderOrUpdate('sparkOmzet', {
        chart: { type: 'area', height: 45, sparkline: { enabled: true } },
        series: [{ name: 'Omzet', data: data.omzet }],
        stroke: { width: 2, curve: 'smooth' },
        fill: { opacity: 0.25 },
        colors: [PALETTE.primary],
        tooltip: { y: { formatter: (v) => formatRupiah(v) } },
    });

    renderOrUpdate('sparkMargin', {
        chart: { type: 'area', height: 45, sparkline: { enabled: true } },
        series: [{ name: 'Margin MA', data: data.moving_average }],
        stroke: { width: 2, curve: 'smooth' },
        fill: { opacity: 0.25 },
        colors: [PALETTE.info],
        tooltip: { y: { formatter: (v) => v.toFixed(2) + '%' } },
    });

    // Omzet, COGS & Profit diambil dari 1 sumber (trend) yang memakai filter sama persis, agar konsisten satu sama lain.
    if (data.omzet.length) {
        $('#kpiOmzet').text(formatRupiah(data.totals.omzet));
        $('#kpiCogs').text(formatRupiah(data.totals.cogs));
        $('#kpiProfit').text(formatRupiah(data.totals.profit));
        const t = trendBadge(data.omzet, 'pct');
        $('#kpiOmzetTrend').html(t.text).attr('class', 'kpi-trend ' + t.cls);
    } else {
        $('#kpiOmzet').text('-');
        $('#kpiCogs').text('Rp 0');
        $('#kpiProfit').text('Rp 0');
        $('#kpiOmzetTrend').html('');
    }

    if (data.moving_average.length) {
        const lastMA = data.moving_average[data.moving_average.length - 1];
        $('#kpiMargin').text(lastMA.toFixed(2) + '%');
        const t = trendBadge(data.moving_average);
        $('#kpiMarginTrend').html(t.text).attr('class', 'kpi-trend ' + t.cls);
    } else {
        $('#kpiMargin').text('-');
        $('#kpiMarginTrend').html('');
    }
}

// ---------------- RANKING (bar, quadrant, donut, compare, table) ----------------
function marginBadgeClass(pct) {
    if (pct < 15) return 'low';
    if (pct > 30) return 'high';
    return 'mid';
}
function quadrantLabel(row, avgOmzet, avgMargin) {
    const highOmzet = row.omzet >= avgOmzet;
    const highMargin = row.margin_percent >= avgMargin;
    if (highOmzet && highMargin) return { text: 'Bintang', color: PALETTE.success };
    if (!highOmzet && highMargin) return { text: 'Niche', color: PALETTE.info };
    if (highOmzet && !highMargin) return { text: 'Volume Driver', color: PALETTE.warning };
    return { text: 'Perlu Perhatian', color: PALETTE.danger };
}

function renderBarChart(id, rows, color) {
    renderOrUpdate(id, {
        chart: { type: 'bar', height: 300, toolbar: { show: false } },
        series: [{ name: 'Margin %', data: rows.map(r => r.margin_percent) }],
        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '55%' } },
        colors: [color],
        dataLabels: { formatter: (v) => v.toFixed(1) + '%' },
        xaxis: { categories: rows.map(r => r.product_name), labels: { formatter: (v) => v + '%' } },
        grid: { borderColor: '#f1f5f9' },
    });
}

function renderQuadrantChart(rows) {
    const avgOmzet = rows.reduce((a, r) => a + r.omzet, 0) / (rows.length || 1);
    const avgMargin = overallMargin(rows);

    // Kelompokkan per kuadran biar warnanya beda & gampang dibaca
    const quads = { star: [], niche: [], volume: [], dog: [] };
    const quadRows = { star: [], niche: [], volume: [], dog: [] };
    rows.forEach(r => {
        const hiOmzet = r.omzet >= avgOmzet, hiMargin = r.margin_percent >= avgMargin;
        const q = hiOmzet && hiMargin ? 'star' : (!hiOmzet && hiMargin ? 'niche' : (hiOmzet && !hiMargin ? 'volume' : 'dog'));
        quads[q].push({ x: r.omzet, y: parseFloat(r.margin_percent.toFixed(2)), z: Math.max(r.profit, 1) });
        quadRows[q].push(r);
    });
    const quadMeta = {
        star:   { label: 'Bintang (omzet & margin tinggi)', color: '#16a34a' },
        niche:  { label: 'Niche (margin tinggi, omzet kecil)', color: '#0891b2' },
        volume: { label: 'Volume Driver (omzet tinggi, margin tipis)', color: '#f59e0b' },
        dog:    { label: 'Perlu Perhatian (omzet & margin rendah)', color: '#dc2626' },
    };
    const series = Object.keys(quads).filter(k => quads[k].length).map(k => ({ name: quadMeta[k].label, data: quads[k] }));

    renderOrUpdate('chartQuadrant', {
        chart: { type: 'bubble', height: 420, toolbar: { show: true } },
        series,
        colors: Object.keys(quads).filter(k => quads[k].length).map(k => quadMeta[k].color),
        fill: { opacity: 0.6 },
        dataLabels: { enabled: false },
        xaxis: {
            title: { text: 'Omzet →' },
            labels: { formatter: (v) => formatCompact(v) },
        },
        yaxis: {
            title: { text: 'Margin % →' },
            labels: { formatter: (v) => v.toFixed(0) + '%' },
        },
        annotations: {
            xaxis: [{ x: avgOmzet, borderColor: '#94a3b8', strokeDashArray: 5, label: { text: 'Rata-rata Omzet', position: 'top', style: { fontSize: '11px', background: '#94a3b8', color: '#fff' } } }],
            yaxis: [{ y: avgMargin, borderColor: '#94a3b8', strokeDashArray: 5, label: { text: 'Rata-rata Margin', position: 'top', style: { fontSize: '11px', background: '#94a3b8', color: '#fff' } } }],
        },
        legend: { position: 'bottom', fontSize: '12px' },
        tooltip: {
            custom: function ({ seriesIndex, dataPointIndex }) {
                const key = Object.keys(quads).filter(k => quads[k].length)[seriesIndex];
                const r = quadRows[key][dataPointIndex];
                return `<div class="p-2" style="min-width:180px">
                    <div class="fw-semibold">${r.product_name}</div>
                    <div class="small text-muted">${quadMeta[key].label}</div>
                    <div>Omzet: ${formatRupiah(r.omzet)}</div>
                    <div>Margin: ${r.margin_percent.toFixed(2)}%</div>
                    <div>Profit: ${formatRupiah(r.profit)}</div>
                </div>`;
            },
        },
    });
}

function renderDonutChart(rows) {
    const sorted = [...rows].sort((a, b) => b.profit - a.profit);
    const top = sorted.slice(0, 8);
    const restSum = sorted.slice(8).reduce((a, r) => a + r.profit, 0);

    const labels = top.map(r => r.product_name);
    const series = top.map(r => Math.max(r.profit, 0));
    if (restSum > 0) { labels.push('Lainnya'); series.push(restSum); }

    renderOrUpdate('chartDonut', {
        chart: { type: 'donut', height: 320 },
        series,
        labels,
        legend: { position: 'bottom', fontSize: '11px' },
        dataLabels: { formatter: (val) => val.toFixed(1) + '%' },
        tooltip: { y: { formatter: (v) => formatRupiah(v) } },
    });
}

function renderCompareChart(rows) {
    const top = [...rows].sort((a, b) => b.omzet - a.omzet).slice(0, 8);

    renderOrUpdate('chartCompare', {
        chart: { type: 'bar', height: 340, toolbar: { show: false } },
        series: [
            { name: 'Omzet', data: top.map(r => r.omzet) },
            { name: 'COGS', data: top.map(r => r.total_cogs) },
            { name: 'Profit', data: top.map(r => r.profit) },
        ],
        colors: [PALETTE.primary, PALETTE.danger, PALETTE.success],
        plotOptions: { bar: { columnWidth: '60%', borderRadius: 4, dataLabels: { position: 'top' } } },
        dataLabels: {
            enabled: true,
            formatter: (v) => formatCompact(v),
            offsetY: -18,
            style: { fontSize: '10px', colors: [apexForeColor()] },
        },
        xaxis: { categories: top.map(r => r.product_name), labels: { rotate: -45, style: { fontSize: '11px' } } },
        yaxis: { labels: { formatter: (v) => formatCompact(v) } },
        legend: { position: 'top' },
        tooltip: {
            shared: true, intersect: false,
            y: { formatter: (v) => formatRupiah(v) },
            custom: function ({ series, seriesIndex, dataPointIndex }) {
                const r = top[dataPointIndex];
                const m = r.omzet > 0 ? (r.profit / r.omzet * 100).toFixed(1) : 0;
                return `<div class="p-2" style="min-width:190px">
                    <div class="fw-semibold mb-1">${r.product_name}</div>
                    <div>Omzet: <b>${formatRupiah(r.omzet)}</b></div>
                    <div>COGS: <b>${formatRupiah(r.total_cogs)}</b></div>
                    <div>Profit: <b class="${r.profit >= 0 ? 'text-success' : 'text-danger'}">${formatRupiah(r.profit)}</b></div>
                    <div class="text-muted small">Margin: ${m}%</div>
                </div>`;
            },
        },
    });
}

function renderTable(rows) {
    latestRows = rows;

    if (!rows.length) {
        $('#marginTableBody').html('<tr><td colspan="9" class="text-center text-muted py-3">Belum ada data.</td></tr>');
        return;
    }

    const avgOmzet = rows.reduce((a, r) => a + r.omzet, 0) / rows.length;
    const avgMargin = overallMargin(rows);

    $('#marginTableBody').html(rows.map(r => {
        const badgeCls = marginBadgeClass(r.margin_percent);
        const q = quadrantLabel(r, avgOmzet, avgMargin);
        return `
            <tr>
                <td>${r.product_code}</td>
                <td>${r.product_name}</td>
                <td class="text-end">${Number(r.total_qty).toLocaleString('id-ID')} ${r.unit}</td>
                <td class="text-end">${formatRupiah(r.omzet)}</td>
                <td class="text-end">${formatRupiah(r.total_cogs)}</td>
                <td class="text-end">${formatRupiah(r.profit)}</td>
                <td class="text-end"><span class="margin-badge ${badgeCls}">${r.margin_percent.toFixed(2)}%</span></td>
                <td class="text-center">${r.jumlah_order}</td>
                <td class="text-center"><span class="badge" style="background:${q.color}">${q.text}</span></td>
            </tr>
        `;
    }).join(''));
}

function renderRanking(payload) {
    renderBarChart('chartLowest', payload.lowest, PALETTE.danger);
    renderBarChart('chartHighest', payload.highest, PALETTE.success);
    renderQuadrantChart(payload.all);
    renderDonutChart(payload.all);
    renderCompareChart(payload.all);
    renderTable(payload.all);

    const avgMargin = overallMargin(payload.all);
    const belowAvg = payload.all.filter(r => r.margin_percent < avgMargin).length;
    $('#kpiCogsSub').text(payload.all.length + ' produk dianalisa');
    $('#kpiProfitSub').text(`Margin keseluruhan ${avgMargin.toFixed(2)}% · ${belowAvg} produk di bawahnya perlu direview`);
}

// ---------------- LOAD ----------------
function loadDashboard() {
    const filters = currentFilters();
    showLoading();

    Promise.all([
        $.get(ENDPOINT.trend, filters),
        $.get(ENDPOINT.ranking, filters),
    ]).then(([trendRes, rankRes]) => {
        if (trendRes.success) renderTrendChart(trendRes.data);
        if (rankRes.success) renderRanking(rankRes.data);
    }).catch(() => {
        showToast('Gagal memuat data margin.', 'error');
    }).finally(hideLoading);
}

// ---------------- EXPORT ----------------
function exportRowsForFile() {
    return latestRows.map(r => ({
        'Code': r.product_code,
        'Product': r.product_name,
        'Qty Terjual': r.total_qty,
        'Unit': r.unit,
        'Omzet': r.omzet,
        'COGS': r.total_cogs,
        'Profit': r.profit,
        'Margin %': r.margin_percent,
        'Jumlah Order': r.jumlah_order,
    }));
}

$('#btnExportExcel').on('click', function () {
    if (!latestRows.length) { showToast('Belum ada data untuk di-export.', 'warning'); return; }
    const ws = XLSX.utils.json_to_sheet(exportRowsForFile());
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Margin Analysis');
    XLSX.writeFile(wb, `margin-analysis-${new Date().toISOString().slice(0, 10)}.xlsx`);
});

$('#btnExportPdf').on('click', function () {
    if (!latestRows.length) { showToast('Belum ada data untuk di-export.', 'warning'); return; }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape' });

    doc.setFontSize(14);
    doc.text('Margin Analysis Report', 14, 15);
    doc.setFontSize(9);
    doc.setTextColor(120);
    doc.text(`Digenerate: ${new Date().toLocaleString('id-ID')}`, 14, 21);

    doc.autoTable({
        startY: 26,
        head: [['Code', 'Product', 'Qty', 'Unit', 'Omzet', 'COGS', 'Profit', 'Margin %', 'Jml Order']],
        body: latestRows.map(r => [
            r.product_code, r.product_name, r.total_qty, r.unit,
            formatRupiah(r.omzet), formatRupiah(r.total_cogs), formatRupiah(r.profit),
            r.margin_percent.toFixed(2) + '%', r.jumlah_order,
        ]),
        styles: { fontSize: 8 },
        headStyles: { fillColor: [79, 70, 229] },
    });

    doc.save(`margin-analysis-${new Date().toISOString().slice(0, 10)}.pdf`);
});

$(function () {
    $('#btnApplyFilter').on('click', loadDashboard);
    loadDashboard();
});
</script>
<?= $this->endSection() ?>