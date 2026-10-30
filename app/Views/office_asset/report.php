<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card mb-4">
    <div class="section-header"><i class="fa-solid fa-filter me-1"></i> Filter Periode <span class="text-muted fw-normal small">(untuk penarikan data keuangan)</span></div>
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Dari Tanggal</label>
                <input type="date" name="from" class="form-control" value="<?= esc($from ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Sampai Tanggal</label>
                <input type="date" name="to" class="form-control" value="<?= esc($to ?? '') ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i> Terapkan</button>
                <a href="<?= base_url('admin/report/office-asset') ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small mb-1"><i class="fa-solid fa-boxes-stacked me-1"></i> Valuasi Master Data (Aktif)</div>
                <div class="fs-3 fw-bold">Rp <?= number_format($valuationAktif['total_value'], 0, ',', '.') ?></div>
                <div class="text-muted small"><?= number_format($valuationAktif['total_qty'], 0, ',', '.') ?> barang</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small mb-1"><i class="fa-solid fa-wallet me-1"></i> Realisasi Keuangan <span class="badge bg-light text-dark border">Aset Kantor</span></div>
                <div class="fs-3 fw-bold">Rp <?= number_format($finance['total'], 0, ',', '.') ?></div>
                <div class="text-muted small"><?= $finance['n_transaksi'] ?> transaksi<?= ($from || $to) ? ' (periode filter)' : '' ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small mb-1"><i class="fa-solid fa-scale-balanced me-1"></i> Selisih (Valuasi − Realisasi)</div>
                <div class="fs-3 fw-bold <?= $selisih < 0 ? 'text-danger' : 'text-success' ?>">
                    <?= $selisih < 0 ? '−' : '' ?>Rp <?= number_format(abs($selisih), 0, ',', '.') ?>
                </div>
                <div class="text-muted small">Valuasi master dikurangi belanja tercatat</div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="section-header"><i class="fa-solid fa-table me-1"></i> Rincian Belanja — Kategori "Aset Kantor"</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:40px">No</th>
                    <th>Tanggal</th>
                    <th>Keterangan</th>
                    <th>Rekening</th>
                    <th class="text-end">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($financeDetail as $f): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= date('d M Y', strtotime($f['transaction_date'])) ?></td>
                    <td><?= esc($f['description']) ?></td>
                    <td><?= esc($f['account_name'] ?? '-') ?></td>
                    <td class="text-end text-danger">Rp <?= number_format($f['amount'], 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($financeDetail)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada transaksi kategori "Aset Kantor"<?= ($from || $to) ? ' pada periode ini' : '' ?>.</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if (! empty($financeDetail)): ?>
            <tfoot>
                <tr class="table-light fw-bold">
                    <td colspan="4">TOTAL</td>
                    <td class="text-end">Rp <?= number_format($finance['total'], 0, ',', '.') ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
