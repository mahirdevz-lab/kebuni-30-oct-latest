<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card mb-4">
    <div class="section-header" id="formTitle"><i class="fa-solid fa-location-crosshairs me-1"></i> Tambah Lokasi Kantor</div>
    <div class="card-body">
        <p class="text-muted small">Absensi dianggap "Dalam Area" jika koordinat karyawan berada dalam radius salah satu lokasi aktif.</p>
        <form id="locForm">
            <input type="hidden" id="loc_id">
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label">Nama <span class="text-danger">*</span></label><input type="text" name="name" id="name" class="form-control" required></div>
                <div class="col-md-3"><label class="form-label">Latitude <span class="text-danger">*</span></label><input type="number" step="any" name="latitude" id="latitude" class="form-control" required placeholder="-6.9175"></div>
                <div class="col-md-3"><label class="form-label">Longitude <span class="text-danger">*</span></label><input type="number" step="any" name="longitude" id="longitude" class="form-control" required placeholder="106.9260"></div>
                <div class="col-md-3"><label class="form-label">Radius (meter) <span class="text-danger">*</span></label><input type="number" min="1" name="radius_meter" id="radius_meter" class="form-control" value="100" required></div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button type="button" class="btn btn-light px-4" id="btnReset">Bersihkan</button>
                <button type="submit" class="btn btn-primary px-4" id="btnSave">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-list me-1"></i> Daftar Lokasi Kantor</div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <label class="visually-hidden" for="searchInput">Cari lokasi</label>
                <input type="text" id="searchInput" class="form-control" placeholder="Cari nama lokasi..." value="<?= esc($search ?? '') ?>">
            </div>
        </div>
        <div id="tableContainer"><?= $this->include('attendance/_locations_table') ?></div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const BASE = "<?= base_url('admin/attendance/locations') ?>";

function loadTable(page = 1) {
    refreshInPlace('#tableContainer', BASE, { search: $('#searchInput').val() }, page);
}

let locSearchTimer;
$('#searchInput').on('keyup', function () {
    clearTimeout(locSearchTimer);
    locSearchTimer = setTimeout(() => loadTable(1), 400);
});

$(document).on('click', '#tableContainer .pagination a', function (e) {
    e.preventDefault();
    loadTable(new URL(this.href).searchParams.get('page') || 1);
});

function resetForm() {
    $('#locForm')[0].reset();
    $('#loc_id').val('');
    $('#radius_meter').val(100);
    $('#formTitle').html('<i class="fa-solid fa-location-crosshairs me-1"></i> Tambah Lokasi Kantor');
    $('#btnSave').text('Simpan');
}

$('#btnReset').on('click', resetForm);

$('#locForm').on('submit', function (e) {
    e.preventDefault();
    const id = $('#loc_id').val();
    ajaxPost(id ? `${BASE}/${id}/update` : `${BASE}/store`, $(this).serialize(), () => {
        resetForm();
        loadTable();
    });
});

$(document).on('click', '.btn-edit', function () {
    const l = $(this).data('loc');
    $('#loc_id').val(l.id);
    $('#name').val(l.name);
    $('#latitude').val(l.latitude);
    $('#longitude').val(l.longitude);
    $('#radius_meter').val(l.radius_meter);
    $('#formTitle').html('<i class="fa-solid fa-pen me-1"></i> Edit Lokasi Kantor');
    $('#btnSave').text('Update');
    $('html, body').animate({ scrollTop: 0 }, 300);
});

$(document).on('click', '.btn-toggle', function () {
    ajaxPost(`${BASE}/${$(this).data('id')}/toggle`, {}, () => loadTable());
});

$(document).on('click', '.btn-delete', function () {
    const id = $(this).data('id');
    confirmAction({
        title: 'Hapus Lokasi Kantor',
        text: 'Data absensi lama tetap tersimpan, tetapi tidak lagi terhubung ke lokasi ini.',
        onConfirm: () => ajaxPost(`${BASE}/${id}/delete`, {}, () => loadTable())
    });
});
</script>
<?= $this->endSection() ?>
