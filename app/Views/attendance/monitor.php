<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="kpi-card"><div class="kpi-value"><?= $summary['check_in'] ?></div><div class="kpi-label">Masuk Hari Ini</div></div></div>
    <div class="col-md-3"><div class="kpi-card"><div class="kpi-value"><?= $summary['check_out'] ?></div><div class="kpi-label">Pulang Hari Ini</div></div></div>
    <div class="col-md-3"><div class="kpi-card"><div class="kpi-value profit-negative"><?= $summary['late'] ?></div><div class="kpi-label">Terlambat</div></div></div>
    <div class="col-md-3"><div class="kpi-card"><div class="kpi-value profit-negative"><?= $summary['outside'] ?></div><div class="kpi-label">Di Luar Area</div></div></div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-clock me-1"></i> Data Absensi</div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-3"><input type="text" id="searchInput" class="form-control" placeholder="Cari nama..." value="<?= esc($search ?? '') ?>"></div>
            <div class="col-md-2"><input type="date" id="fromFilter" class="form-control" value="<?= esc($from) ?>"></div>
            <div class="col-md-2"><input type="date" id="toFilter" class="form-control" value="<?= esc($to) ?>"></div>
            <div class="col-md-2">
                <select id="typeFilter" class="form-select">
                    <option value="">Semua Jenis</option>
                    <option value="CHECK_IN">Masuk</option>
                    <option value="CHECK_OUT">Pulang</option>
                </select>
            </div>
            <div class="col-md-3">
                <select id="locFilter" class="form-select">
                    <option value="">Semua Lokasi</option>
                    <option value="INSIDE_AREA">Dalam Area</option>
                    <option value="OUTSIDE_AREA">Di Luar Area</option>
                    <option value="UNKNOWN">Tidak Diketahui</option>
                </select>
            </div>
        </div>
        <div id="tableContainer"><?= $this->include('attendance/_table') ?></div>
    </div>
</div>

<div class="modal fade" id="modalPhoto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title" id="photoTitle">Foto Absensi</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center"><img id="photoFull" class="img-fluid rounded" alt="Foto absensi"></div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const LIST_URL = "<?= base_url('admin/attendance') ?>";

function loadTable(page = 1) {
    showLoading();
    $.get(LIST_URL, {
        search: $('#searchInput').val(), from: $('#fromFilter').val(), to: $('#toFilter').val(),
        type: $('#typeFilter').val(), location_status: $('#locFilter').val(), page
    }, html => $('#tableContainer').html(html)).always(hideLoading);
}

$(function () {
    let t;
    $('#searchInput').on('keyup', () => { clearTimeout(t); t = setTimeout(() => loadTable(1), 400); });
    $('#fromFilter, #toFilter, #typeFilter, #locFilter').on('change', () => loadTable(1));

    $(document).on('click', '#tableContainer .pagination a', function (e) {
        e.preventDefault();
        loadTable(new URL(this.href).searchParams.get('page') || 1);
    });

    $(document).on('click', '.btn-photo', function () {
        $('#photoTitle').text($(this).data('title'));
        $('#photoFull').attr('src', $(this).data('src'));
        new bootstrap.Modal('#modalPhoto').show();
    });
});
</script>
<?= $this->endSection() ?>
