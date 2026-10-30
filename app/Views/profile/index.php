<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="row g-4">
    <div class="col-md-5">
        <div class="card">
            <div class="section-header"><i class="fa-solid fa-id-badge me-1"></i> Informasi Akun</div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr><th width="120">Nama</th><td>: <?= esc($user['full_name']) ?></td></tr>
                    <tr><th>Username</th><td>: <?= esc($user['username']) ?></td></tr>
                    <tr><th>Email</th><td>: <?= esc($user['email']) ?></td></tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card">
            <div class="section-header"><i class="fa-solid fa-key me-1"></i> Ganti Password</div>
            <div class="card-body">
                <form id="passwordForm">
                    <div class="mb-3">
                        <label class="form-label">Password Saat Ini</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password Baru</label>
                        <input type="password" name="new_password" class="form-control" minlength="8" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Konfirmasi Password Baru</label>
                        <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                    </div>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$('#passwordForm').on('submit', function (e) {
    e.preventDefault();
    ajaxPost("<?= base_url('admin/profile/update-password') ?>", $(this).serialize(), () => {
        $('#passwordForm')[0].reset();
    });
});
</script>
<?= $this->endSection() ?>
