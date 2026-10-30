<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card">
            <div class="section-header" id="formTitle"><i class="fa-solid fa-hand-holding-dollar me-1"></i> Tambah Payment Term</div>
            <div class="card-body">
                <form id="termForm">
                    <input type="hidden" name="id" id="term_id">
                    <div class="mb-3">
                        <label class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" required placeholder="Tempo 7 Hari">
                        <div class="invalid-feedback" data-error-for="name"></div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Days <span class="text-danger">*</span></label>
                            <input type="number" min="0" name="days" id="days" class="form-control" required value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Daily Interest (%)</label>
                            <input type="number" step="0.0001" min="0" name="daily_interest_percent" id="daily_interest_percent" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="mb-3 mt-2">
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
            <div class="section-header"><i class="fa-solid fa-list me-1"></i> Daftar Payment Term</div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-7">
                        <input type="text" id="searchInput" class="form-control" placeholder="Cari nama payment term..." value="<?= esc($search ?? '') ?>">
                    </div>
                    <div class="col-md-5">
                        <select id="statusFilter" class="form-select">
                            <option value="">Semua Status</option>
                            <option value="aktif" <?= ($status ?? '') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                            <option value="nonaktif" <?= ($status ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div id="tableContainer"><?= $this->include('payment_term/_table') ?></div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const ENDPOINT = {
    list:   "<?= base_url('admin/payment-term') ?>",
    store:  "<?= base_url('admin/payment-term/store') ?>",
    edit:   (id) => `<?= base_url('admin/payment-term') ?>/${id}/edit`,
    update: (id) => `<?= base_url('admin/payment-term') ?>/${id}/update`,
    del:    (id) => `<?= base_url('admin/payment-term') ?>/${id}/delete`,
};

function loadTable(page = 1) {
    $('#tableContainer').html(tableSkeleton(6));
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
    $('#termForm')[0].reset();
    $('#term_id').val('');
    $('#formTitle').html('<i class="fa-solid fa-hand-holding-dollar me-1"></i> Tambah Payment Term');
    $('#btnSave').text('Simpan');
    $('.invalid-feedback').text('');
    $('.is-invalid').removeClass('is-invalid');
}

$('#btnReset').on('click', resetForm);

$('#termForm').on('submit', function (e) {
    e.preventDefault();
    const id = $('#term_id').val();
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
            $('#term_id').val(d.id);
            $('#name').val(d.name);
            $('#days').val(d.days);
            $('#daily_interest_percent').val(d.daily_interest_percent);
            $('#notes').val(d.notes);
            $('#formTitle').html('<i class="fa-solid fa-pen me-1"></i> Edit Payment Term');
            $('#btnSave').text('Update');
        }
    }).always(hideLoading);
});

$(document).on('click', '.btn-delete', function () {
    const id = $(this).data('id');
    confirmAction({
        title: 'Hapus Payment Term',
        text: 'Data yang dihapus tidak dapat dikembalikan.',
        onConfirm: () => ajaxPost(ENDPOINT.del(id), {}, () => loadTable())
    });
});
</script>
<?= $this->endSection() ?>
