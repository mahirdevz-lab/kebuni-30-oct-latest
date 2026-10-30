<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$q  = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',') ?: '0';
$rp = static fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
?>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2 small">
                    <div class="col-sm-4"><span class="text-muted">Penjualan</span><div class="fw-semibold"><a href="<?= base_url('admin/pos/sales/' . $sale['id']) ?>"><?= esc($sale['sale_no']) ?></a></div></div>
                    <div class="col-sm-4"><span class="text-muted">Tanggal</span><div><?= date('d/m/Y H:i', strtotime($sale['sale_date'])) ?></div></div>
                    <div class="col-sm-4"><span class="text-muted">Hari kas</span><div><?= $sale['day_status'] === 'OPEN' ? '<span class="badge text-bg-success">Masih buka</span> (bisa juga Void)' : '<span class="badge text-bg-secondary">Sudah EOD</span>' ?></div></div>
                    <div class="col-sm-4"><span class="text-muted">Pelanggan</span><div><?= esc($sale['customer_name'] ?? '-') ?></div></div>
                    <div class="col-sm-4"><span class="text-muted">Total / dibayar</span><div><?= $rp($sale['grand_total']) ?> / <?= $rp($sale['paid_total']) ?></div></div>
                    <div class="col-sm-4"><span class="text-muted">Maks. refund uang</span><div class="fw-semibold"><?= $rp($refundable) ?></div></div>
                </div>
                <?php if ($pendingQris): ?><div class="alert alert-warning mt-2 mb-0">Ada pembayaran QRIS/Transfer yang belum diverifikasi — verifikasi/tolak dulu sebelum retur.</div><?php endif ?>
            </div>
        </div>

        <div class="card">
            <div class="section-header"><i class="fa-solid fa-rotate-left me-1"></i> Barang yang diretur</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Produk</th><th class="text-end">Terjual</th><th class="text-end">Sudah retur</th><th class="text-end">Harga dibayar</th><th style="width:140px">Qty retur</th><th style="width:160px">Kondisi</th><th class="text-end">Nilai</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $i): ?>
                        <tr data-id="<?= $i['id'] ?>" data-price="<?= $i['unit_paid'] ?>" data-max="<?= $i['returnable'] ?>">
                            <td><?= esc($i['product_name']) ?><?= $i['line_type'] === 'PROMO_FREE' ? ' <span class="badge text-bg-info">gratis promo</span>' : '' ?></td>
                            <td class="text-end"><?= $q($i['qty']) ?> <?= esc($i['unit']) ?></td>
                            <td class="text-end"><?= $q($i['returned']) ?></td>
                            <td class="text-end"><?= $rp($i['unit_paid']) ?></td>
                            <td>
                                <?php if ($i['returnable'] > 0): ?>
                                    <div class="input-group input-group-sm flex-nowrap">
                                        <input type="text" class="form-control text-end r-qty" inputmode="decimal" placeholder="0" style="min-width:60px">
                                        <button type="button" class="btn btn-outline-secondary r-all" title="Semua sisa"><?= $q($i['returnable']) ?></button>
                                    </div>
                                <?php else: ?><span class="text-muted small">sudah diretur semua</span><?php endif ?>
                            </td>
                            <td>
                                <?php if ($i['returnable'] > 0): ?>
                                    <select class="form-select form-select-sm r-cond">
                                        <option value="RESTOCK">Layak jual (masuk stok)</option>
                                        <option value="DAMAGED">Rusak (jadi Broken Stock)</option>
                                    </select>
                                <?php endif ?>
                            </td>
                            <td class="text-end r-val">-</td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <label class="form-label">Alasan retur *</label>
                <select id="rReason" class="form-select mb-2"><?php foreach ($reasons as $k => $v): ?><option value="<?= $k ?>"><?= esc($v) ?></option><?php endforeach ?></select>
                <input type="text" id="rNote" class="form-control mb-3" placeholder="Keterangan (wajib jika Lainnya)" maxlength="255">

                <label class="form-label">Pengembalian uang</label>
                <div class="btn-group w-100 mb-2">
                    <input type="radio" class="btn-check" name="rMethod" id="rmCash" value="CASH" <?= $dayOpen ? 'checked' : 'disabled' ?>>
                    <label class="btn btn-outline-primary" for="rmCash">Tunai (Kas Kasir)</label>
                    <input type="radio" class="btn-check" name="rMethod" id="rmTrf" value="TRANSFER" <?= $dayOpen ? '' : 'checked' ?>>
                    <label class="btn btn-outline-primary" for="rmTrf">Transfer</label>
                </div>
                <?php if (! $dayOpen): ?><div class="form-text mb-2">Refund tunai butuh toko buka (kas harian OPEN).</div><?php endif ?>
                <select id="rAccount" class="form-select mb-3 <?= $dayOpen ? 'd-none' : '' ?>">
                    <?php foreach ($accounts as $acc): ?><option value="<?= $acc['id'] ?>"><?= esc($acc['name']) ?></option><?php endforeach ?>
                </select>

                <div class="d-flex justify-content-between"><span>Nilai retur</span><strong id="rTotal">Rp 0</strong></div>
                <div class="d-flex justify-content-between"><span>Uang dikembalikan</span><strong id="rRefund">Rp 0</strong></div>
                <div class="d-flex justify-content-between small text-muted"><span>Mengurangi sisa tagihan</span><span id="rDue">Rp 0</span></div>

                <button class="btn btn-danger btn-lg w-100 mt-3" id="rSave" <?= $pendingQris ? 'disabled' : '' ?>><i class="fa-solid fa-rotate-left me-1"></i> Posting Retur</button>
                <div class="form-text">Penjualan asli tidak diubah. Retur tercatat sebagai dokumen baru dengan jejak stok & kas.</div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const REFUNDABLE = <?= json_encode((float) $refundable) ?>, DUE = <?= json_encode((float) $sale['balance_due']) ?>;
    const num = v => { const n = parseFloat(String(v).replace(',', '.')); return isNaN(n) ? 0 : n; };
    function calc() {
        let total = 0;
        $('tbody tr[data-id]').each(function () {
            const $r = $(this), q = num($r.find('.r-qty').val()), max = +$r.data('max');
            $r.find('.r-qty').toggleClass('is-invalid', q > max + 0.0005);
            const v = Math.round(q * $r.data('price') * 100) / 100;
            total += v; $r.find('.r-val').text(q > 0 ? fmtRp(v) : '-');
        });
        const refund = Math.min(total, REFUNDABLE), due = Math.min(total - refund, DUE);
        $('#rTotal').text(fmtRp(total)); $('#rRefund').text(fmtRp(refund)); $('#rDue').text(fmtRp(due));
    }
    $(document).on('input', '.r-qty', calc);
    $('.r-all').on('click', function () { const $r = $(this).closest('tr'); $r.find('.r-qty').val(String($r.data('max')).replace('.', ',')); calc(); });
    $('input[name=rMethod]').on('change', function () { $('#rAccount').toggleClass('d-none', this.value !== 'TRANSFER'); });

    let saving = false;
    $('#rSave').on('click', function () {
        if (saving) return;
        const items = $('tbody tr[data-id]').map(function () {
            const q = num($(this).find('.r-qty').val());
            return q > 0 ? { sale_item_id: +$(this).data('id'), qty: q, condition: $(this).find('.r-cond').val() } : null;
        }).get();
        if (!items.length) { showToast('Isi qty barang yang diretur.', 'warning'); return; }
        const method = $('input[name=rMethod]:checked').val();
        confirmAction({ title: 'Posting retur', text: 'Stok & kas akan dikoreksi. Barang rusak otomatis jadi Broken Stock. Lanjutkan?', onConfirm: () => {
            saving = true; showLoading();
            $.ajax({ url: location.pathname, method: 'POST', contentType: 'application/json', dataType: 'json',
                data: JSON.stringify({ items, reason_code: $('#rReason').val(), reason: $('#rNote').val(), refund_method: method, cash_account_id: method === 'TRANSFER' ? +$('#rAccount').val() : null }) })
                .done(res => { showToast(res.message); setTimeout(() => location.href = BASE_URL + 'admin/pos/returns/' + res.id, 600); })
                .fail(x => { saving = false; showToast(x.responseJSON?.message || 'Gagal menyimpan retur.', 'error'); })
                .always(hideLoading);
        } });
    });
})();
</script>
<?= $this->endSection() ?>
