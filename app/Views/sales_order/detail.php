<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php $badgeMap = ['Draft'=>'bg-secondary','Submitted'=>'bg-info text-dark','Approved'=>'bg-primary','Processing'=>'bg-warning text-dark','Completed'=>'bg-success','Cancelled'=>'bg-danger']; ?>

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <span class="badge bg-light text-dark border mb-2"><?= esc($order['order_no']) ?></span>
                <span class="badge <?= $badgeMap[$order['status']] ?> mb-2"><?= esc($order['status']) ?></span>
                <h4 class="mb-1"><?= esc($order['nama_account']) ?></h4>
                <p class="text-muted mb-0">
                    <i class="fa-solid fa-calendar-days me-1"></i> <?= tgl_id($order['order_date']) ?>
                    &middot; <?= esc($order['price_period_name']) ?> (<?= esc($order['period_code']) ?>)
                    &middot; <?= esc($order['payment_term_name']) ?>
                    <?php if (! empty($order['sales_name'])): ?>
                        &middot; Sales: <?= esc($order['sales_name']) ?>
                    <?php endif ?>
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= base_url('admin/sales-order/' . $order['id'] . '/print') ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-print me-1"></i> Cetak</a>
                <?php if ($order['status'] === 'Draft'): ?>
                    <a href="<?= base_url('admin/sales-order/' . $order['id'] . '/edit') ?>" class="btn btn-sm btn-outline-warning"><i class="fa-solid fa-pen me-1"></i> Edit</a>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="btnDelete"><i class="fa-solid fa-trash me-1"></i> Delete</button>
                    <button type="button" class="btn btn-sm btn-success" id="btnSubmit"><i class="fa-solid fa-paper-plane me-1"></i> Submit</button>
                <?php elseif ($order['status'] === 'Submitted'): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="btnCancel"><i class="fa-solid fa-ban me-1"></i> Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="btnApprove"><i class="fa-solid fa-check me-1"></i> Approve</button>
                <?php elseif ($order['status'] === 'Approved'): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="btnCancel"><i class="fa-solid fa-ban me-1"></i> Cancel</button>
                    <button type="button" class="btn btn-sm btn-warning" id="btnProcess"><i class="fa-solid fa-gears me-1"></i> Process</button>
                <?php elseif ($order['status'] === 'Processing'): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="btnCancel"><i class="fa-solid fa-ban me-1"></i> Cancel</button>
                    <button type="button" class="btn btn-sm btn-success" id="btnComplete"><i class="fa-solid fa-flag-checkered me-1"></i> Complete</button>
                <?php endif ?>
            </div>
        </div>

        <hr>
        <div class="row g-3">
            <div class="col-md-2"><div class="kpi-card"><div class="kpi-label">Total Qty</div><div class="kpi-value"><?= number_format((float) $order['total_qty'], 0, ',', '.') ?> Kg</div></div></div>
            <div class="col-md-2"><div class="kpi-card"><div class="kpi-label">Total Labour</div><div class="kpi-value">Rp <?= number_format((float) $order['total_labour_cost'], 0, ',', '.') ?></div></div></div>
            <div class="col-md-2"><div class="kpi-card"><div class="kpi-label">Total COGS</div><div class="kpi-value">Rp <?= number_format((float) $order['total_cogs'], 0, ',', '.') ?></div></div></div>
            <div class="col-md-3"><div class="kpi-card"><div class="kpi-label">Grand Total</div><div class="kpi-value">Rp <?= number_format((float) $order['grand_total'], 0, ',', '.') ?></div></div></div>
            <div class="col-md-3"><div class="kpi-card"><div class="kpi-label">Estimated Profit</div><div class="kpi-value <?= $order['estimated_profit'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">Rp <?= number_format((float) $order['estimated_profit'], 0, ',', '.') ?> (<?= $order['estimated_profit_percent'] ?>%)</div></div></div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <div class="kpi-card">
                    <div class="kpi-label">Sales Commission</div>
                    <div class="kpi-value">Rp <?= number_format((float) ($order['commission_amount'] ?? 0), 0, ',', '.') ?> <span class="small text-muted">(<?= esc($order['commission_percent'] ?? 0) ?>%)</span></div>
                    <?php
                    $cTotal = (float) ($order['commission_amount'] ?? 0);
                    $cPaid = (float) ($order['commission_paid_amount'] ?? 0);
                    $cStatus = $order['commission_status'] ?? 'UNPAID';
                    $cRemaining = max(0, $cTotal - $cPaid);
                    $cBadge = $cStatus === 'PAID' ? 'bg-success' : ($cStatus === 'PARTIAL' ? 'bg-warning' : 'bg-danger');
                    ?>
                    <?php if ($cTotal > 0): ?>
                    <div class="mt-2 small">
                        <span class="badge <?= $cBadge ?>"><?= $cStatus ?></span>
                        <span class="text-muted">Dibayar: Rp <?= number_format($cPaid, 0, ',', '.') ?></span>
                        <?php if ($cRemaining > 0): ?>
                        <span class="text-danger fw-semibold">Sisa: Rp <?= number_format($cRemaining, 0, ',', '.') ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="mt-2">
                        <?php if ($cStatus !== 'PAID'): ?>
                        <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalPayCommission">
                            <i class="bi bi-cash-coin"></i> Bayar Komisi
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCommissionHistory">
                            <i class="bi bi-clock-history"></i> Riwayat
                        </button>
                    </div>
                    <div id="commissionHistory" class="mt-2 small" style="display:none;"></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-3">
                <?php $profitAfterCommission = (float) $order['estimated_profit'] - (float) ($order['commission_amount'] ?? 0); ?>
                <div class="kpi-card">
                    <div class="kpi-label">Profit Setelah Komisi</div>
                    <div class="kpi-value <?= $profitAfterCommission >= 0 ? 'profit-positive' : 'profit-negative' ?>">Rp <?= number_format($profitAfterCommission, 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="section-header"><i class="fa-solid fa-table me-1"></i> Detail Item</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th>Product</th><th>Qty</th><th>Purchase</th><th>Packing</th><th>Labour</th><th>Shrinkage</th><th>OPEX</th><th>COF</th>
                        <th>COGS SK</th><th>Transport</th><th>COGS Dest</th><th>Tier</th><th>Harga Jual</th><th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td class="fw-semibold"><?= esc($it['product_name_snapshot']) ?><div class="small text-muted"><?= esc($it['destination_label_snapshot'] ?? '-') ?></div></td>
                            <td><?= number_format((float) $it['qty'], 0, ',', '.') ?> <?= esc($it['unit_snapshot']) ?></td>
                            <td>Rp <?= number_format((float) $it['purchase_price'], 0, ',', '.') ?></td>
                            <td>Rp <?= number_format((float) $it['packing_cost'], 2, ',', '.') ?></td>
                            <td>Rp <?= number_format((float) $it['labour_cost'], 2, ',', '.') ?></td>
                            <td>Rp <?= number_format((float) ($it['cost_of_fund'] ?? 0), 2, ',', '.') ?></td>
                            <td>Rp <?= number_format((float) $it['shrinkage_cost'], 2, ',', '.') ?></td>
                            <td>Rp <?= number_format((float) (($it['opex_cost'] ?? 0) ?: (($it['opex_ho'] ?? 0) + ($it['opex_sukabumi'] ?? 0) + ($it['opex_destination'] ?? 0))), 2, ',', '.') ?>
                                <?php if (isset($it['via_sukabumi_warehouse']) && ! (int) $it['via_sukabumi_warehouse']): ?><div class="small text-muted">Direct petani</div><?php endif ?></td>
                            <td>Rp <?= number_format((float) $it['cogs_sk'], 2, ',', '.') ?></td>
                            <td>Rp <?= number_format((float) $it['transport_cost'], 2, ',', '.') ?></td>
                            <td class="fw-semibold">Rp <?= number_format((float) $it['cogs_destination'], 2, ',', '.') ?></td>
                            <td><span class="badge bg-light text-dark border"><?= esc($it['tier_name_snapshot']) ?></span></td>
                            <td>
                                Rp <?= number_format((float) $it['selling_price'], 0, ',', '.') ?>
                                <?php if ($it['manual_price'] !== null): ?>
                                    <div class="small text-muted">Manual (sistem: Rp <?= number_format((float) $it['final_price'], 0, ',', '.') ?>)</div>
                                <?php endif ?>
                            </td>
                            <td class="fw-semibold">Rp <?= number_format((float) $it['subtotal_final'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>

        <?php if (! empty($order['notes'])): ?>
        <div class="mt-3">
            <label class="form-label small text-muted">Notes</label>
            <p class="mb-0"><?= nl2br(esc($order['notes'])) ?></p>
        </div>
        <?php endif ?>
    </div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-clock-rotate-left me-1"></i> Audit Trail</div>
    <div class="card-body">
        <?php if (empty($auditTrail)): ?>
            <p class="text-muted mb-0">Belum ada aktivitas.</p>
        <?php else: ?>
            <ul class="list-unstyled mb-0">
                <?php foreach ($auditTrail as $log): ?>
                    <li class="mb-2 pb-2 border-bottom">
                        <span class="fw-semibold"><?= esc($log['action']) ?></span>
                        <span class="text-muted small"> - <?= tgl_id($log['created_at'], 'dd MMM yyyy HH.mm') ?></span>
                        <?php if ($log['description']): ?><div class="small text-muted"><?= esc($log['description']) ?></div><?php endif ?>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </div>
</div>


<!-- Modal Bayar Komisi -->
<div class="modal fade" id="modalPayCommission" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bayar Komisi Sales</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nominal Pembayaran (Rp)</label>
                    <input type="number" class="form-control" id="commissionAmount" min="1" step="any">
                    <div class="form-text" id="commissionRemainingText"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tanggal Bayar</label>
                    <input type="date" class="form-control" id="commissionDate" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Catatan (opsional)</label>
                    <input type="text" class="form-control" id="commissionNotes" placeholder="cth: cicilan 1, lunas, via transfer">
                </div>
                <div class="mb-3">
                    <label class="form-label">Bukti Transfer (opsional)</label>
                    <input type="file" class="form-control" id="commissionProof" accept=".jpg,.jpeg,.png,.webp,.pdf">
                    <div class="form-text">JPG/PNG/WebP/PDF, maksimal 5MB.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success" id="btnSubmitCommissionPay">Simpan Pembayaran</button>
            </div>
        </div>
    </div>
</div>
<script>
$(function () {
    const ORDER_ID = <?= (int) $order['id'] ?>;

    // Isi info sisa saat modal dibuka
    $('#modalPayCommission').on('show.bs.modal', function () {
        $.get(BASE_URL + 'admin/sales-order/' + ORDER_ID + '/commission-history', function (res) {
            if (res.success) {
                const remaining = res.total - res.paid;
                $('#commissionRemainingText').text('Sisa hutang: Rp ' + Number(remaining).toLocaleString('id-ID'));
                $('#commissionAmount').attr('max', remaining).val(remaining);
            }
        });
    });

    $('#btnSubmitCommissionPay').on('click', function () {
        const amount = parseFloat($('#commissionAmount').val());
        if (!amount || amount <= 0) { showToast('Nominal harus diisi.', 'error'); return; }
        const $btn = $(this).prop('disabled', true);
        const fd = new FormData();
        fd.append('amount', amount);
        fd.append('payment_date', $('#commissionDate').val());
        fd.append('notes', $('#commissionNotes').val());
        const proofFile = $('#commissionProof')[0].files[0];
        if (proofFile) fd.append('proof', proofFile);
        $.ajax({
            url: BASE_URL + 'admin/sales-order/' + ORDER_ID + '/commission-pay',
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false
        }).done(function (res) {
            if (res.success) {
                showToast(res.message, 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(res.message || 'Gagal.', 'error');
                $btn.prop('disabled', false);
            }
        }).fail(function () {
            showToast('Gagal menghubungi server.', 'error');
            $btn.prop('disabled', false);
        });
    });

    $('#btnCommissionHistory').on('click', function () {
        const $box = $('#commissionHistory');
        if ($box.is(':visible')) { $box.hide(); return; }
        $.get(BASE_URL + 'admin/sales-order/' + ORDER_ID + '/commission-history', function (res) {
            if (!res.success || !res.payments.length) {
                $box.html('<div class="text-muted">Belum ada pembayaran.</div>').show();
                return;
            }
            let html = '<ul class="list-unstyled mb-0">';
            res.payments.forEach(p => {
                html += '<li class="border-bottom py-1">' +
                    '<span class="fw-semibold">Rp ' + Number(p.amount).toLocaleString('id-ID') + '</span>' +
                    ' <span class="text-muted">' + p.payment_date + '</span>' +
                    (p.proof_path ? ' <a href="' + BASE_URL + 'admin/sales-order/commission-proof/' + p.id + '" target="_blank" class="badge bg-info text-decoration-none"><i class="bi bi-paperclip"></i> Bukti</a>' : '') +
                    (p.notes ? '<div class="text-muted">' + $('<div>').text(p.notes).html() + '</div>' : '') +
                    '</li>';
            });
            html += '</ul>';
            $box.html(html).show();
        }).fail(function () {
            $box.html('<div class="text-danger">Gagal memuat riwayat.</div>').show();
        });
    });
});
</script>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const ORDER_ID = <?= $order['id'] ?>;
const BASE = "<?= base_url('admin/sales-order') ?>/" + ORDER_ID;

function doAction(url, confirmText) {
    confirmAction({
        title: 'Konfirmasi',
        text: confirmText,
        onConfirm: () => ajaxPost(url, {}, () => window.location.reload())
    });
}

$('#btnSubmit').on('click', () => doAction(BASE + '/submit', 'Submit PO ini untuk approval? Harga akan terkunci.'));
$('#btnApprove').on('click', () => doAction(BASE + '/approve', 'Setujui PO ini?'));
$('#btnProcess').on('click', () => doAction(BASE + '/process', 'Mulai proses PO ini?'));
$('#btnComplete').on('click', () => doAction(BASE + '/complete', 'Selesaikan PO ini?'));
$('#btnCancel').on('click', () => doAction(BASE + '/cancel', 'Batalkan PO ini? Aksi ini tidak dapat dibatalkan.'));
$('#btnDelete').on('click', function () {
    confirmAction({
        title: 'Hapus PO',
        text: 'Data Draft yang dihapus tidak dapat dikembalikan.',
        onConfirm: () => ajaxPost(BASE + '/delete', {}, () => window.location.href = "<?= base_url('admin/sales-order') ?>")
    });
});
</script>
<?= $this->endSection() ?>
