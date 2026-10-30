<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="section-header"><i class="fa-solid fa-bullseye me-1"></i> Target Aktif Saat Ini</div>
            <div class="card-body">
                <?php if ($active): ?>
                    <div class="d-flex gap-4 mb-3">
                        <div class="text-center">
                            <div class="display-6 fw-bold text-primary"><?= (int) $active['feeds_per_week'] ?>&times;</div>
                            <div class="text-muted small">Feeds / minggu</div>
                        </div>
                        <div class="text-center">
                            <div class="display-6 fw-bold text-danger"><?= (int) $active['videos_per_week'] ?>&times;</div>
                            <div class="text-muted small">Video / minggu</div>
                        </div>
                    </div>
                    <div class="text-muted small">Berlaku sejak <?= date('d M Y', strtotime($active['effective_from'])) ?></div>
                <?php else: ?>
                    <div class="text-muted">Belum ada target.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="section-header"><i class="fa-solid fa-plus me-1"></i> Naikkan / Ubah Target</div>
            <div class="card-body">
                <form id="targetForm">
                    <?= csrf_field() ?>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Feeds / minggu</label>
                            <input type="number" name="feeds_per_week" class="form-control" min="0" max="50" value="<?= $active ? (int) $active['feeds_per_week'] : 2 ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Video / minggu</label>
                            <input type="number" name="videos_per_week" class="form-control" min="0" max="50" value="<?= $active ? (int) $active['videos_per_week'] : 1 ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Berlaku mulai</label>
                            <input type="date" name="effective_from" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="form-text mb-2">Target lama otomatis jadi riwayat — KPI bulan lalu tetap dihitung pakai target yang berlaku waktu itu.</div>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Simpan Target Baru</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="section-header"><i class="fa-solid fa-clock-rotate-left me-1"></i> Riwayat Target</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Berlaku Mulai</th><th>Feeds/minggu</th><th>Video/minggu</th><th>Total/minggu</th><th>Dibuat</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                    <tr class="<?= $active && $h['id'] == $active['id'] ? 'table-success' : '' ?>">
                        <td><?= date('d M Y', strtotime($h['effective_from'])) ?>
                            <?php if ($active && $h['id'] == $active['id']): ?><span class="badge bg-success ms-1">Aktif</span><?php endif; ?>
                        </td>
                        <td><?= (int) $h['feeds_per_week'] ?>&times;</td>
                        <td><?= (int) $h['videos_per_week'] ?>&times;</td>
                        <td class="fw-bold"><?= (int) $h['feeds_per_week'] + (int) $h['videos_per_week'] ?>&times;</td>
                        <td class="text-muted small"><?= date('d M Y H:i', strtotime($h['created_at'])) ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteTarget(<?= (int) $h['id'] ?>)" title="Hapus">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($history)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada riwayat.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$('#targetForm').on('submit', function (e) {
    e.preventDefault();
    $.post("<?= base_url('admin/sosmed/targets/save') ?>", $(this).serialize())
        .done(res => {
            showToast(res.message, res.success ? 'success' : 'error');
            if (res.success) setTimeout(() => location.reload(), 800);
        })
        .fail(() => showToast('Gagal menyimpan.', 'error'));
});

function deleteTarget(id) {
    if (! confirm('Hapus target ini?')) return;
    $.post("<?= base_url('admin/sosmed/targets') ?>/" + id + "/delete", { "<?= csrf_token() ?>": "<?= csrf_hash() ?>" })
        .done(res => {
            showToast(res.message, res.success ? 'success' : 'error');
            if (res.success) setTimeout(() => location.reload(), 800);
        })
        .fail(() => showToast('Gagal menghapus.', 'error'));
}
</script>
<?= $this->endSection() ?>
