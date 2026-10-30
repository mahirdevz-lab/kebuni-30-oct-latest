<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card mb-4">
    <div class="section-header">
        <i class="fa-solid fa-address-book me-1"></i> Registrasi Account
    </div>
    <div class="card-body">
        <form id="accountForm">
            <input type="hidden" name="id" id="account_id">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nama Account <span class="text-danger">*</span></label>
                    <input type="text" name="nama_account" id="nama_account" class="form-control" required>
                    <div class="invalid-feedback" data-error-for="nama_account"></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Kota</label>
                    <input type="text" name="kota" id="kota" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label">Alamat</label>
                    <textarea name="alamat" id="alamat" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nomor Telepon</label>
                    <input type="text" name="nomor_telepon" id="nomor_telepon" class="form-control" placeholder="081234567890">
                    <div class="invalid-feedback" data-error-for="nomor_telepon"></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="email" class="form-control">
                    <div class="invalid-feedback" data-error-for="email"></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Contact Person</label>
                    <input type="text" name="contact_person" id="contact_person" class="form-control">
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button type="button" class="btn btn-light px-4" id="btnResetForm">
                    <i class="fa-solid fa-broom me-1"></i> Bersihkan
                </button>
                <button type="submit" class="btn btn-primary px-4" id="btnSaveAccount">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="section-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-list me-1"></i> Daftar Account</span>
    </div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" id="searchInput" class="form-control" placeholder="Cari nama, kota, email, contact person...">
            </div>
            <div class="col-md-3">
                <select id="statusFilter" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="aktif">Aktif</option>
                    <option value="nonaktif">Nonaktif</option>
                </select>
            </div>
        </div>

        <div id="tableContainer">
            <?= $this->include('account/_table') ?>
        </div>
    </div>
</div>

<!-- Modal Detail Account -->
<div class="modal fade" id="modalDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailContent"></div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const ENDPOINT = {
    list:   "<?= base_url('admin/account') ?>",
    store:  "<?= base_url('admin/account/store') ?>",
    edit:   (id) => `<?= base_url('admin/account') ?>/${id}/edit`,
    update: (id) => `<?= base_url('admin/account') ?>/${id}/update`,
    del:    (id) => `<?= base_url('admin/account') ?>/${id}/delete`,
    toggle: (id) => `<?= base_url('admin/account') ?>/${id}/toggle-status`,
    detail: (id) => `<?= base_url('admin/account') ?>/${id}/detail`,
};

function loadTable(page = 1) {
    showLoading();
    $.get(ENDPOINT.list, {
        search: $('#searchInput').val(),
        status: $('#statusFilter').val(),
        page: page
    }, function (html) {
        $('#tableContainer').html(html);
    }).always(hideLoading);
}

$(function () {
    let searchTimer;
    $('#searchInput').on('keyup', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadTable(1), 400);
    });
    $('#statusFilter').on('change', () => loadTable(1));

    // Pagination (delegated, karena tabel di-reload via AJAX)
    $(document).on('click', '#tableContainer .pagination a', function (e) {
        e.preventDefault();
        const url = new URL(this.href);
        loadTable(url.searchParams.get('page') || 1);
    });

    $('#btnResetForm').on('click', resetForm);

    function resetForm() {
        $('#accountForm')[0].reset();
        $('#account_id').val('');
        $('#btnSaveAccount').html('<i class="fa-solid fa-floppy-disk me-1"></i> Simpan');
        $('.invalid-feedback').text('');
        $('.is-invalid').removeClass('is-invalid');
    }

    $('#accountForm').on('submit', function (e) {
        e.preventDefault();
        const id = $('#account_id').val();
        const url = id ? ENDPOINT.update(id) : ENDPOINT.store;

        $('.invalid-feedback').text('');
        $('.is-invalid').removeClass('is-invalid');
        showLoading();

        $.post(url, $(this).serialize())
            .done(function (res) {
                if (res.success) {
                    showToast(res.message, 'success');
                    resetForm();
                    loadTable();
                } else {
                    showToast(res.message || 'Data tidak valid.', 'error');
                    if (res.errors) {
                        Object.keys(res.errors).forEach(field => {
                            $(`[data-error-for="${field}"]`).text(res.errors[field]);
                            $(`#${field}`).addClass('is-invalid');
                        });
                    }
                }
            })
            .fail(() => showToast('Terjadi kesalahan pada server.', 'error'))
            .always(hideLoading);
    });

    // Edit -> isi form
    $(document).on('click', '.btn-edit-account', function () {
        const id = $(this).data('id');
        showLoading();
        $.get(ENDPOINT.edit(id), function (res) {
            if (res.success) {
                const d = res.data;
                $('#account_id').val(d.id);
                $('#nama_account').val(d.nama_account);
                $('#kota').val(d.kota);
                $('#alamat').val(d.alamat);
                $('#nomor_telepon').val(d.nomor_telepon);
                $('#email').val(d.email);
                $('#contact_person').val(d.contact_person);
                $('#btnSaveAccount').html('<i class="fa-solid fa-floppy-disk me-1"></i> Update');
                $('html, body').animate({ scrollTop: 0 }, 300);
            }
        }).always(hideLoading);
    });

    // Detail
    $(document).on('click', '.btn-detail-account', function () {
        const id = $(this).data('id');
        showLoading();
        $.get(ENDPOINT.detail(id), function (res) {
            if (res.success) {
                const d = res.data;
                $('#detailContent').html(`
                    <table class="table table-borderless mb-0">
                        <tr><th width="140">Nama Account</th><td>: ${d.nama_account}</td></tr>
                        <tr><th>Kota</th><td>: ${d.kota || '-'}</td></tr>
                        <tr><th>Alamat</th><td>: ${d.alamat || '-'}</td></tr>
                        <tr><th>Telepon</th><td>: ${d.nomor_telepon || '-'}</td></tr>
                        <tr><th>Email</th><td>: ${d.email || '-'}</td></tr>
                        <tr><th>Contact Person</th><td>: ${d.contact_person || '-'}</td></tr>
                        <tr><th>Status</th><td>: ${d.status}</td></tr>
                    </table>
                `);
                new bootstrap.Modal('#modalDetail').show();
            }
        }).always(hideLoading);
    });

    // Delete
    $(document).on('click', '.btn-delete-account', function () {
        const id = $(this).data('id');
        confirmAction({
            title: 'Hapus Account',
            text: 'Data account yang dihapus tidak dapat dikembalikan.',
            onConfirm: () => ajaxPost(ENDPOINT.del(id), {}, () => loadTable())
        });
    });

    // Toggle status
    $(document).on('click', '.btn-toggle-account', function () {
        const id = $(this).data('id');
        ajaxPost(ENDPOINT.toggle(id), {}, () => loadTable());
    });
});
</script>
<?= $this->endSection() ?>
