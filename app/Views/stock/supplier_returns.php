<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$rp = static fn ($v) => number_format((float) $v, 0, ',', '.');
$settleLabel = ['BELUM' => 'Belum diselesaikan', 'NOTA_KREDIT' => 'Nota kredit', 'REFUND' => 'Refund'];
?>
<div class="card mb-3">
    <div class="section-header"><i class="fa-solid fa-truck-arrow-right me-1"></i> Retur Barang ke Supplier</div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-4"><label class="form-label mb-1">Supplier *</label>
                <select id="rsSupplier" class="form-select"><option value="">- pilih -</option><?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= esc($s['nama_supplier']) ?></option><?php endforeach ?></select></div>
            <div class="col-md-4"><label class="form-label mb-1">Dari penerimaan (opsional)</label>
                <select id="rsReceipt" class="form-select"><option value="">- tanpa rujukan -</option>
                    <?php foreach ($receipts as $r): ?><option value="<?= $r['id'] ?>" data-supplier="<?= $r['supplier_id'] ?>"><?= esc($r['receipt_no']) ?> · <?= date('d/m', strtotime($r['receipt_date'])) ?></option><?php endforeach ?></select></div>
            <div class="col-md-4"><label class="form-label mb-1">Tanggal</label><input type="date" id="rsDate" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>"></div>
            <div class="col-md-4"><label class="form-label mb-1">Alasan *</label>
                <select id="rsReason" class="form-select"><?php foreach ($reasons as $k => $v): ?><option value="<?= $k ?>"><?= esc($v) ?></option><?php endforeach ?></select></div>
            <div class="col-md-8"><label class="form-label mb-1">Keterangan (wajib bila Lainnya)</label><input type="text" id="rsNote" class="form-control" maxlength="255"></div>
        </div>
        <table class="table table-sm align-middle">
            <thead><tr><th>Produk</th><th style="width:130px" class="text-end">Stok</th><th style="width:150px">Qty retur</th><th style="width:40px"></th></tr></thead>
            <tbody id="rsBody"></tbody>
        </table>
        <button class="btn btn-sm btn-outline-secondary mb-3" id="rsAdd"><i class="fa-solid fa-plus me-1"></i> Tambah baris</button>
        <div class="row g-2">
            <div class="col-md-3"><label class="form-label mb-1 small">Nilai diakui supplier (Rp)</label><input type="number" id="rsCredit" class="form-control" min="0" step="1000" value="0"></div>
            <div class="col-md-3"><label class="form-label mb-1 small">Penyelesaian</label>
                <select id="rsSettle" class="form-select"><?php foreach ($settleLabel as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach ?></select></div>
            <div class="col-md-3"><label class="form-label mb-1 small">No. nota / referensi</label><input type="text" id="rsRef" class="form-control" maxlength="60"></div>
            <div class="col-md-3 d-flex align-items-end"><button class="btn btn-danger w-100" id="rsSave"><i class="fa-solid fa-paper-plane me-1"></i> Posting Retur</button></div>
        </div>
        <div class="form-text mt-2">Stok berkurang senilai biaya rata-rata saat ini. Nilai kredit/refund hanya dicatat sebagai informasi; pemetaan ke hutang/jurnal menunggu keputusan akuntansi.</div>
    </div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-list me-1"></i> Riwayat retur ke supplier</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>No</th><th>Tanggal</th><th>Supplier</th><th>Penerimaan</th><th>Alasan</th><th class="text-end">Produk</th><th class="text-end">Nilai stok</th><th class="text-end">Diakui supplier</th><th>Oleh</th></tr></thead>
            <tbody>
            <?php if (! $rows): ?><tr><td colspan="9" class="text-center text-muted py-3">Belum ada.</td></tr><?php endif ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a href="<?= base_url('admin/stock/supplier-returns/' . $r['id']) ?>"><?= esc($r['return_no']) ?></a></td>
                    <td><?= date('d/m/Y', strtotime($r['return_date'])) ?></td>
                    <td><?= esc($r['nama_supplier'] ?? '-') ?></td>
                    <td class="small"><?= esc($r['receipt_no'] ?? '-') ?></td>
                    <td class="small"><?= esc($reasons[$r['reason_code']] ?? $r['reason_code']) ?></td>
                    <td class="text-end"><?= (int) $r['n_items'] ?></td>
                    <td class="text-end"><?= $rp($r['cost_value']) ?></td>
                    <td class="text-end"><?= $rp($r['credit_amount']) ?> <span class="small text-muted"><?= $settleLabel[$r['settlement']] ?></span></td>
                    <td class="small"><?= esc($r['user_name'] ?? '-') ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const PRODUCTS = <?= json_encode(array_map(static fn ($p) => ['id' => (int) $p['id'], 'label' => $p['product_name'] . ' (' . $p['product_code'] . ')', 'unit' => $p['unit'], 'stock' => (float) $p['stock']], $products)) ?>;
function rsRow() {
    const opts = PRODUCTS.map(p => `<option value="${p.id}" data-stock="${p.stock}" data-unit="${p.unit}">${$('<i>').text(p.label).html()}</option>`).join('');
    $('#rsBody').append(`<tr><td><select class="form-select form-select-sm rs-prod"><option value="">- pilih -</option>${opts}</select></td>
        <td class="text-end rs-stock text-muted">-</td>
        <td><div class="input-group input-group-sm flex-nowrap"><input type="text" class="form-control text-end rs-qty" inputmode="decimal" placeholder="0"><span class="input-group-text rs-unit">-</span></div></td>
        <td><button class="btn btn-sm btn-link text-danger rs-del"><i class="fa-solid fa-trash"></i></button></td></tr>`);
}
rsRow();
$('#rsAdd').on('click', rsRow);
$('#rsBody').on('click', '.rs-del', function () { $(this).closest('tr').remove(); })
    .on('change', '.rs-prod', function () { const o = $(this).find(':selected'); const $r = $(this).closest('tr'); $r.find('.rs-stock').text(o.val() ? fmtNum(o.data('stock'), 3) : '-'); $r.find('.rs-unit').text(o.data('unit') || '-'); });
$('#rsReceipt').on('change', function () { const s = $(this).find(':selected').data('supplier'); if (s) $('#rsSupplier').val(s); });
$('#rsSupplier').on('change', function () {
    const s = this.value; $('#rsReceipt option[value!=""]').each(function () { $(this).prop('hidden', s && String($(this).data('supplier')) !== s); });
    if ($('#rsReceipt option:selected').prop('hidden')) $('#rsReceipt').val('');
});
let sending = false;
$('#rsSave').on('click', function () {
    if (sending) return;
    const items = $('#rsBody tr').map(function () { return { product_id: +$(this).find('.rs-prod').val(), qty: $(this).find('.rs-qty').val() }; }).get().filter(i => i.product_id);
    if (!$('#rsSupplier').val()) { showToast('Pilih supplier.', 'warning'); return; }
    if (!items.length) { showToast('Isi minimal satu produk.', 'warning'); return; }
    confirmAction({ title: 'Posting retur ke supplier', text: 'Stok akan berkurang dan tidak dapat diubah (koreksi lewat mutasi baru). Lanjutkan?', onConfirm: () => {
        sending = true; showLoading();
        $.ajax({ url: BASE_URL + 'admin/stock/supplier-returns/store', method: 'POST', contentType: 'application/json', dataType: 'json',
            data: JSON.stringify({ supplier_id: +$('#rsSupplier').val(), receipt_id: +$('#rsReceipt').val() || null, return_date: $('#rsDate').val(), reason_code: $('#rsReason').val(), reason: $('#rsNote').val(),
                credit_amount: +$('#rsCredit').val() || 0, settlement: $('#rsSettle').val(), reference_no: $('#rsRef').val(), items }) })
            .done(res => { showToast(res.message); setTimeout(() => location.href = BASE_URL + 'admin/stock/supplier-returns/' + res.id, 700); })
            .fail(x => { sending = false; showToast(x.responseJSON?.message || 'Gagal menyimpan.', 'error'); })
            .always(hideLoading);
    } });
});
</script>
<?= $this->endSection() ?>
