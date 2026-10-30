<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card">
            <div class="section-header" id="formTitle"><i class="fa-solid fa-layer-group me-1"></i> Tambah Pricing Tier</div>
            <div class="card-body">
                <div class="alert alert-info border-0 small py-2">
                    Kosongkan Max Qty untuk tier tanpa batas atas (mis. "5000+").
                </div>
                <form id="tierForm">
                    <input type="hidden" name="id" id="tier_id">
                    <div class="mb-3">
                        <label class="form-label">Nama Tier <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" required placeholder="Tier 1">
                        <div class="invalid-feedback" data-error-for="name"></div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Min Qty <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="min_qty" id="min_qty" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Max Qty</label>
                            <input type="number" step="0.01" min="0" name="max_qty" id="max_qty" class="form-control" placeholder="Tanpa batas">
                        </div>
                    </div>
                    <div class="mb-3 mt-2">
                        <label class="form-label">Markup (%) <span class="text-danger">*</span></label>
                        <input type="number" step="0.0001" min="0" name="markup_percent" id="markup_percent" class="form-control" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" id="start_date" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" id="end_date" class="form-control">
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
            <div class="section-header"><i class="fa-solid fa-list me-1"></i> Daftar Pricing Tier</div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-7">
                        <input type="text" id="searchInput" class="form-control" placeholder="Cari nama tier..." value="<?= esc($search ?? '') ?>">
                    </div>
                    <div class="col-md-5">
                        <select id="statusFilter" class="form-select">
                            <option value="">Semua Status</option>
                            <option value="aktif" <?= ($status ?? '') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                            <option value="nonaktif" <?= ($status ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div id="tableContainer"><?= $this->include('pricing_tier/_table') ?></div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const ENDPOINT = {
    list:   "<?= base_url('admin/pricing-tier') ?>",
    store:  "<?= base_url('admin/pricing-tier/store') ?>",
    edit:   (id) => `<?= base_url('admin/pricing-tier') ?>/${id}/edit`,
    update: (id) => `<?= base_url('admin/pricing-tier') ?>/${id}/update`,
    del:    (id) => `<?= base_url('admin/pricing-tier') ?>/${id}/delete`,
};

function loadTable(page = 1) {
    $('#tableContainer').html(tableSkeleton(7));
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
    $('#tierForm')[0].reset();
    $('#tier_id').val('');
    $('#formTitle').html('<i class="fa-solid fa-layer-group me-1"></i> Tambah Pricing Tier');
    $('#btnSave').text('Simpan');
    $('.invalid-feedback').text('');
    $('.is-invalid').removeClass('is-invalid');
}

$('#btnReset').on('click', resetForm);

$('#tierForm').on('submit', function (e) {
    e.preventDefault();
    const id = $('#tier_id').val();
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
            $('#tier_id').val(d.id);
            $('#name').val(d.name);
            $('#min_qty').val(d.min_qty);
            $('#max_qty').val(d.max_qty);
            $('#markup_percent').val(d.markup_percent);
            $('#start_date').val(d.start_date);
            $('#end_date').val(d.end_date);
            $('#notes').val(d.notes);
            $('#formTitle').html('<i class="fa-solid fa-pen me-1"></i> Edit Pricing Tier');
            $('#btnSave').text('Update');
        }
    }).always(hideLoading);
});

$(document).on('click', '.btn-delete', function () {
    const id = $(this).data('id');
    confirmAction({
        title: 'Hapus Pricing Tier',
        text: 'Data yang dihapus tidak dapat dikembalikan.',
        onConfirm: () => ajaxPost(ENDPOINT.del(id), {}, () => loadTable())
    });
});
</script>
<?= $this->endSection() ?>
