<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card">
            <div class="section-header" id="formTitle"><i class="fa-solid fa-tag me-1"></i> Tambah Category</div>
            <div class="card-body">
                <form id="categoryForm">
                    <input type="hidden" name="id" id="category_id">
                    <div class="mb-3">
                        <label class="form-label">Nama Category <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" required>
                        <div class="invalid-feedback" data-error-for="name"></div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light w-50" id="btnReset">Bersihkan</button>
                        <button type="submit" class="btn btn-primary w-50" id="btnSave">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="section-header"><i class="fa-solid fa-list me-1"></i> Daftar Category</div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-7">
                        <input type="text" id="searchInput" class="form-control" placeholder="Cari nama kategori..." value="<?= esc($search ?? '') ?>">
                    </div>
                    <div class="col-md-5">
                        <select id="statusFilter" class="form-select">
                            <option value="">Semua Status</option>
                            <option value="aktif" <?= ($status ?? '') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                            <option value="nonaktif" <?= ($status ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div id="tableContainer"><?= $this->include('product_category/_table') ?></div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const ENDPOINT = {
    list:   "<?= base_url('admin/product-category') ?>",
    store:  "<?= base_url('admin/product-category/store') ?>",
    edit:   (id) => `<?= base_url('admin/product-category') ?>/${id}/edit`,
    update: (id) => `<?= base_url('admin/product-category') ?>/${id}/update`,
    del:    (id) => `<?= base_url('admin/product-category') ?>/${id}/delete`,
};

function loadTable(page = 1) {
    $('#tableContainer').html(tableSkeleton(4));
    $.get(ENDPOINT.list, {
        search: $('#searchInput').val(),
        status: $('#statusFilter').val(),
        page
    }, function (html) {
        $('#tableContainer').html(html);
    });
}

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

function resetForm() {
    $('#categoryForm')[0].reset();
    $('#category_id').val('');
    $('#formTitle').html('<i class="fa-solid fa-tag me-1"></i> Tambah Category');
    $('#btnSave').text('Simpan');
    $('.invalid-feedback').text('');
    $('.is-invalid').removeClass('is-invalid');
}

$('#btnReset').on('click', resetForm);

$('#categoryForm').on('submit', function (e) {
    e.preventDefault();
    const id = $('#category_id').val();
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
                    Object.keys(res.errors).forEach(f => {
                        $(`[data-error-for="${f}"]`).text(res.errors[f]);
                        $(`#${f}`).addClass('is-invalid');
                    });
                }
            }
        })
        .fail(() => showToast('Terjadi kesalahan pada server.', 'error'))
        .always(hideLoading);
});

$(document).on('click', '.btn-edit', function () {
    const id = $(this).data('id');
    showLoading();
    $.get(ENDPOINT.edit(id), function (res) {
        if (res.success) {
            $('#category_id').val(res.data.id);
            $('#name').val(res.data.name);
            $('#formTitle').html('<i class="fa-solid fa-pen me-1"></i> Edit Category');
            $('#btnSave').text('Update');
        }
    }).always(hideLoading);
});

$(document).on('click', '.btn-delete', function () {
    const id = $(this).data('id');
    confirmAction({
        title: 'Hapus Category',
        text: 'Data yang dihapus tidak dapat dikembalikan.',
        onConfirm: () => ajaxPost(ENDPOINT.del(id), {}, () => loadTable())
    });
});
</script>
<?= $this->endSection() ?>
