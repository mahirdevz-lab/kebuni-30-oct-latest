<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$s = $report['summary'];
$exportUrl = base_url('admin/report/attendance') . '?' . http_build_query(['from' => $from, 'to' => $to, 'search' => $search, 'location_status' => $loc, 'export' => 1]);
$locLabel = ['INSIDE_AREA' => 'Dalam Area', 'OUTSIDE_AREA' => 'Di Luar Area', 'UNKNOWN' => 'Tidak Diketahui'];
?>
<div class="card mb-4 no-print">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-2"><label class="form-label small text-muted">Dari</label><input type="date" name="from" class="form-control form-control-sm" value="<?= esc($from) ?>"></div>
            <div class="col-md-2"><label class="form-label small text-muted">Sampai</label><input type="date" name="to" class="form-control form-control-sm" value="<?= esc($to) ?>"></div>
            <div class="col-md-3"><label class="form-label small text-muted">Nama</label><input type="text" name="search" class="form-control form-control-sm" value="<?= esc($search ?? '') ?>"></div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Lokasi</label>
                <select name="location_status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php foreach ($locLabel as $k => $v): ?><option value="<?= $k ?>" <?= $loc === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-fill">Terapkan</button>
                <a href="<?= esc($exportUrl) ?>" class="btn btn-sm btn-outline-success" title="Detail per absensi"><i class="fa-solid fa-file-csv"></i> Detail</a>
                <a href="<?= esc(str_replace('export=1', 'export=recap', $exportUrl)) ?>" class="btn btn-sm btn-outline-success" title="Rekap per karyawan"><i class="fa-solid fa-file-csv"></i> Rekap</a>
                <button aria-label="Cetak halaman" type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="kpi-card"><div class="kpi-value"><?= $s['check_in'] ?></div><div class="kpi-label">Absen Masuk</div></div></div>
    <div class="col-md-3"><div class="kpi-card"><div class="kpi-value"><?= $s['check_out'] ?></div><div class="kpi-label">Absen Pulang</div></div></div>
    <div class="col-md-3"><div class="kpi-card"><div class="kpi-value profit-negative"><?= $s['late'] ?></div><div class="kpi-label">Terlambat</div></div></div>
    <div class="col-md-3"><div class="kpi-card"><div class="kpi-value profit-negative"><?= $s['outside'] ?></div><div class="kpi-label">Di Luar Area</div></div></div>
</div>

<div class="card mb-4">
    <div class="section-header"><i class="fa-solid fa-table-list me-1"></i> Rekap per Karyawan</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Nama</th><th class="text-center">Hari Hadir</th><th class="text-center">Terlambat</th><th class="text-center">Absen Pulang</th><th class="text-center">Di Luar Area</th></tr></thead>
                <tbody>
                    <?php if (empty($report['recap'])): ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-inbox"></i><p class="mb-0">Tidak ada data.</p></div></td></tr>
                    <?php else: foreach ($report['recap'] as $r): ?>
                        <tr>
                            <td class="fw-semibold"><?= esc($r['nama']) ?></td>
                            <td class="text-center"><?= (int) $r['days_present'] ?></td>
                            <td class="text-center"><?= (int) $r['late_count'] ?></td>
                            <td class="text-center"><?= (int) $r['checkout_count'] ?></td>
                            <td class="text-center"><?= (int) $r['outside_count'] ?></td>
                        </tr>
                    <?php endforeach; endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-file-lines me-1"></i> Detail Absensi (<?= $s['records'] ?> data)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Nama</th><th>Tanggal</th><th>Jam</th><th>Jenis</th><th>Lokasi</th><th>Jarak</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($report['rows'] as $r): ?>
                        <tr>
                            <td><?= esc($r['nama']) ?></td>
                            <td><?= tgl_id($r['attendance_date']) ?></td>
                            <td><?= substr($r['attendance_time'], 0, 5) ?></td>
                            <td><?= $r['attendance_type'] === 'CHECK_IN' ? 'Masuk' : 'Pulang' ?></td>
                            <td><?= esc($locLabel[$r['location_status']] ?? '-') ?><?= $r['location_name'] ? ' (' . esc($r['location_name']) . ')' : '' ?></td>
                            <td><?= $r['distance_from_office'] !== null ? number_format((float) $r['distance_from_office'], 0, ',', '.') . ' m' : '-' ?></td>
                            <td><?= $r['is_late'] ? 'Terlambat' : '-' ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
