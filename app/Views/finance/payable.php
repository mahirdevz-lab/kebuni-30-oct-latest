<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card mb-4">
    <div class="section-header" id="formTitle"><i class="fa-solid fa-file-invoice-dollar me-1"></i> Tambah Hutang</div>
    <div class="card-body">
        <form id="payableForm">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Supplier <span class="text-danger">*</span></label>
                    <select name="supplier_id" id="supplier_id" class="form-select" required>
                        <option value="">-- Pilih Supplier --</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= esc($s['nama_supplier']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">No. Invoice <span class="text-danger">*</span></label>
                    <input type="text" name="invoice_number" id="invoice_number" class="form-control" required>
                    <div class="invalid-feedback" data-error-for="invoice_number"></div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="transaction_date" id="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Jatuh Tempo</label>
                    <input type="date" name="due_date" id="due_date" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Jumlah (Rp) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" name="total_amount" id="total_amount" class="form-control" required>
                    <div class="invalid-feedback" data-error-for="total_amount"></div>
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
    <div class="section-header"><i class="fa-solid fa-list me-1"></i> Daftar Hutang</div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" id="searchInput" class="form-control" placeholder="Cari invoice / nama supplier...">
            </div>
            <div class="col-md-3">
                <select id="statusFilter" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="UNPAID">Unpaid</option>
                    <option value="PARTIAL">Partial</option>
                    <option value="PAID">Paid</option>
                    <option value="OVERDUE">Overdue</option>
                </select>
            </div>
        </div>
        <div id="tableContainer"><?= $this->include('finance/_payable_table') ?></div>
    </div>
</div>

<!-- Modal Bayar Hutang -->
<div class="modal fade" id="modalPay" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bayar Hutang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Invoice: <strong id="payInvoice"></strong></p>
                <p class="mb-3">Sisa Hutang: <strong id="payRemaining"></strong></p>
                <div class="mb-3">
                    <label class="form-label">Tanggal Bayar</label>
                    <input type="date" id="payDate" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Jumlah Bayar (Rp)</label>
                    <input type="number" step="0.01" min="0" id="payAmount" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Catatan</label>
                    <input type="text" id="payNotes" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btnConfirmPay">Simpan Pembayaran</button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const ENDPOINT = {
    list:  "<?= base_url('admin/finance/payable') ?>",
    store: "<?= base_url('admin/finance/payable/store') ?>",
    pay:   (id) => `<?= base_url('admin/finance/payable') ?>/${id}/pay`,
};

let currentPayId = null;

function loadTable(page = 1) {
    showLoading();
    $.get(ENDPOINT.list, { search: $('#searchInput').val(), status: $('#statusFilter').val(), page }, function (html) {
        $('#tableContainer').html(html);
    }).always(hideLoading);
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

    $('#payableForm').on('submit', function (e) {
        e.preventDefault();

        $('.invalid-feedback').text('');
        $('.is-invalid').removeClass('is-invalid');
        showLoading();

        $.post(ENDPOINT.store, $(this).serialize())
            .done(function (res) {
                if (res.success) {
                    showToast(res.message, 'success');
                    $('#payableForm')[0].reset();
                    $('#transaction_date').val('<?= date('Y-m-d') ?>');
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

    $(document).on('click', '.btn-pay-payable', function () {
        currentPayId = $(this).data('id');
        $('#payInvoice').text($(this).data('invoice'));
        $('#payRemaining').text($(this).data('remaining'));
        $('#payAmount').val($(this).data('remaining-raw'));
        $('#payDate').val('<?= date('Y-m-d') ?>');
        $('#payNotes').val('');
        new bootstrap.Modal('#modalPay').show();
    });

    $('#btnConfirmPay').on('click', function () {
        const amount = parseFloat($('#payAmount').val());
        if (! amount || amount <= 0) { showToast('Jumlah pembayaran tidak valid.', 'error'); return; }

        ajaxPost(ENDPOINT.pay(currentPayId), {
            amount: amount,
            date: $('#payDate').val(),
            notes: $('#payNotes').val(),
        }, () => {
            bootstrap.Modal.getInstance(document.getElementById('modalPay')).hide();
            loadTable();
        });
    });
});
</script>
<?= $this->endSection() ?>
