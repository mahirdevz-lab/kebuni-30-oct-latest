<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$rp   = static fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
$cur  = $sales['current'];
$chg  = $sales['omzet_change'];
$hour = (int) date('G');
$greet = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
$statusBadge = ['Draft' => 'bg-secondary', 'Submitted' => 'bg-info text-dark', 'Approved' => 'bg-primary', 'Processing' => 'bg-warning text-dark'];
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><?= $greet ?>, <?= esc(explode(' ', $user['full_name'] ?? '')[0]) ?> 👋</h4>
        <div class="text-muted small"><?= tgl_id(null, 'EEEE, dd MMMM yyyy') ?> &middot; ringkasan bulan <?= tgl_id(null, 'MMMM yyyy') ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('admin/sales-order/create') ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i> Buat Sales Order</a>
        <a href="<?= base_url('admin/price-simulator') ?>" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-calculator me-1"></i> Price Simulator</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="kpi-card">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                <div>
                    <div class="kpi-value"><?= $rp($cur['omzet']) ?></div>
                    <div class="kpi-label">Omzet bulan ini
                        <?php if ($chg !== null): ?>
                            <span class="ms-1 <?= $chg >= 0 ? 'profit-positive' : 'profit-negative' ?>"><i class="fa-solid <?= $chg >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i> <?= abs($chg) ?>%</span>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="kpi-card">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="fa-solid fa-chart-line"></i></div>
                <div>
                    <div class="kpi-value <?= $cur['profit'] >= 0 ? 'profit-positive' : 'profit-negative' ?>"><?= $rp($cur['profit']) ?></div>
                    <div class="kpi-label">Estimasi profit &middot; margin <?= $sales['margin'] ?>%</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="kpi-card">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="fa-solid fa-cart-shopping"></i></div>
                <div>
                    <div class="kpi-value"><?= (int) $cur['orders'] ?> <small class="fs-6 text-muted fw-normal">PO</small></div>
                    <div class="kpi-label"><?= number_format($cur['qty'], 0, ',', '.') ?> Kg terjual bulan ini</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="kpi-card">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                <div>
                    <div class="kpi-value"><?= (int) ($pipeline['Submitted'] ?? 0) ?></div>
                    <div class="kpi-label">PO menunggu approval</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isset($finance)): ?>
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="kpi-card">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="fa-solid fa-arrow-trend-up"></i></div>
                <div>
                    <div class="kpi-value profit-positive"><?= $rp($finance['total_income']) ?></div>
                    <div class="kpi-label">Kas masuk bulan ini</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="kpi-card">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="fa-solid fa-arrow-trend-down"></i></div>
                <div>
                    <div class="kpi-value profit-negative"><?= $rp($finance['total_expense']) ?></div>
                    <div class="kpi-label">Kas keluar bulan ini</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <a href="<?= base_url('admin/finance/receivable') ?>" class="text-reset d-block h-100"><div class="kpi-card">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                <div>
                    <div class="kpi-value"><?= $rp($finance['total_receivable']) ?></div>
                    <div class="kpi-label">Piutang outstanding
                        <?php if ($finance['receivable_overdue']['n'] > 0): ?><span class="badge bg-danger ms-1"><?= (int) $finance['receivable_overdue']['n'] ?> lewat tempo</span><?php endif ?>
                    </div>
                </div>
            </div>
        </div></a>
    </div>
    <div class="col-md-6 col-xl-3">
        <a href="<?= base_url('admin/finance/payable') ?>" class="text-reset d-block h-100"><div class="kpi-card">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                <div>
                    <div class="kpi-value"><?= $rp($finance['total_payable']) ?></div>
                    <div class="kpi-label">Hutang outstanding
                        <?php if ($finance['payable_overdue']['n'] > 0): ?><span class="badge bg-danger ms-1"><?= (int) $finance['payable_overdue']['n'] ?> lewat tempo</span><?php endif ?>
                    </div>
                </div>
            </div>
        </div></a>
    </div>
</div>
<?php endif ?>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="section-header"><i class="fa-solid fa-chart-column me-1"></i> Omzet vs Profit &mdash; 6 Bulan Terakhir</div>
            <div class="card-body"><canvas id="salesChart" height="110"></canvas></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="section-header"><i class="fa-solid fa-diagram-project me-1"></i> Alur Sales Order</div>
            <div class="card-body">
                <?php foreach (['Draft', 'Submitted', 'Approved', 'Processing'] as $st): ?>
                    <a href="<?= base_url('admin/sales-order?status=' . $st) ?>" class="d-flex justify-content-between align-items-center py-2 border-bottom text-reset">
                        <span><span class="badge <?= $statusBadge[$st] ?> me-2">&nbsp;</span><?= $st ?></span>
                        <strong><?= (int) ($pipeline[$st] ?? 0) ?></strong>
                    </a>
                <?php endforeach ?>
                <?php if (isset($attendance)): ?>
                    <div class="mt-3 pt-2">
                        <div class="text-muted small mb-1"><i class="fa-solid fa-camera me-1"></i> Absensi hari ini</div>
                        <div class="d-flex gap-3">
                            <div><strong><?= $attendance['check_in'] ?></strong> <span class="text-muted small">masuk</span></div>
                            <div><strong><?= $attendance['check_out'] ?></strong> <span class="text-muted small">pulang</span></div>
                            <div><strong class="<?= $attendance['late'] ? 'profit-negative' : '' ?>"><?= $attendance['late'] ?></strong> <span class="text-muted small">telat</span></div>
                        </div>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="section-header"><i class="fa-solid fa-file-circle-check me-1"></i> Menunggu Approval
                <a href="<?= base_url('admin/sales-order?status=Submitted') ?>" class="float-end small fw-normal">Lihat semua <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($pending)): ?>
                    <div class="empty-state py-4"><i class="fa-regular fa-circle-check"></i><p class="mb-0 small">Tidak ada PO yang menunggu.</p></div>
                <?php else: foreach ($pending as $p): ?>
                    <a href="<?= base_url('admin/sales-order/' . $p['id']) ?>" class="d-flex justify-content-between px-3 py-2 border-bottom text-reset">
                        <span><div class="fw-semibold small"><?= esc($p['order_no']) ?></div><div class="text-muted small"><?= esc($p['nama_account']) ?></div></span>
                        <span class="text-end small fw-semibold"><?= $rp($p['grand_total']) ?></span>
                    </a>
                <?php endforeach; endif ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="section-header"><i class="fa-solid fa-ranking-star me-1"></i> Top Customer Bulan Ini
                <a href="<?= base_url('admin/report/transaction') ?>" class="float-end small fw-normal">Lihat semua <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($top_customers)): ?>
                    <div class="empty-state py-4"><i class="fa-solid fa-inbox"></i><p class="mb-0 small">Belum ada transaksi bulan ini.</p></div>
                <?php else: foreach ($top_customers as $i => $c): ?>
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                        <span><span class="badge bg-light text-dark border me-2"><?= $i + 1 ?></span><span class="small fw-semibold"><?= esc($c['nama_account']) ?></span></span>
                        <span class="small"><?= $rp($c['omzet']) ?></span>
                    </div>
                <?php endforeach; endif ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="section-header"><i class="fa-solid fa-clock-rotate-left me-1"></i> Aktivitas Terbaru
                <a href="<?= base_url('admin/audit-log') ?>" class="float-end small fw-normal">Lihat semua <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="card-body p-0">
                <?php if (! isset($activity)): ?>
                    <div class="empty-state py-4"><i class="fa-solid fa-lock"></i><p class="mb-0 small">Hanya untuk admin sistem.</p></div>
                <?php elseif (empty($activity)): ?>
                    <div class="empty-state py-4"><i class="fa-solid fa-inbox"></i><p class="mb-0 small">Belum ada aktivitas.</p></div>
                <?php else: foreach ($activity as $a): ?>
                    <div class="px-3 py-2 border-bottom">
                        <div class="small"><strong><?= esc($a['full_name'] ?? 'Sistem') ?></strong> &middot; <?= esc($a['action']) ?></div>
                        <div class="text-muted small"><?= esc(mb_strimwidth($a['description'] ?? '', 0, 60, '…')) ?> &middot; <?= tgl_id($a['created_at'], 'dd MMM HH.mm') ?></div>
                    </div>
                <?php endforeach; endif ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const chartData = <?= json_encode($chart) ?>;
let salesChart = null;

function drawSalesChart() {
    const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const grid = dark ? '#22304d' : '#e6e9f0';
    const text = dark ? '#94a3b8' : '#64748b';
    if (salesChart) salesChart.destroy();
    salesChart = new Chart(document.getElementById('salesChart'), {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [
                { label: 'Omzet', data: chartData.omzet, backgroundColor: '#4f46e5', borderRadius: 6 },
                { label: 'Profit', data: chartData.profit, backgroundColor: '#16a34a', borderRadius: 6 },
            ],
        },
        options: {
            responsive: true,
            plugins: { legend: { labels: { color: text } }, tooltip: { callbacks: { label: c => c.dataset.label + ': ' + formatRupiah(c.parsed.y) } } },
            scales: {
                x: { ticks: { color: text }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { color: text, callback: v => new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(v) }, grid: { color: grid } },
            },
        },
    });
}
drawSalesChart();
$(document).on('themechange', drawSalesChart);
</script>
<?= $this->endSection() ?>
