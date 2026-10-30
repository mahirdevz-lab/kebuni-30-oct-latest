<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Nama</th><th>Koordinat</th><th>Radius</th><th>Status</th><th width="150">Action</th></tr></thead>
        <tbody>
            <?php if (empty($locations)): ?>
                <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-inbox"></i><p class="mb-0">Belum ada lokasi. Tanpa lokasi aktif, semua absensi berstatus "Tidak Diketahui".</p></div></td></tr>
            <?php else: ?>
                <?php foreach ($locations as $l): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($l['name']) ?></td>
                        <td><?= esc($l['latitude']) ?>, <?= esc($l['longitude']) ?></td>
                        <td><?= (int) $l['radius_meter'] ?> m</td>
                        <td><?= $this->include('partials/status_badge', ['status' => $l['status']]) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-outline-primary btn-edit" data-loc='<?= esc(json_encode($l), 'attr') ?>' title="Edit" aria-label="Edit <?= esc($l['name'], 'attr') ?>"><i class="fa-solid fa-pen"></i></button>
                                <button class="btn btn-sm btn-outline-secondary btn-toggle" data-id="<?= $l['id'] ?>" title="Aktif/Nonaktif" aria-label="Aktifkan atau nonaktifkan <?= esc($l['name'], 'attr') ?>"><i class="fa-solid fa-power-off"></i></button>
                                <button class="btn btn-sm btn-outline-danger btn-delete" data-id="<?= $l['id'] ?>" title="Hapus" aria-label="Hapus <?= esc($l['name'], 'attr') ?>"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
            <?php endif ?>
        </tbody>
    </table>
</div>

<?= $this->include('partials/pager_footer', ['pager' => $pager]) ?>