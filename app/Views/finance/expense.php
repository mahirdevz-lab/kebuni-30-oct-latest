<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php if (! empty($balances)): ?>
<div class="row g-3 mb-3" id="balanceCards">
    <?php foreach ($balances as $b): ?>
    <div class="col-md-3">
        <div class="kpi-card">
            <div class="kpi-label"><?= esc($b['name']) ?></div>
            <div class="kpi-value">Rp <?= number_format($b['balance'], 0, ',', '.') ?></div>
            <div class="small text-muted">Masuk: <?= number_format($b['total_in'], 0, ',', '.') ?> | Keluar: <?= number_format($b['total_out'], 0, ',', '.') ?></div>
        </div>
    </div>
    <?php endforeach ?>
</div>
<?php endif ?>
<div class="card mb-4">
    <div class="section-header" id="formTitle"><i class="fa-solid fa-arrow-trend-down me-1"></i> Catat Pengeluaran / Biaya Operasional</div>
    <div class="card-body">
        <form id="expenseForm">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="transaction_date" id="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    <div class="invalid-feedback" data-error-for="transaction_date"></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kategori <span class="text-danger">*</span></label>
                    <select name="category_id" id="category_id" class="form-select" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option>
                        <?php endforeach ?>
                    </select>
                    <div class="form-text">Sewa, Listrik &amp; Utility, Gaji Karyawan, Biaya Tak Terduga, dll (Operasional).</div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Rekening <span class="text-danger">*</span></label>
                    <select name="cash_account_id" id="cash_account_id" class="form-select" required>
                        <option value="">-- Pilih --</option>
                        <?php foreach ($cashAccounts as $ca): ?>
                            <option value="<?= $ca['id'] ?>"><?= esc($ca['name']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-2 d-none" id="toAccountWrap">
                    <label class="form-label">Rekening Tujuan <span class="text-danger">*</span></label>
                    <select name="to_account_id" id="to_account_id" class="form-select">
                        <option value="">-- Pilih --</option>
                        <?php foreach ($cashAccounts as $ca): ?>
                            <option value="<?= $ca['id'] ?>"><?= esc($ca['name']) ?></option>
                        <?php endforeach ?>
                    </select>
                    <div class="form-text">Dana pindah ke rekening ini.</div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Jumlah (Rp) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" name="amount" id="amount" class="form-control" required>
                    <div class="invalid-feedback" data-error-for="amount"></div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Keterangan</label>
                    <input type="text" name="description" id="description" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tanda Bukti</label>
                    <input type="text" name="proof_label" id="proof_label" class="form-control" list="proofLabels" placeholder="cth: STRUK, SCREENSHOT, NOTA">
                    <datalist id="proofLabels">
                        <option value="STRUK">
                        <option value="SCREENSHOT">
                        <option value="NOTA">
                        <option value="KWITANSI">
                        <option value="TRANSFER">
                    </datalist>
                    <div id="proofDropzone" class="border rounded mt-2 p-2 text-center text-muted small" style="border-style: dashed !important; cursor: pointer;">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Drop file / klik untuk upload
                        <input type="file" name="proof" id="proofFile" class="d-none" accept=".jpg,.jpeg,.png,.webp,.pdf">
                        <div id="proofFileName" class="text-success fw-semibold mt-1"></div>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary px-4" id="btnSave">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-list me-1"></i> Daftar Pengeluaran</div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-3">
                <input type="text" id="searchInput" class="form-control" placeholder="Cari keterangan...">
            </div>
            <div class="col-md-2">
                <input type="date" id="fromFilter" class="form-control" placeholder="Dari">
            </div>
            <div class="col-md-2">
                <input type="date" id="toFilter" class="form-control" placeholder="Sampai">
            </div>
            <div class="col-md-2">
                <select id="categoryFilter" class="form-select">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2">
                <select id="accountFilter" class="form-select">
                    <option value="">Semua Rekening</option>
                    <?php foreach ($cashAccounts as $ca): ?>
                        <option value="<?= $ca['id'] ?>"><?= esc($ca['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
        <div id="tableContainer"><?= $this->include('finance/_expense_table') ?></div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const ENDPOINT = {
    list:  "<?= base_url('admin/finance/expense') ?>",
    store: "<?= base_url('admin/finance/expense/store') ?>",
};

function loadTable(page = 1) {
    showLoading();
    $.get(ENDPOINT.list, {
        search: $('#searchInput').val(),
        from: $('#fromFilter').val(),
        to: $('#toFilter').val(),
        category_id: $('#categoryFilter').val(),
        cash_account_id: $('#accountFilter').val(),
        page
    }, function (html) {
        $('#tableContainer').html(html);
    }).always(hideLoading);
}

$(function () {
    let searchTimer;
    $('#searchInput').on('keyup', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadTable(1), 400);
    });
    $('#fromFilter, #toFilter, #categoryFilter, #accountFilter').on('change', () => loadTable(1));

    $(document).on('click', '#tableContainer .pagination a', function (e) {
        e.preventDefault();
        const url = new URL(this.href);
        loadTable(url.searchParams.get('page') || 1);
    });

    // Tampilkan Rekening Tujuan saat kategori = Pemindahan Dana
    $('#category_id').on('change', function () {
        const label = $(this).find('option:selected').text().trim().toLowerCase();
        const isTransfer = label === 'pemindahan dana';
        $('#toAccountWrap').toggleClass('d-none', !isTransfer);
        $('#to_account_id').prop('required', isTransfer);
        if (!isTransfer) $('#to_account_id').val('');
    });

    // Drag & drop bukti
    const $dz = $('#proofDropzone'), $fi = $('#proofFile');
    $dz.on('click', () => $fi.click());
    $dz.on('dragover', e => { e.preventDefault(); $dz.addClass('border-primary'); });
    $dz.on('dragleave', () => $dz.removeClass('border-primary'));
    $dz.on('drop', e => {
        e.preventDefault(); $dz.removeClass('border-primary');
        const f = e.originalEvent.dataTransfer.files[0];
        if (f) { $fi[0].files = e.originalEvent.dataTransfer.files; $('#proofFileName').text(f.name); }
    });
    $fi.on('change', function () {
        const f = this.files[0];
        $('#proofFileName').text(f ? f.name : '');
    });

    $('#expenseForm').on('submit', function (e) {
        e.preventDefault();

        $('.invalid-feedback').text('');
        $('.is-invalid').removeClass('is-invalid');
        showLoading();

        const fd = new FormData(this);
        $.ajax({ url: ENDPOINT.store, type: 'POST', data: fd, processData: false, contentType: false })
            .done(function (res) {
                if (res.success) {
                    showToast(res.message, 'success');
                    $('#expenseForm')[0].reset();
                    $('#transaction_date').val('<?= date('Y-m-d') ?>');
                    $('#proofFileName').text('');
                    loadTable();
                    // Refresh kartu saldo tanpa reload halaman
                    $.get(ENDPOINT.list, function (html) {
                        const cards = $(html).find('#balanceCards').html();
                        if (cards) $('#balanceCards').html(cards);
                    });
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
});
</script>
<?= $this->endSection() ?>

