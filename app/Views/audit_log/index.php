<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="section-header"><i class="fa-solid fa-clipboard-list me-1"></i> Audit Log</div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-3"><input type="text" id="searchInput" class="form-control" placeholder="Cari aksi / keterangan / user..."></div>
            <div class="col-md-2">
                <select id="moduleFilter" class="form-select">
                    <option value="">Semua Modul</option>
                    <?php foreach ($modules as $m): ?><option value="<?= esc($m) ?>"><?= esc($m) ?></option><?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2"><input type="date" id="fromFilter" class="form-control"></div>
            <div class="col-md-2"><input type="date" id="toFilter" class="form-control"></div>
        </div>
        <div id="tableContainer"><?= $this->include('audit_log/_table') ?></div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const LIST_URL = "<?= base_url('admin/audit-log') ?>";
function loadTable(page = 1) {
    showLoading();
    $.get(LIST_URL, { search: $('#searchInput').val(), module: $('#moduleFilter').val(), from: $('#fromFilter').val(), to: $('#toFilter').val(), page },
        html => $('#tableContainer').html(html)).always(hideLoading);
}
$(function () {
    let t;
    $('#searchInput').on('keyup', () => { clearTimeout(t); t = setTimeout(() => loadTable(1), 400); });
    $('#moduleFilter, #fromFilter, #toFilter').on('change', () => loadTable(1));
    $(document).on('click', '#tableContainer .pagination a', function (e) {
        e.preventDefault();
        loadTable(new URL(this.href).searchParams.get('page') || 1);
    });
});
</script>
<?= $this->endSection() ?>
