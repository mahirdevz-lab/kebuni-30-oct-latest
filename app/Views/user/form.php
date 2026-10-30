<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $isEdit = $user !== null; ?>
<div class="card" style="max-width:820px">
    <div class="section-header"><i class="fa-solid fa-user-pen me-1"></i> <?= $isEdit ? 'Edit User' : 'Tambah User' ?></div>
    <div class="card-body">
        <form id="userForm">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" id="full_name" class="form-control" required value="<?= esc($user['full_name'] ?? '') ?>">
                    <div class="invalid-feedback" data-error-for="full_name"></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" id="username" class="form-control" required value="<?= esc($user['username'] ?? '') ?>">
                    <div class="invalid-feedback" data-error-for="username"></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="email" class="form-control" required value="<?= esc($user['email'] ?? '') ?>">
                    <div class="invalid-feedback" data-error-for="email"></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Role <span class="text-danger">*</span></label>
                    <select name="role_id" id="role_id" class="form-select" required>
                        <option value="">-- Pilih --</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= (int) ($user['role_id'] ?? 0) === (int) $r['id'] ? 'selected' : '' ?>><?= esc($r['name']) ?></option>
                        <?php endforeach ?>
                    </select>
                    <div class="invalid-feedback" data-error-for="role_id"></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="active" <?= ($user['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Aktif</option>
                        <option value="inactive" <?= ($user['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password <?= $isEdit ? '' : '<span class="text-danger">*</span>' ?></label>
                    <input type="password" name="password" id="password" class="form-control" minlength="8" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                    <div class="form-text"><?= $isEdit ? 'Kosongkan jika tidak ingin mengubah password.' : 'Minimal 8 karakter.' ?></div>
                    <div class="invalid-feedback" data-error-for="password"></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Konfirmasi Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" autocomplete="new-password">
                    <div class="invalid-feedback" data-error-for="confirm_password"></div>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <a href="<?= base_url('admin/user') ?>" class="btn btn-light px-4">Batal</a>
                <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-floppy-disk me-1"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const SAVE_URL = "<?= $isEdit ? base_url('admin/user/' . $user['id'] . '/update') : base_url('admin/user/store') ?>";

$('#userForm').on('submit', function (e) {
    e.preventDefault();
    $('.invalid-feedback').text('');
    $('.is-invalid').removeClass('is-invalid');
    showLoading();

    $.post(SAVE_URL, $(this).serialize())
        .done(res => {
            if (res.success) {
                showToast(res.message, 'success');
                setTimeout(() => window.location.href = "<?= base_url('admin/user') ?>", 600);
            } else {
                showToast(res.message || 'Data tidak valid.', 'error');
            }
        })
        .fail(xhr => {
            const res = xhr.responseJSON || {};
            showToast(res.message || 'Terjadi kesalahan pada server.', 'error');
            Object.keys(res.errors || {}).forEach(f => {
                $(`[data-error-for="${f}"]`).text(res.errors[f]);
                $(`#${f}`).addClass('is-invalid');
            });
        })
        .always(hideLoading);
});
</script>
<?= $this->endSection() ?>
