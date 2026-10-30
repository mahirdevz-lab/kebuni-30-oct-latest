<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<style>
    .receipt { max-width: 380px; font-family: ui-monospace, Consolas, monospace; font-size: 13px; }
    @media print {
        .sidebar, .navbar, .page-header, .no-print { display: none !important; }
        .main-content { margin: 0 !important; }
        .receipt { max-width: 100%; }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$rp      = static fn ($v) => number_format((float) $v, 0, ',', '.');
$qty     = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
$pstatus = ['CONFIRMED' => ['success', 'Terkonfirmasi'], 'PENDING_VERIFICATION' => ['warning', 'Menunggu verifikasi'], 'REJECTED' => ['danger', 'Ditolak'], 'VOIDED' => ['secondary', 'Dibatalkan']];
?>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body receipt mx-auto">
                <div class="text-center fw-bold fs-6"><?= esc($store) ?></div>
                <div class="text-center mb-2"><?= esc($sale['sale_no']) ?></div>
                <div>Waktu : <?= date('d/m/Y H:i', strtotime($sale['sale_date'])) ?></div>
                <div>Kasir : <?= esc($sale['cashier_name'] ?? '-') ?></div>
                <?php if ($sale['customer_name']): ?><div>Pelanggan: <?= esc($sale['customer_name']) ?></div><?php endif ?>
                <hr class="my-2">
                <?php foreach ($items as $i): ?>
                    <div><?= esc($i['product_name']) ?><?= $i['line_type'] === 'PROMO_FREE' ? ' (GRATIS PROMO)' : '' ?></div>
                    <div class="d-flex justify-content-between">
                        <span><?= $qty($i['qty']) ?> <?= esc($i['unit']) ?> x <?= $rp($i['unit_price']) ?><?= (float) $i['list_price'] > (float) $i['unit_price'] && $i['line_type'] === 'NORMAL' ? ' (normal ' . $rp($i['list_price']) . ')' : '' ?></span>
                        <span><?= $rp((float) $i['line_total'] + (float) $i['discount']) ?></span>
                    </div>
                <?php endforeach ?>
                <hr class="my-2">
                <?php if ((float) $sale['discount_total'] > 0): ?><div class="d-flex justify-content-between"><span>Subtotal</span><span><?= $rp($sale['subtotal']) ?></span></div><div class="d-flex justify-content-between"><span>Diskon<?= $sale['discount_type'] === 'PCT' ? ' ' . rtrim(rtrim(number_format((float) $sale['discount_value'], 2, ',', '.'), '0'), ',') . '%' : '' ?></span><span>-<?= $rp($sale['discount_total']) ?></span></div><?php endif ?>
                <div class="d-flex justify-content-between fw-bold"><span>TOTAL</span><span><?= $rp($sale['grand_total']) ?></span></div>
                <?php foreach ($payments as $p): ?>
                    <div class="d-flex justify-content-between"><span><?= (int) $p['is_balance'] ? 'Pelunasan ' . strtolower(\App\Services\PosSaleService::label($p['method'])) . ' ' . date('d/m H:i', strtotime($p['created_at'])) : ($p['method'] === 'CASH' ? 'Tunai diterima' : \App\Services\PosSaleService::label($p['method'])) ?><?= $p['status'] !== 'CONFIRMED' ? ' [' . $pstatus[$p['status']][1] . ']' : '' ?></span><span><?= $rp($p['method'] === 'CASH' && ! (int) $p['is_balance'] ? $p['tendered'] : $p['amount']) ?></span></div>
                <?php endforeach ?>
                <?php if ((float) $sale['change_amount'] > 0): ?><div class="d-flex justify-content-between"><span>Kembalian</span><span><?= $rp($sale['change_amount']) ?></span></div><?php endif ?>
                <?php if ((float) $sale['balance_due'] > 0 && $sale['status'] === 'POSTED'): ?><div class="d-flex justify-content-between text-danger"><span>Sisa tagihan</span><span><?= $rp($sale['balance_due']) ?></span></div><?php endif ?>
                <?php if ($sale['status'] === 'VOIDED'): ?><div class="text-center text-danger fw-bold mt-2">*** DIBATALKAN ***</div><?php endif ?>
                <?php if ($footer): ?><div class="text-center mt-2"><?= esc($footer) ?></div><?php endif ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7 no-print">
        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap gap-2">
                <?php if ($sale['status'] === 'POSTED'): ?>
                    <a href="<?= esc($whatsappUrl) ?>" target="_blank" rel="noopener" class="btn btn-success"><i class="fa-brands fa-whatsapp me-1"></i> Kirim Struk via WhatsApp</a>
                <?php endif ?>
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i> Cetak</button>
                <a href="<?= base_url('admin/pos/sales') ?>" class="btn btn-light">Kembali</a>
            </div>
            <div class="card-footer small text-muted">
                Tombol WhatsApp hanya membuka chat dengan pesan terisi<?= $sale['customer_phone'] ? ' ke ' . esc($sale['customer_phone']) : ' (pilih kontak sendiri — pelanggan tidak punya nomor)' ?>. Kasir tetap menekan kirim; sistem tidak mencatat struk sebagai terkirim.
            </div>
        </div>

        <div class="card mb-3">
            <div class="section-header"><i class="fa-solid fa-money-check me-1"></i> Pembayaran</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Metode</th><th class="text-end">Nilai</th><th>Status</th><th>Referensi</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><?= \App\Services\PosSaleService::label($p['method']) ?><?= $p['corrected_method'] ? ' <span class="badge text-bg-warning" title="Koreksi metode bayar">→ ' . \App\Services\PosSaleService::label($p['corrected_method']) . '</span>' : '' ?></td>
                            <td class="text-end"><?= $rp($p['amount']) ?></td>
                            <td><span class="badge text-bg-<?= $pstatus[$p['status']][0] ?>"><?= $pstatus[$p['status']][1] ?></span></td>
                            <td><?= esc($p['reference_no'] ?? '-') ?></td>
                            <td class="text-end">
                                <?php if ($canVerify && $p['status'] === 'PENDING_VERIFICATION' && $sale['status'] === 'POSTED'): ?>
                                    <button class="btn btn-sm btn-success btn-verify" data-id="<?= $p['id'] ?>" data-decision="approve">Konfirmasi</button>
                                    <button class="btn btn-sm btn-outline-danger btn-verify" data-id="<?= $p['id'] ?>" data-decision="reject">Tolak</button>
                                <?php endif ?>
                                <?php if ($canCorrect && $p['status'] === 'CONFIRMED' && ! $p['corrected_method'] && $sale['status'] === 'POSTED'): ?>
                                    <?php foreach (\App\Services\PosSaleService::METHODS as $m): if ($m === $p['method']) { continue; } ?>
                                        <button class="btn btn-sm btn-link text-nowrap btn-correct" data-id="<?= $p['id'] ?>" data-to="<?= $m ?>">Koreksi ke <?= \App\Services\PosSaleService::label($m) ?></button>
                                    <?php endforeach ?>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($canSell && $sale['status'] === 'POSTED' && (float) $sale['balance_due'] > 0): ?>
            <div class="card mb-3 border-warning" id="pelunasan">
                <div class="section-header"><i class="fa-solid fa-hand-holding-dollar me-1"></i> Pelunasan sisa tagihan</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span>Sisa tagihan</span><strong class="text-danger fs-5">Rp <?= $rp($sale['balance_due']) ?></strong></div>
                    <div class="btn-group w-100 mb-2">
                        <input type="radio" class="btn-check" name="payM" id="pmCash" value="CASH" checked><label class="btn btn-outline-primary" for="pmCash">Tunai</label>
                        <input type="radio" class="btn-check" name="payM" id="pmQris" value="QRIS"><label class="btn btn-outline-primary" for="pmQris">QRIS</label>
                        <input type="radio" class="btn-check" name="payM" id="pmTransfer" value="TRANSFER"><label class="btn btn-outline-primary" for="pmTransfer">Transfer</label>
                    </div>
                    <input type="number" id="payAmount" class="form-control form-control-lg text-end mb-1" min="0" step="500" inputmode="numeric" placeholder="Nominal diterima" data-due="<?= (float) $sale['balance_due'] ?>">
                    <div class="d-flex justify-content-between small mb-2"><span id="payChangeLbl" class="text-muted">Sisa setelah bayar</span><strong id="payAfter">Rp <?= $rp($sale['balance_due']) ?></strong></div>
                    <input type="text" id="payRef" class="form-control mb-2 d-none" maxlength="60" placeholder="No. referensi / nama pengirim (opsional)">
                    <button class="btn btn-warning w-100" id="btnPay"><i class="fa-solid fa-check me-1"></i> Terima Pembayaran</button>
                    <div class="form-text">Tunai masuk Kas Kasir (toko harus buka) dan tercatat di EOD hari ini. QRIS/Transfer menunggu verifikasi.</div>
                </div>
            </div>
        <?php endif ?>

        <?php if ($returns || ($canReturn && $sale['status'] === 'POSTED')): ?>
            <div class="card mb-3">
                <div class="section-header d-flex justify-content-between align-items-center">
                    <span><i class="fa-solid fa-rotate-left me-1"></i> Retur</span>
                    <?php if ($canReturn && $sale['status'] === 'POSTED'): ?><a class="btn btn-sm btn-outline-danger" href="<?= base_url('admin/pos/sales/' . $sale['id'] . '/return') ?>">Retur Barang</a><?php endif ?>
                </div>
                <ul class="list-group list-group-flush">
                    <?php if (! $returns): ?><li class="list-group-item small text-muted">Belum ada retur. Retur bisa dilakukan juga setelah EOD; penjualan asli tidak diubah.</li><?php endif ?>
                    <?php foreach ($returns as $rt): ?>
                        <li class="list-group-item d-flex justify-content-between small">
                            <span><a href="<?= base_url('admin/pos/returns/' . $rt['id']) ?>"><?= esc($rt['return_no']) ?></a> · <?= date('d/m/Y H:i', strtotime($rt['return_date'])) ?></span>
                            <span>nilai <?= $rp($rt['return_value']) ?> · refund <?= $rp($rt['refund_amount']) ?></span>
                        </li>
                    <?php endforeach ?>
                </ul>
            </div>
        <?php endif ?>

        <?php if ($sale['status'] === 'VOIDED'): ?>
            <div class="alert alert-danger">Dibatalkan <?= date('d/m/Y H:i', strtotime($sale['voided_at'])) ?>: <?= esc($sale['void_reason']) ?></div>
        <?php elseif ($canVoid && ! $dayOpen): ?>
            <div class="alert alert-secondary small">Hari kas penjualan ini sudah EOD — pembatalan penuh tidak tersedia; gunakan <strong>Retur Barang</strong>.</div>
        <?php elseif ($canVoid && ! $returns): ?>
            <div class="card border-danger">
                <div class="card-body">
                    <label class="form-label">Batalkan penjualan (supervisor)</label>
                    <div class="input-group">
                        <input type="text" id="voidReason" class="form-control" placeholder="Alasan pembatalan (wajib)" maxlength="255">
                        <button class="btn btn-danger" id="btnVoid"><i class="fa-solid fa-ban me-1"></i> Void</button>
                    </div>
                    <div class="form-text">Data tidak dihapus: stok dikembalikan lewat mutasi reversal dan kas dicatat sebagai refund.</div>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$('.btn-verify').on('click', function () {
    const id = $(this).data('id'), decision = $(this).data('decision');
    confirmAction({
        title: decision === 'approve' ? 'Konfirmasi pembayaran' : 'Tolak pembayaran',
        text: decision === 'approve' ? 'Pastikan dana sudah terlihat di aplikasi QRIS / mutasi rekening bank. Lanjutkan?' : 'Pembayaran ini akan ditandai ditolak.',
        onConfirm: () => ajaxPost(BASE_URL + 'admin/pos/payments/' + id + '/verify', { decision }, () => location.reload())
    });
});
(function () {
    const $amt = $('#payAmount'); if (!$amt.length) return;
    const due = +$amt.data('due'); let uuid = (crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => { const r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16); }));
    let busy = false;
    const method = () => $('input[name=payM]:checked').val();
    const upd = () => {
        const v = parseFloat($amt.val()) || 0;
        $('#payAfter').text(v >= due ? (method() === 'CASH' && v > due ? 'Kembalian ' + fmtRp(v - due) : 'Lunas') : fmtRp(due - v));
        $('#payRef').toggleClass('d-none', method() === 'CASH');
    };
    $amt.on('input', upd); $('input[name=payM]').on('change', upd);
    $('#btnPay').on('click', function () {
        if (busy) return;
        const v = parseFloat($amt.val()) || 0;
        if (v <= 0) { showToast('Isi nominal pembayaran.', 'warning'); return; }
        busy = true; $(this).prop('disabled', true); showLoading();
        $.ajax({ url: BASE_URL + 'admin/pos/sales/<?= (int) $sale['id'] ?>/pay', method: 'POST', contentType: 'application/json', dataType: 'json',
            data: JSON.stringify({ client_uuid: uuid, method: method(), amount: v, reference_no: $('#payRef').val() || null }) })
            .done(res => { showToast(res.message); setTimeout(() => location.href = location.pathname, 900); })
            .fail(x => { busy = false; $('#btnPay').prop('disabled', false); showToast(x.responseJSON?.message || 'Gagal menyimpan pembayaran.', 'error'); })
            .always(hideLoading);
    });
})();
$('.btn-correct').on('click', function () {
    const id = $(this).data('id'), to = $(this).data('to');
    const reason = prompt('Alasan koreksi metode bayar (mis. pelanggan transfer/QRIS tapi kasir pilih tunai):');
    if (reason) ajaxPost(BASE_URL + 'admin/pos/payments/' + id + '/correct', { to_method: to, reason }, () => location.reload());
});
$('#btnVoid').on('click', function () {
    const reason = $('#voidReason').val().trim();
    if (!reason) { showToast('Alasan pembatalan wajib diisi.', 'warning'); return; }
    confirmAction({ title: 'Void penjualan', text: 'Stok dan kas akan dikoreksi dengan reversal. Lanjutkan?',
        onConfirm: () => ajaxPost(BASE_URL + 'admin/pos/sales/<?= (int) $sale['id'] ?>/void', { reason }, () => location.reload()) });
});
</script>
<?= $this->endSection() ?>
