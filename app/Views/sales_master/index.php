<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card">
            <div class="section-header" id="formTitle"><i class="fa-solid fa-user-tie me-1"></i> Tambah Sales</div>
            <div class="card-body">
                <form id="salesForm">
                    <input type="hidden" name="id" id="sales_id">
                    <div class="mb-3">
                        <label class="form-label">Nama Sales <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" required>
                        <div class="invalid-feedback" data-error-for="name"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">No. Telepon</label>
                        <input type="text" name="phone" id="phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="email" class="form-control">
                        <div class="invalid-feedback" data-error-for="email"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="2"></textarea>
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
            <div class="section-header"><i class="fa-solid fa-list me-1"></i> Daftar Sales</div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="visually-hidden" for="searchInput">Cari sales</label>
                        <input type="text" id="searchInput" class="form-control" placeholder="Cari nama / telepon / email sales..." value="<?= esc($search ?? '') ?>">
                    </div>
                </div>
                <div id="tableContainer"><?= $this->include('sales_master/_table') ?></div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const ENDPOINT = {
    list:   "<?= base_url('admin/sales-master') ?>",
    store:  "<?= base_url('admin/sales-master/store') ?>",
    edit:   (id) => `<?= base_url('admin/sales-master') ?>/${id}/edit`,
    update: (id) => `<?= base_url('admin/sales-master') ?>/${id}/update`,
    del:    (id) => `<?= base_url('admin/sales-master') ?>/${id}/delete`,
};

function resetForm() {
    $('#salesForm')[0].reset();
    $('#sales_id').val('');
    $('#formTitle').html('<i class="fa-solid fa-user-tie me-1"></i> Tambah Sales');
    $('#btnSave').text('Simpan');
    $('.invalid-feedback').text('');
    $('.is-invalid').removeClass('is-invalid');
}

function loadTable(page = 1) {
    refreshInPlace('#tableContainer', ENDPOINT.list, { search: $('#searchInput').val() }, page);
}

let salesSearchTimer;
$('#searchInput').on('keyup', function () {
    clearTimeout(salesSearchTimer);
    salesSearchTimer = setTimeout(() => loadTable(1), 400);
});

$(document).on('click', '#tableContainer .pagination a', function (e) {
    e.preventDefault();
    loadTable(new URL(this.href).searchParams.get('page') || 1);
});

$('#btnReset').on('click', resetForm);

$('#salesForm').on('submit', function (e) {
    e.preventDefault();
    const id = $('#sales_id').val();
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
            const d = res.data;
            $('#sales_id').val(d.id);
            $('#name').val(d.name);
            $('#phone').val(d.phone);
            $('#email').val(d.email);
            $('#notes').val(d.notes);
            $('#formTitle').html('<i class="fa-solid fa-pen me-1"></i> Edit Sales');
            $('#btnSave').text('Update');
        }
    }).always(hideLoading);
});

$(document).on('click', '.btn-delete', function () {
    const id = $(this).data('id');
    confirmAction({
        title: 'Hapus Sales',
        text: 'Data yang dihapus tidak dapat dikembalikan.',
        onConfirm: () => ajaxPost(ENDPOINT.del(id), {}, () => loadTable())
    });
});
</script>
<?= $this->endSection() ?>