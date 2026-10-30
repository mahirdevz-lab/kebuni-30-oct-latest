<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card mb-4">
    <div class="section-header" id="formTitle"><i class="fa-solid fa-box me-1"></i> Tambah Packing Material</div>
    <div class="card-body">
        <form id="materialForm">
            <input type="hidden" name="id" id="material_id">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Code <span class="text-danger">*</span></label>
                    <input type="text" name="code" id="code" class="form-control" required placeholder="WRG-30">
                    <div class="invalid-feedback" data-error-for="code"></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nama <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" class="form-control" required placeholder="Waring 30 Kg">
                    <div class="invalid-feedback" data-error-for="name"></div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Unit <span class="text-danger">*</span></label>
                    <input type="text" name="unit" id="unit" class="form-control" required placeholder="Pcs">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Capacity (Kg)</label>
                    <input type="number" step="0.01" name="capacity_kg" id="capacity_kg" class="form-control" placeholder="Kosongkan jika tidak berbasis kapasitas">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" id="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button type="button" class="btn btn-light px-4" id="btnReset">Bersihkan</button>
                <button type="submit" class="btn btn-primary px-4" id="btnSave">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-list me-1"></i> Daftar Packing Material</div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" id="searchInput" class="form-control" placeholder="Cari nama / code...">
            </div>
            <div class="col-md-3">
                <select id="statusFilter" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="aktif">Aktif</option>
                    <option value="nonaktif">Nonaktif</option>
                </select>
            </div>
        </div>
        <div id="tableContainer"><?= $this->include('packing_material/_table') ?></div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const ENDPOINT = {
    list:   "<?= base_url('admin/packing-material') ?>",
    store:  "<?= base_url('admin/packing-material/store') ?>",
    edit:   (id) => `<?= base_url('admin/packing-material') ?>/${id}/edit`,
    update: (id) => `<?= base_url('admin/packing-material') ?>/${id}/update`,
    del:    (id) => `<?= base_url('admin/packing-material') ?>/${id}/delete`,
};

function loadTable(page = 1) {
    showLoading();
    $.get(ENDPOINT.list, { search: $('#searchInput').val(), status: $('#statusFilter').val(), page }, function (html) {
        $('#tableContainer').html(html);
    }).always(hideLoading);
}

function resetForm() {
    $('#materialForm')[0].reset();
    $('#material_id').val('');
    $('#formTitle').html('<i class="fa-solid fa-box me-1"></i> Tambah Packing Material');
    $('#btnSave').text('Simpan');
    $('.invalid-feedback').text('');
    $('.is-invalid').removeClass('is-invalid');
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

    $('#btnReset').on('click', resetForm);

    $('#materialForm').on('submit', function (e) {
        e.preventDefault();
        const id = $('#material_id').val();
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

    $(document).on('click', '.btn-edit-material', function () {
        const id = $(this).data('id');
        showLoading();
        $.get(ENDPOINT.edit(id), function (res) {
            if (res.success) {
                const d = res.data;
                $('#material_id').val(d.id);
                $('#code').val(d.code);
                $('#name').val(d.name);
                $('#unit').val(d.unit);
                $('#capacity_kg').val(d.capacity_kg);
                $('#notes').val(d.notes);
                $('#formTitle').html('<i class="fa-solid fa-pen me-1"></i> Edit Packing Material');
                $('#btnSave').text('Update');
                $('html, body').animate({ scrollTop: 0 }, 300);
            }
        }).always(hideLoading);
    });

    $(document).on('click', '.btn-delete-material', function () {
        const id = $(this).data('id');
        confirmAction({
            title: 'Hapus Packing Material',
            text: 'Data yang dihapus tidak dapat dikembalikan.',
            onConfirm: () => ajaxPost(ENDPOINT.del(id), {}, () => loadTable())
        });
    });
});
</script>
<?= $this->endSection() ?>
