<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted">Dari Tanggal</label>
                <input type="date" name="from" id="from" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Sampai Tanggal</label>
                <input type="date" name="to" id="to" class="form-control form-control-sm" value="<?= date('Y-m-t') ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Terapkan</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="kpi-card">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="fa-solid fa-arrow-trend-up"></i></div>
                <div>
                    <div class="kpi-value text-success" id="kpiIncome">Rp 0</div>
                    <div class="kpi-label">Total Pemasukan</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="fa-solid fa-arrow-trend-down"></i></div>
                <div>
                    <div class="kpi-value text-danger" id="kpiExpense">Rp 0</div>
                    <div class="kpi-label">Total Pengeluaran</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="fa-solid fa-scale-balanced"></i></div>
                <div>
                    <div class="kpi-value" id="kpiNet">Rp 0</div>
                    <div class="kpi-label">Arus Kas Bersih</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                <div>
                    <div class="kpi-value" id="kpiReceivable">Rp 0</div>
                    <div class="kpi-label">Piutang Outstanding</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="kpi-card">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                <div>
                    <div class="kpi-value" id="kpiPayable">Rp 0</div>
                    <div class="kpi-label">Hutang Outstanding</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-chart-line me-1"></i> Arus Kas Bulanan (6 Bulan Terakhir)</div>
    <div class="card-body">
        <canvas id="cashFlowChart" height="90"></canvas>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const ENDPOINT = {
    summary:  "<?= base_url('admin/finance/dashboard/summary') ?>",
    chart:    "<?= base_url('admin/finance/chart/cash-flow') ?>",
};

let cashFlowChartInstance = null;

function loadSummary() {
    const from = $('#from').val();
    const to = $('#to').val();

    showLoading();
    $.get(ENDPOINT.summary, { from, to }, function (res) {
        if (! res.success) return;
        $('#kpiIncome').text(formatRupiah(res.total_income));
        $('#kpiExpense').text(formatRupiah(res.total_expense));
        $('#kpiNet').text(formatRupiah(res.net_cash_flow));
        $('#kpiReceivable').text(formatRupiah(res.total_receivable));
        $('#kpiPayable').text(formatRupiah(res.total_payable));
    }).always(hideLoading);
}

function loadChart() {
    $.get(ENDPOINT.chart, function (res) {
        if (! res.success) return;

        const labels = res.series.map(s => s.month);
        const income = res.series.map(s => s.income || 0);
        const expense = res.series.map(s => s.expense || 0);

        const ctx = document.getElementById('cashFlowChart').getContext('2d');

        if (cashFlowChartInstance) cashFlowChartInstance.destroy();

        cashFlowChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Pemasukan', data: income, backgroundColor: '#16a34a' },
                    { label: 'Pengeluaran', data: expense, backgroundColor: '#dc2626' },
                ],
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } },
            },
        });
    });
}

$('#filterForm').on('submit', function (e) {
    e.preventDefault();
    loadSummary();
});

loadSummary();
loadChart();
</script>
<?= $this->endSection() ?>
