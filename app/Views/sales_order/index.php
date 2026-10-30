<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="section-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-list-check me-1"></i> Monitoring PO</span>
        <a href="<?= base_url('admin/sales-order/create') ?>" class="btn btn-sm btn-light">
            <i class="fa-solid fa-plus me-1"></i> Taking Order Baru
        </a>
    </div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-3">
                <input type="text" id="searchInput" class="form-control" placeholder="Cari No. PO / Customer...">
            </div>
            <div class="col-md-2">
                <select id="statusFilter" class="form-select">
                    <option value="">Semua Status</option>
                    <?php foreach (['Draft','Submitted','Approved','Processing','Completed','Cancelled'] as $s): ?>
                        <option value="<?= $s ?>"><?= $s ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2"><input type="date" id="fromFilter" class="form-control" placeholder="Dari"></div>
            <div class="col-md-2"><input type="date" id="toFilter" class="form-control" placeholder="Sampai"></div>
        </div>
        <div id="tableContainer"><?= $this->include('sales_order/_table') ?></div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const ENDPOINT = { list: "<?= base_url('admin/sales-order') ?>" };

function loadTable(page = 1) {
    showLoading();
    $.get(ENDPOINT.list, {
        search: $('#searchInput').val(), status: $('#statusFilter').val(),
        from: $('#fromFilter').val(), to: $('#toFilter').val(), page
    }, html => $('#tableContainer').html(html)).always(hideLoading);
}

$(function () {
    let t;
    $('#searchInput').on('keyup', () => { clearTimeout(t); t = setTimeout(() => loadTable(1), 400); });
    $('#statusFilter, #fromFilter, #toFilter').on('change', () => loadTable(1));
    $(document).on('click', '#tableContainer .pagination a', function (e) {
        e.preventDefault();
        loadTable(new URL(this.href).searchParams.get('page') || 1);
    });
});
</script>
<?= $this->endSection() ?>
