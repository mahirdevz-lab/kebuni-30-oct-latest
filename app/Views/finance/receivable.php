<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-hand-holding-dollar me-1"></i> Daftar Piutang (dari Taking Order dengan Payment Term kredit)</div>
    <div class="card-body">
        <p class="text-muted small">
            Piutang dibuat otomatis saat Taking Order (PO) dengan Payment Term kredit (bukan Cash) berstatus <strong>Completed</strong>.
        </p>
        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" id="searchInput" class="form-control" placeholder="Cari invoice / nama customer...">
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
        <div id="tableContainer"><?= $this->include('finance/_receivable_table') ?></div>
    </div>
</div>

<!-- Modal Bayar Piutang -->
<div class="modal fade" id="modalPay" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bayar Piutang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Invoice: <strong id="payInvoice"></strong></p>
                <p class="mb-3">Sisa Piutang: <strong id="payRemaining"></strong></p>
                <div class="mb-3">
                    <label class="form-label">Tanggal Bayar</label>
                    <input type="date" id="payDate" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Jumlah Bayar (Rp)</label>
                    <input type="number" step="0.01" min="0" id="payAmount" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Masuk ke Rekening</label>
                    <select id="payAccount" class="form-select">
                        <?php foreach (($cashAccounts ?? []) as $ca): ?>
                            <option value="<?= $ca['id'] ?>" <?= ($ca['name'] === 'Bank Utama') ? 'selected' : '' ?>><?= esc($ca['name']) ?></option>
                        <?php endforeach ?>
                    </select>
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
    list: "<?= base_url('admin/finance/receivable') ?>",
    pay:  (id) => `<?= base_url('admin/finance/receivable') ?>/${id}/pay`,
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

    $(document).on('click', '.btn-pay-receivable', function () {
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
            cash_account_id: $('#payAccount').val(),
        }, () => {
            bootstrap.Modal.getInstance(document.getElementById('modalPay')).hide();
            loadTable();
        });
    });
});
</script>
<?= $this->endSection() ?>
