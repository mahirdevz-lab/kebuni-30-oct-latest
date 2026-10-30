<?php
$locBadge = ['INSIDE_AREA' => ['bg-success', 'Dalam Area'], 'OUTSIDE_AREA' => ['bg-danger', 'Di Luar Area'], 'UNKNOWN' => ['bg-secondary', 'Tidak Diketahui']];
?>
<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th width="40">No</th>
                <th>Nama</th>
                <th>Tanggal</th>
                <th>Jam</th>
                <th>Jenis</th>
                <th>Lokasi</th>
                <th>Jarak</th>
                <th>Status</th>
                <th width="70">Foto</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="9"><div class="empty-state"><i class="fa-solid fa-inbox"></i><p class="mb-0">Belum ada data absensi.</p></div></td></tr>
            <?php else: ?>
                <?php $no = ($pager->getCurrentPage() - 1) * $pager->getPerPage() + 1; ?>
                <?php foreach ($rows as $r): ?>
                    <?php [$cls, $label] = $locBadge[$r['location_status']] ?? $locBadge['UNKNOWN']; ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td class="fw-semibold"><?= esc($r['nama']) ?></td>
                        <td><?= tgl_id($r['attendance_date']) ?></td>
                        <td><?= substr($r['attendance_time'], 0, 5) ?></td>
                        <td><?= $r['attendance_type'] === 'CHECK_IN' ? 'Masuk' : 'Pulang' ?></td>
                        <td>
                            <span class="badge <?= $cls ?>"><?= $label ?></span>
                            <?php if ($r['location_name']): ?><div class="small text-muted"><?= esc($r['location_name']) ?></div><?php endif ?>
                        </td>
                        <td><?= $r['distance_from_office'] !== null ? number_format((float) $r['distance_from_office'], 0, ',', '.') . ' m' : '-' ?></td>
                        <td><?= $r['is_late'] ? '<span class="badge bg-warning text-dark">Terlambat</span>' : ($r['attendance_type'] === 'CHECK_IN' ? '<span class="badge bg-light text-dark border">Tepat Waktu</span>' : '-') ?></td>
                        <td>
                            <button aria-label="Lihat foto absensi <?= esc($r['nama']) ?>" class="btn btn-sm btn-outline-primary btn-photo" data-src="<?= base_url('admin/attendance/photo/' . $r['id']) ?>" data-title="<?= esc($r['nama']) ?>">
                                <i class="fa-solid fa-image"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach ?>
            <?php endif ?>
        </tbody>
    </table>
</div>
<?= $this->include('partials/pager_footer', ['pager' => $pager]) ?>
