<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card">
            <div class="section-header" id="formTitle"><i class="fa-solid fa-location-dot me-1"></i> Tambah Destination</div>
            <div class="card-body">
                <form id="destinationForm">
                    <input type="hidden" name="id" id="destination_id">
                    <div class="mb-3">
                        <label class="form-label">Nama Destination <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" required placeholder="Jakarta">
                        <div class="invalid-feedback" data-error-for="name"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Code</label>
                        <input type="text" name="code" id="code" class="form-control" placeholder="JKT">
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
            <div class="section-header"><i class="fa-solid fa-list me-1"></i> Daftar Destination</div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-7">
                        <input type="text" id="searchInput" class="form-control" placeholder="Cari nama / code destination..." value="<?= esc($search ?? '') ?>">
                    </div>
                    <div class="col-md-5">
                        <select id="statusFilter" class="form-select">
                            <option value="">Semua Status</option>
                            <option value="aktif" <?= ($status ?? '') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                            <option value="nonaktif" <?= ($status ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div id="tableContainer"><?= $this->include('destination/_table') ?></div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const ENDPOINT = {
    list:   "<?= base_url('admin/destination') ?>",
    store:  "<?= base_url('admin/destination/store') ?>",
    edit:   (id) => `<?= base_url('admin/destination') ?>/${id}/edit`,
    update: (id) => `<?= base_url('admin/destination') ?>/${id}/update`,
    del:    (id) => `<?= base_url('admin/destination') ?>/${id}/delete`,
};

function loadTable(page = 1) {
    $('#tableContainer').html(tableSkeleton(5));
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
    $('#destinationForm')[0].reset();
    $('#destination_id').val('');
    $('#formTitle').html('<i class="fa-solid fa-location-dot me-1"></i> Tambah Destination');
    $('#btnSave').text('Simpan');
    $('.invalid-feedback').text('');
    $('.is-invalid').removeClass('is-invalid');
}

$('#btnReset').on('click', resetForm);

$('#destinationForm').on('submit', function (e) {
    e.preventDefault();
    const id = $('#destination_id').val();
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
            $('#destination_id').val(res.data.id);
            $('#name').val(res.data.name);
            $('#code').val(res.data.code);
            $('#notes').val(res.data.notes);
            $('#formTitle').html('<i class="fa-solid fa-pen me-1"></i> Edit Destination');
            $('#btnSave').text('Update');
        }
    }).always(hideLoading);
});

$(document).on('click', '.btn-delete', function () {
    const id = $(this).data('id');
    confirmAction({
        title: 'Hapus Destination',
        text: 'Data yang dihapus tidak dapat dikembalikan.',
        onConfirm: () => ajaxPost(ENDPOINT.del(id), {}, () => loadTable())
    });
});
</script>
<?= $this->endSection() ?>
