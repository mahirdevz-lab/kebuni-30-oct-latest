<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                    <i class="fa-solid fa-boxes-stacked text-primary fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Barang (Aktif)</div>
                    <div class="fs-4 fw-bold"><?= number_format($valuation['total_qty'], 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success bg-opacity-10 p-3">
                    <i class="fa-solid fa-rupiah-sign text-success fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Valuasi (Aktif)</div>
                    <div class="fs-4 fw-bold">Rp <?= number_format($valuation['total_value'], 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="section-header"><i class="fa-solid fa-plus me-1"></i> Tambah Aset</div>
    <div class="card-body">
        <form id="assetForm" class="row g-3">
            <input type="hidden" name="id" id="assetId">
            <div class="col-md-4">
                <label class="form-label">Nama Barang <span class="text-danger">*</span></label>
                <input type="text" name="name" id="assetName" class="form-control" placeholder="cth: BOX FILE" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Qty <span class="text-danger">*</span></label>
                <input type="number" name="qty" id="assetQty" class="form-control" value="1" min="0" step="any" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Harga (Rp) <span class="text-danger">*</span></label>
                <input type="number" name="price" id="assetPrice" class="form-control" value="0" min="0" step="any" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tgl Pembelian</label>
                <input type="date" name="purchase_date" id="assetDate" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" id="assetStatus" class="form-select">
                    <option value="AKTIF">AKTIF</option>
                    <option value="RUSAK">RUSAK</option>
                    <option value="HILANG">HILANG</option>
                </select>
            </div>
            <div class="col-md-10">
                <label class="form-label">Catatan</label>
                <input type="text" name="notes" id="assetNotes" class="form-control" placeholder="Opsional">
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary" id="btnSave"><i class="fa-solid fa-plus me-1"></i> Tambah</button>
                <button type="button" class="btn btn-light d-none" id="btnCancel">Batal</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-list me-1"></i> Daftar Aset Kantor</div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" id="searchInput" class="form-control" placeholder="Cari nama barang..." value="<?= esc($search ?? '') ?>">
            </div>
            <div class="col-md-3">
                <select id="statusFilter" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="AKTIF" <?= ($status ?? '') === 'AKTIF' ? 'selected' : '' ?>>AKTIF</option>
                    <option value="RUSAK" <?= ($status ?? '') === 'RUSAK' ? 'selected' : '' ?>>RUSAK</option>
                    <option value="HILANG" <?= ($status ?? '') === 'HILANG' ? 'selected' : '' ?>>HILANG</option>
                </select>
            </div>
        </div>
        <div id="tableContainer"><?= $this->include('office_asset/_table') ?></div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const BASE = "<?= base_url('admin/office-asset') ?>";
let editId = null;

function loadTable() {
    $.get(BASE, { search: $('#searchInput').val(), status: $('#statusFilter').val() }, function (html) {
        $('#tableContainer').html(html);
    });
}

$(function () {
    let timer;
    $('#searchInput').on('keyup', function () {
        clearTimeout(timer);
        timer = setTimeout(loadTable, 400);
    });
    $('#statusFilter').on('change', loadTable);
});

$('#assetForm').on('submit', function (e) {
    e.preventDefault();
    const url = editId ? BASE + '/' + editId + '/update' : BASE + '/store';
    $.post(url, $(this).serialize())
        .done(res => {
            if (res.success) { showToast(res.message, 'success'); resetForm(); loadTable(); setTimeout(() => location.reload(), 800); }
            else showToast(res.message, 'error');
        })
        .fail(() => showToast('Gagal menyimpan.', 'error'));
});

function editAsset(id) {
    const row = $('#row-' + id);
    editId = id;
    $('#assetId').val(id);
    $('#assetName').val(row.data('name'));
    $('#assetQty').val(row.data('qty'));
    $('#assetPrice').val(row.data('price'));
    $('#assetDate').val(row.data('date'));
    $('#assetStatus').val(row.data('status'));
    $('#assetNotes').val(row.data('notes'));
    $('#btnSave').html('<i class="fa-solid fa-save me-1"></i> Simpan');
    $('#btnCancel').removeClass('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    editId = null;
    $('#assetForm')[0].reset();
    $('#assetQty').val(1);
    $('#assetPrice').val(0);
    $('#btnSave').html('<i class="fa-solid fa-plus me-1"></i> Tambah');
    $('#btnCancel').addClass('d-none');
}

$('#btnCancel').on('click', resetForm);

function deleteAsset(id, name) {
    if (! confirm('Hapus "' + name + '"?')) return;
    $.post(BASE + '/' + id + '/delete')
        .done(res => {
            if (res.success) { showToast(res.message, 'success'); loadTable(); setTimeout(() => location.reload(), 800); }
            else showToast(res.message, 'error');
        });
}
</script>
<?= $this->endSection() ?>
