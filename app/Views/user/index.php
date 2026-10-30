<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="section-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-users-gear me-1"></i> Daftar User</span>
        <?php if ((new \App\Services\AuthService())->hasPermission('user.create')): ?>
            <a href="<?= base_url('admin/user/create') ?>" class="btn btn-sm btn-light"><i class="fa-solid fa-plus me-1"></i> Tambah User</a>
        <?php endif ?>
    </div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-4"><input type="text" id="searchInput" class="form-control" placeholder="Cari nama / username / email..."></div>
            <div class="col-md-3">
                <select id="roleFilter" class="form-select">
                    <option value="">Semua Role</option>
                    <?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>"><?= esc($r['name']) ?></option><?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3">
                <select id="statusFilter" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                </select>
            </div>
        </div>
        <div id="tableContainer"><?= $this->include('user/_table') ?></div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const LIST_URL = "<?= base_url('admin/user') ?>";

function loadTable(page = 1) {
    showLoading();
    $.get(LIST_URL, { search: $('#searchInput').val(), role_id: $('#roleFilter').val(), status: $('#statusFilter').val(), page },
        html => $('#tableContainer').html(html)).always(hideLoading);
}

$(function () {
    let t;
    $('#searchInput').on('keyup', () => { clearTimeout(t); t = setTimeout(() => loadTable(1), 400); });
    $('#roleFilter, #statusFilter').on('change', () => loadTable(1));

    $(document).on('click', '#tableContainer .pagination a', function (e) {
        e.preventDefault();
        loadTable(new URL(this.href).searchParams.get('page') || 1);
    });

    $(document).on('click', '.btn-delete-user', function () {
        const id = $(this).data('id');
        confirmAction({
            title: 'Hapus User',
            text: 'User tidak akan bisa login lagi.',
            onConfirm: () => ajaxPost(`${LIST_URL}/${id}/delete`, {}, () => loadTable())
        });
    });
});
</script>
<?= $this->endSection() ?>
