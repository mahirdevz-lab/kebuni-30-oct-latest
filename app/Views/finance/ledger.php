<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">BUKU BESAR KEUANGAN</h5>
    <form method="get" class="d-flex gap-2 align-items-center">
        <label class="form-label mb-0">Periode:</label>
        <select name="year" class="form-select" onchange="this.form.submit()" style="width: auto;">
            <?php foreach ($years as $y): ?>
                <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach ?>
        </select>
    </form>
</div>

<!-- Ringkasan -->
<div class="card mb-4">
    <div class="section-header text-center fw-bold">PERIODE TAHUN <?= $year ?></div>
    <div class="table-responsive">
        <table class="table table-bordered text-center mb-0">
            <thead>
                <tr>
                    <th>MODAL AWAL</th>
                    <th>PEMASUKAN</th>
                    <th>PENGELUARAN</th>
                    <th>KEUNTUNGAN</th>
                    <th>PENDING</th>
                    <th>KAS SEKARANG</th>
                </tr>
            </thead>
            <tbody>
                <tr class="fs-5">
                    <td>Rp <?= number_format($modalAwal, 0, ',', '.') ?></td>
                    <td class="text-primary">Rp <?= number_format($pemasukan, 0, ',', '.') ?></td>
                    <td class="text-danger">Rp <?= number_format($pengeluaran, 0, ',', '.') ?></td>
                    <td class="<?= $keuntungan >= 0 ? 'profit-positive' : 'profit-negative' ?>">Rp <?= number_format($keuntungan, 0, ',', '.') ?></td>
                    <td>Rp <?= number_format($pending, 0, ',', '.') ?></td>
                    <td class="fw-bold">Rp <?= number_format($kasSekarang, 0, ',', '.') ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Saldo per rekening -->
<?php if (! empty($accounts)): ?>
<div class="row g-3 mb-4">
    <?php foreach ($accounts as $a): ?>
    <div class="col-md-3">
        <div class="kpi-card">
            <div class="kpi-label"><?= esc($a['name']) ?></div>
            <div class="kpi-value">Rp <?= number_format($a['balance'], 0, ',', '.') ?></div>
        </div>
    </div>
    <?php endforeach ?>
</div>
<?php endif ?>

<!-- Catatan bulanan -->
<div class="card">
    <div class="section-header text-center fw-bold">CATATAN BULANAN</div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover mb-0">
            <thead>
                <tr class="text-center">
                    <th>No</th>
                    <th>Bulan</th>
                    <th>Pemasukan</th>
                    <th>Pengeluaran</th>
                    <th>Operasional</th>
                    <th>Jml Transaksi</th>
                    <th>Jml Pending</th>
                    <th>Total Pending</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($monthly as $m): ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td><?= $m['nama_bulan'] ?></td>
                    <td class="text-end">Rp <?= number_format($m['pemasukan'], 0, ',', '.') ?></td>
                    <td class="text-end text-danger">Rp <?= number_format($m['pengeluaran'], 0, ',', '.') ?></td>
                    <td class="text-end"><em>Rp <?= number_format($m['operasional'], 0, ',', '.') ?></em></td>
                    <td class="text-center"><?= $m['n_transaksi'] ?: '-' ?></td>
                    <td class="text-center"><?= $m['jml_pending'] ?: '-' ?></td>
                    <td class="text-end"><?= $m['total_pending'] ? 'Rp ' . number_format($m['total_pending'], 0, ',', '.') : '-' ?></td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
