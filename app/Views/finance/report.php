<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted">Dari Tanggal</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= $report['from'] ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Sampai Tanggal</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= $report['to'] ?>">
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
            <div class="kpi-value text-success">Rp <?= number_format($report['total_income'], 0, ',', '.') ?></div>
            <div class="kpi-label">Total Pemasukan</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card">
            <div class="kpi-value text-danger">Rp <?= number_format($report['total_expense'], 0, ',', '.') ?></div>
            <div class="kpi-label">Total Pengeluaran</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card">
            <div class="kpi-value <?= ($report['total_income'] - $report['total_expense']) >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                Rp <?= number_format($report['total_income'] - $report['total_expense'], 0, ',', '.') ?>
            </div>
            <div class="kpi-label">Laba/Rugi Bersih</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card">
            <div class="kpi-value">Rp <?= number_format($report['total_receivable'], 0, ',', '.') ?></div>
            <div class="kpi-label">Piutang Outstanding</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-table-list me-1"></i> Rincian Bulanan</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Bulan</th>
                        <th class="text-end">Pemasukan</th>
                        <th class="text-end">Pengeluaran</th>
                        <th class="text-end">Selisih</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($report['monthly'])): ?>
                        <tr>
                            <td colspan="4">
                                <div class="empty-state">
                                    <i class="fa-solid fa-inbox"></i>
                                    <p class="mb-0">Tidak ada data pada rentang tanggal ini.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($report['monthly'] as $m): ?>
                            <?php $diff = $m['income'] - $m['expense']; ?>
                            <tr>
                                <td><?= tgl_id($m['month'] . '-01', 'MMMM yyyy') ?></td>
                                <td class="text-end text-success">Rp <?= number_format($m['income'], 0, ',', '.') ?></td>
                                <td class="text-end text-danger">Rp <?= number_format($m['expense'], 0, ',', '.') ?></td>
                                <td class="text-end fw-semibold <?= $diff >= 0 ? 'profit-positive' : 'profit-negative' ?>">Rp <?= number_format($diff, 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach ?>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$('#filterForm').on('submit', function (e) {
    e.preventDefault();
    window.location.href = '<?= base_url('admin/finance/report') ?>?' + $(this).serialize();
});
</script>
<?= $this->endSection() ?>
