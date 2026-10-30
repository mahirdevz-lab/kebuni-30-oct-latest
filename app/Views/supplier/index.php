<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card mb-4">
    <div class="section-header">
        <i class="fa-solid fa-truck-field me-1"></i> Registrasi Supplier
    </div>
    <div class="card-body">
        <form id="supplierForm">
            <input type="hidden" name="id" id="supplier_id">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nama Supplier <span class="text-danger">*</span></label>
                    <input type="text" name="nama_supplier" id="nama_supplier" class="form-control" required>
                    <div class="invalid-feedback" data-error-for="nama_supplier"></div>
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
                    <input type="text" name="nomor_telepon" id="nomor_telepon" class="form-control">
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
                <button type="submit" class="btn btn-primary px-4" id="btnSaveSupplier">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="section-header">
        <i class="fa-solid fa-list me-1"></i> Daftar Supplier
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
            <?= $this->include('supplier/_table') ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const ENDPOINT = {
    list:   "<?= base_url('admin/supplier') ?>",
    store:  "<?= base_url('admin/supplier/store') ?>",
    edit:   (id) => `<?= base_url('admin/supplier') ?>/${id}/edit`,
    update: (id) => `<?= base_url('admin/supplier') ?>/${id}/update`,
    del:    (id) => `<?= base_url('admin/supplier') ?>/${id}/delete`,
    toggle: (id) => `<?= base_url('admin/supplier') ?>/${id}/toggle-status`,
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

    $(document).on('click', '#tableContainer .pagination a', function (e) {
        e.preventDefault();
        const url = new URL(this.href);
        loadTable(url.searchParams.get('page') || 1);
    });

    $('#btnResetForm').on('click', resetForm);

    function resetForm() {
        $('#supplierForm')[0].reset();
        $('#supplier_id').val('');
        $('#btnSaveSupplier').html('<i class="fa-solid fa-floppy-disk me-1"></i> Simpan');
        $('.invalid-feedback').text('');
        $('.is-invalid').removeClass('is-invalid');
    }

    $('#supplierForm').on('submit', function (e) {
        e.preventDefault();
        const id = $('#supplier_id').val();
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

    $(document).on('click', '.btn-edit-supplier', function () {
        const id = $(this).data('id');
        showLoading();
        $.get(ENDPOINT.edit(id), function (res) {
            if (res.success) {
                const d = res.data;
                $('#supplier_id').val(d.id);
                $('#nama_supplier').val(d.nama_supplier);
                $('#kota').val(d.kota);
                $('#alamat').val(d.alamat);
                $('#nomor_telepon').val(d.nomor_telepon);
                $('#email').val(d.email);
                $('#contact_person').val(d.contact_person);
                $('#btnSaveSupplier').html('<i class="fa-solid fa-floppy-disk me-1"></i> Update');
                $('html, body').animate({ scrollTop: 0 }, 300);
            }
        }).always(hideLoading);
    });

    $(document).on('click', '.btn-delete-supplier', function () {
        const id = $(this).data('id');
        confirmAction({
            title: 'Hapus Supplier',
            text: 'Data supplier yang dihapus tidak dapat dikembalikan.',
            onConfirm: () => ajaxPost(ENDPOINT.del(id), {}, () => loadTable())
        });
    });

    $(document).on('click', '.btn-toggle-supplier', function () {
        const id = $(this).data('id');
        ajaxPost(ENDPOINT.toggle(id), {}, () => loadTable());
    });
});
</script>
<?= $this->endSection() ?>
