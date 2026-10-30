<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card" style="max-width:520px">
    <div class="section-header"><i class="fa-solid fa-key me-1"></i> PIN Supervisor</div>
    <div class="card-body">
        <p class="small text-muted">PIN dipakai supervisor di layar kasir untuk mengotorisasi <strong>diskon manual</strong> dan <strong>override stok</strong>
            tanpa harus login ulang. PIN bersifat pribadi — jangan dibagikan. Salah 5× terkunci 15 menit.</p>
        <div class="alert alert-<?= $hasPin ? 'success' : 'warning' ?> py-2 small"><?= $hasPin ? 'Anda sudah menyetel PIN. Isi form di bawah untuk menggantinya.' : 'Anda belum menyetel PIN.' ?></div>
        <label class="form-label">PIN baru (4–6 digit) *</label>
        <input type="password" id="pPin" class="form-control mb-2" inputmode="numeric" maxlength="6" autocomplete="new-password">
        <label class="form-label">Ulangi PIN *</label>
        <input type="password" id="pPin2" class="form-control mb-2" inputmode="numeric" maxlength="6" autocomplete="new-password">
        <label class="form-label">Password akun Anda *</label>
        <input type="password" id="pPass" class="form-control mb-3" autocomplete="current-password">
        <button class="btn btn-primary" id="pSave"><i class="fa-solid fa-floppy-disk me-1"></i> Simpan PIN</button>
        <hr>
        <div class="small text-muted">Supervisor dengan PIN aktif: <?= $supervisors ? esc(implode(', ', array_column($supervisors, 'full_name'))) : '-' ?></div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$('#pSave').on('click', function () {
    if ($('#pPin').val() !== $('#pPin2').val()) { showToast('Konfirmasi PIN tidak sama.', 'warning'); return; }
    ajaxPost(BASE_URL + 'admin/pos/pin', { pin: $('#pPin').val(), password: $('#pPass').val() }, () => setTimeout(() => location.reload(), 700));
});
</script>
<?= $this->endSection() ?>
