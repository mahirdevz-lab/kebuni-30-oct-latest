<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $q = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ','); ?>
<?php if ((new \App\Services\AuthService())->hasPermission('stock.receive')): ?>
<div class="card mb-3">
    <div class="section-header"><i class="fa-solid fa-truck-ramp-box me-1"></i> Penerimaan Barang Baru</div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-4"><label class="form-label">Supplier *</label>
                <select id="grSupplier" class="form-select"><option value="">- pilih -</option>
                    <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= esc($s['nama_supplier']) ?></option><?php endforeach ?>
                </select></div>
            <div class="col-md-3"><label class="form-label">Tanggal</label><input type="date" id="grDate" class="form-control" value="<?= date('Y-m-d') ?>"></div>
            <div class="col-md-5"><label class="form-label">No. nota / pembelian</label><input type="text" id="grRef" class="form-control" maxlength="60"></div>
        </div>
        <table class="table table-sm align-middle">
            <thead><tr><th>Produk</th><th style="width:140px">Qty reguler</th><th style="width:140px">Qty bonus supplier</th><th style="width:230px">Total biaya baris (Rp) <span class="text-danger">— bukan per unit</span></th><th style="width:40px"></th></tr></thead>
            <tbody id="grItems"></tbody>
        </table>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="grAdd"><i class="fa-solid fa-plus me-1"></i> Tambah baris</button>
        <div class="form-text mt-2">
            Kebijakan biaya bonus saat ini: <strong><?= $bonusPolicy === 'zero' ? 'bonus bernilai Rp0' : 'biaya dibagi ke seluruh unit (reguler + bonus)' ?></strong>
            (setting <code>bonus_cost_policy</code>, menunggu keputusan Keuangan). Contoh: 100 kg Rp1.000.000 + bonus 10 kg → stok 110 kg.
        </div>
        <textarea id="grNotes" class="form-control mt-2" rows="2" placeholder="Catatan"></textarea>
        <button type="button" class="btn btn-primary mt-3" id="grSave"><i class="fa-solid fa-floppy-disk me-1"></i> Posting Penerimaan</button>
    </div>
</div>
<?php endif ?>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-list me-1"></i> Riwayat Penerimaan</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>No</th><th>Tanggal</th><th>Supplier</th><th>Nota</th><th class="text-end">Qty reguler</th><th class="text-end">Qty bonus</th><th class="text-end">Biaya</th><th>Oleh</th></tr></thead>
            <tbody>
            <?php if (! $rows): ?><tr><td colspan="8" class="text-center text-muted py-4">Belum ada penerimaan.</td></tr><?php endif ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a href="<?= base_url('admin/stock/receipts/' . $r['id']) ?>"><?= esc($r['receipt_no']) ?></a></td>
                    <td><?= date('d/m/Y', strtotime($r['receipt_date'])) ?></td>
                    <td><?= esc($r['nama_supplier'] ?? '-') ?></td>
                    <td><?= esc($r['reference_no'] ?? '-') ?></td>
                    <td class="text-end"><?= $q($r['qty_regular']) ?></td>
                    <td class="text-end"><?= $q($r['qty_bonus']) ?></td>
                    <td class="text-end"><?= number_format((float) $r['total_cost'], 0, ',', '.') ?></td>
                    <td><?= esc($r['user_name'] ?? '-') ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const PRODUCTS = <?= json_encode(array_map(static fn ($p) => ['id' => (int) $p['id'], 'unit' => $p['unit'], 'ref' => $refCost[(int) $p['id']] ?? null, 'label' => $p['product_name'] . ' (' . $p['product_code'] . ', ' . $p['unit'] . ')'], $products)) ?>;
const fmt0 = n => Math.round(n).toLocaleString('id-ID');
// Biaya per unit dari total baris; ditandai bila jauh dari harga beli periode terakhir (> 50%).
function rowCheck($tr) {
    const p = PRODUCTS.find(x => x.id === +$tr.find('.g-prod').val()), reg = +$tr.find('.g-reg').val(), cost = +$tr.find('.g-cost').val(), $h = $tr.find('.g-hint');
    $tr.data('flag', null);
    if (!p || reg <= 0) { $h.text(''); return; }
    const unit = cost / reg;
    let t = '= Rp ' + fmt0(unit) + ' / ' + p.unit + (p.ref ? ' (acuan beli Rp ' + fmt0(p.ref) + ')' : '');
    let cls = 'text-muted';
    if (cost <= 0) { t = 'Biaya wajib diisi (total, bukan per ' + p.unit + ')'; cls = 'text-danger'; $tr.data('flag', 'biaya kosong'); }
    else if (p.ref && (unit < p.ref * 0.5 || unit > p.ref * 1.5)) { t += ' — jauh dari acuan, cek lagi'; cls = 'text-warning fw-semibold'; $tr.data('flag', t); }
    $h.attr('class', 'small g-hint ' + cls).text(t);
}
function addRow() {
    const opts = PRODUCTS.map(p => `<option value="${p.id}">${$('<i>').text(p.label).html()}</option>`).join('');
    $('#grItems').append(`<tr>
        <td><select class="form-select form-select-sm g-prod"><option value="">- pilih -</option>${opts}</select></td>
        <td><input type="number" class="form-control form-control-sm g-reg" min="0" step="0.001" value="0"></td>
        <td><input type="number" class="form-control form-control-sm g-bonus" min="0" step="0.001" value="0"></td>
        <td><input type="number" class="form-control form-control-sm g-cost" min="0" step="1" value="0"><div class="small g-hint"></div></td>
        <td><button type="button" class="btn btn-sm btn-link text-danger g-del"><i class="fa-solid fa-trash"></i></button></td></tr>`);
}
$('#grAdd').on('click', addRow);
$('#grItems').on('click', '.g-del', function () { $(this).closest('tr').remove(); });
$('#grItems').on('input change', '.g-prod, .g-reg, .g-cost', function () { rowCheck($(this).closest('tr')); });
if (!PRODUCTS.length) $('#grItems').html('<tr><td colspan="5" class="text-muted">Belum ada produk yang dijual di POS.</td></tr>'); else addRow();

let saving = false;
$('#grSave').on('click', function () {
    if (saving) return;
    const flagged = $('#grItems tr').map(function () { $(this).find('.g-reg').trigger('change'); const f = $(this).data('flag'); return f ? $(this).find('.g-prod option:selected').text() + ' ' + f : null; }).get();
    if (flagged.length) {
        const bad = flagged.some(t => t.includes('biaya kosong'));
        if (bad) { showToast('Total biaya baris wajib diisi untuk barang reguler.', 'error'); return; }
        confirmAction({ title: 'Biaya tampak tidak wajar', text: 'Periksa: ' + flagged.join('; ') + '. Ingat kolom ini TOTAL baris (qty × harga per unit). Tetap posting?', onConfirm: doSave });
        return;
    }
    doSave();
});
function doSave() {
    if (saving) return;
    const items = $('#grItems tr').map(function () {
        return { product_id: +$(this).find('.g-prod').val(), qty_regular: +$(this).find('.g-reg').val(), qty_bonus: +$(this).find('.g-bonus').val(), line_cost: +$(this).find('.g-cost').val() };
    }).get().filter(i => i.product_id);
    saving = true; showLoading();
    $.ajax({ url: BASE_URL + 'admin/stock/receipts/store', method: 'POST', contentType: 'application/json', dataType: 'json',
        data: JSON.stringify({ supplier_id: +$('#grSupplier').val() || null, receipt_date: $('#grDate').val(), reference_no: $('#grRef').val() || null, notes: $('#grNotes').val() || null, items }) })
        .done(res => { showToast(res.message); setTimeout(() => location.href = BASE_URL + 'admin/stock/receipts/' + res.id, 500); })
        .fail(x => { saving = false; showToast(x.responseJSON?.message || 'Gagal menyimpan.', 'error'); })
        .always(hideLoading);
}
</script>
<?= $this->endSection() ?>
