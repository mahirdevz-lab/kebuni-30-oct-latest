<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-3"><label class="form-label small text-muted">Dari</label><input type="date" name="from" class="form-control form-control-sm" value="<?= esc($from) ?>"></div>
            <div class="col-md-3"><label class="form-label small text-muted">Sampai</label><input type="date" name="to" class="form-control form-control-sm" value="<?= esc($to) ?>"></div>
            <div class="col-md-3"><label class="form-label small text-muted">Nama</label><input type="text" name="search" class="form-control form-control-sm" value="<?= esc($search ?? '') ?>"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Terapkan</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-table-list me-1"></i> Rekap Absensi <?= tgl_id($from) ?> &ndash; <?= tgl_id($to) ?></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>Nama</th><th class="text-center">Hari Hadir</th><th class="text-center">Terlambat</th><th class="text-center">Absen Pulang</th><th class="text-center">Di Luar Area</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-inbox"></i><p class="mb-0">Tidak ada data pada rentang ini.</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td class="fw-semibold"><?= esc($r['nama']) ?></td>
                                <td class="text-center"><?= (int) $r['days_present'] ?></td>
                                <td class="text-center <?= $r['late_count'] > 0 ? 'profit-negative' : '' ?>"><?= (int) $r['late_count'] ?></td>
                                <td class="text-center"><?= (int) $r['checkout_count'] ?></td>
                                <td class="text-center <?= $r['outside_count'] > 0 ? 'profit-negative' : '' ?>"><?= (int) $r['outside_count'] ?></td>
                            </tr>
                        <?php endforeach ?>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
