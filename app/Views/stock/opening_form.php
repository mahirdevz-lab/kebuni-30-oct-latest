<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$q        = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',') ?: '0';
$isDraft  = $doc['status'] === 'DRAFT';
$total    = array_sum(array_map(static fn ($l) => (float) $l['qty'] * (float) $l['unit_cost'], $lines));
?>
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-sm-3"><div class="text-muted small">Dokumen</div><strong><?= esc($doc['opening_no']) ?></strong>
                <span class="badge text-bg-<?= ['DRAFT' => 'warning', 'POSTED' => 'success', 'CANCELLED' => 'secondary'][$doc['status']] ?>"><?= $doc['status'] ?></span></div>
            <?php if ($isDraft): ?>
                <div class="col-sm-3"><label class="form-label mb-1 small">Cut-off</label><input type="datetime-local" id="oAt" class="form-control form-control-sm" value="<?= date('Y-m-d\TH:i', strtotime($doc['effective_at'])) ?>" max="<?= date('Y-m-d\TH:i') ?>"></div>
                <div class="col-sm-6"><label class="form-label mb-1 small">Catatan</label><input type="text" id="oNotes" class="form-control form-control-sm" maxlength="255" value="<?= esc($doc['notes'] ?? '') ?>"></div>
            <?php else: ?>
                <div class="col-sm-3"><div class="text-muted small">Cut-off</div><?= date('d/m/Y H:i', strtotime($doc['effective_at'])) ?></div>
                <div class="col-sm-3"><div class="text-muted small">Diposting</div><?= $doc['posted_at'] ? esc($doc['posted_name'] ?? '-') . ' · ' . date('d/m/Y H:i', strtotime($doc['posted_at'])) : '-' ?></div>
                <div class="col-sm-3"><div class="text-muted small">Nilai persediaan awal</div><strong>Rp <?= number_format($total, 0, ',', '.') ?></strong></div>
            <?php endif ?>
        </div>
    </div>
</div>

<?php if ($isDraft): ?>
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <div style="min-width:260px" class="flex-grow-1">
            <label class="form-label mb-1 small">Tambah produk</label>
            <input type="search" id="oFind" class="form-control form-control-sm mb-1" placeholder="Cari nama / kode / barcode">
            <select id="oProduct" class="form-select form-select-sm">
                <option value="">- pilih produk -</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $p['has_stock'] ? 'disabled' : '' ?> data-search="<?= esc(mb_strtolower($p['product_name'] . ' ' . $p['product_code'] . ' ' . ($p['barcode'] ?? ''))) ?>">
                        <?= esc($p['product_name']) ?> (<?= esc($p['product_code']) ?>)<?= $p['has_stock'] ? ' — sudah ada stok' : '' ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>
        <button class="btn btn-sm btn-outline-primary" id="oAdd">Tambah</button>
        <button class="btn btn-sm btn-outline-secondary" id="oAddPos">+ Semua produk POS</button>
        <button class="btn btn-sm btn-outline-secondary" id="oAddAll">+ Semua produk aktif</button>
    </div>
</div>
<?php endif ?>

<div class="card mb-3">
    <div class="section-header d-flex justify-content-between"><span><i class="fa-solid fa-boxes-stacked me-1"></i> Hasil hitung fisik go-live</span><span class="small">Total nilai: <strong id="oTotal">Rp <?= number_format($total, 0, ',', '.') ?></strong></span></div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Produk</th><th style="width:150px">Qty fisik</th><th style="width:170px">Biaya / unit (Rp)</th><th class="text-end">Nilai</th><th>Catatan</th><?= $isDraft ? '<th></th>' : '' ?></tr></thead>
            <tbody id="oBody">
            <?php if (! $isDraft): ?>
                <?php foreach ($lines as $l): ?>
                    <tr>
                        <td><?= esc($l['product_name']) ?> <span class="small text-muted"><?= esc($l['product_code']) ?></span></td>
                        <td><?= $q($l['qty']) ?> <?= esc($l['unit']) ?></td>
                        <td><?= number_format((float) $l['unit_cost'], 2, ',', '.') ?><div class="small text-muted"><?= esc($l['cost_source'] ?? '') ?></div></td>
                        <td class="text-end"><?= number_format((float) $l['qty'] * (float) $l['unit_cost'], 0, ',', '.') ?></td>
                        <td class="small"><?= esc($l['note'] ?? '') ?><?= $l['ledger_id'] ? ' <a href="' . base_url('admin/stock/book?product_id=' . $l['product_id'] . '&from=' . date('Y-m-d', strtotime($doc['effective_at']))) . '">kartu stok</a>' : '' ?></td>
                    </tr>
                <?php endforeach ?>
            <?php endif ?>
            </tbody>
        </table>
    </div>
    <?php if ($isDraft): ?><div class="card-footer small text-muted">Qty kosong/0 tidak diposting. Biaya per unit wajib > 0 untuk produk yang diposting.</div><?php endif ?>
</div>

<?php if ($isDraft): ?>
<div class="card border-primary">
    <div class="card-body d-flex flex-wrap gap-2">
        <button class="btn btn-primary" id="oSave"><i class="fa-solid fa-floppy-disk me-1"></i> Simpan Draft</button>
        <?php if ($canPost): ?><button class="btn btn-success" id="oPost"><i class="fa-solid fa-check-double me-1"></i> Simpan & Posting Stok Awal</button><?php endif ?>
        <button class="btn btn-link text-danger ms-auto" id="oCancel">Batalkan draft</button>
        <?php if (! $canPost): ?><div class="form-text w-100">Posting dilakukan oleh Admin setelah draft diperiksa.</div><?php endif ?>
    </div>
</div>
<?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if ($isDraft): ?>
<script>
(function () {
    const URL = BASE_URL + 'admin/stock/opening/<?= (int) $doc['id'] ?>';
    const PRODUCTS = <?= json_encode(array_column(array_map(static fn ($p) => [
        'id' => (int) $p['id'], 'name' => $p['product_name'], 'code' => $p['product_code'], 'unit' => $p['unit'], 'is_pos' => (int) $p['is_pos'],
        'cost' => $p['default_cost'], 'source' => $p['cost_source'], 'has_stock' => $p['has_stock'],
    ], $products), null, 'id')) ?>;
    const INITIAL = <?= json_encode(array_map(static fn ($l) => [
        'product_id' => (int) $l['product_id'], 'qty' => (float) $l['qty'], 'unit_cost' => (float) $l['unit_cost'], 'cost_source' => $l['cost_source'], 'note' => $l['note'],
    ], $lines)) ?>;
    const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    const qnum = v => { const n = parseFloat(String(v).replace(',', '.')); return isNaN(n) ? 0 : n; };

    function addRow(l) {
        const p = PRODUCTS[l.product_id];
        if (!p || $(`#oBody tr[data-pid="${l.product_id}"]`).length) return;
        $('#oBody').append(`<tr data-pid="${p.id}" data-source="${esc(l.cost_source ?? p.source ?? '')}" data-default="${p.cost || 0}">
            <td>${esc(p.name)} <span class="small text-muted">${esc(p.code)}</span>${p.has_stock ? ' <span class="badge text-bg-danger">sudah ada stok</span>' : ''}</td>
            <td><div class="input-group input-group-sm flex-nowrap"><input type="text" class="form-control text-end o-qty" inputmode="decimal" value="${l.qty ? String(l.qty).replace('.', ',') : ''}" placeholder="0"><span class="input-group-text">${esc(p.unit)}</span></div></td>
            <td><input type="text" class="form-control form-control-sm text-end o-cost" inputmode="decimal" value="${l.unit_cost ? String(l.unit_cost).replace('.', ',') : ''}" placeholder="0">
                <div class="small text-muted">${esc(l.cost_source ?? p.source ?? 'isi manual dari nota / hitungan (per unit)')}</div></td>
            <td class="text-end o-val">-</td>
            <td><input type="text" class="form-control form-control-sm o-note" maxlength="255" value="${esc(l.note ?? '')}"></td>
            <td><button type="button" class="btn btn-sm btn-link text-danger o-del"><i class="fa-solid fa-trash"></i></button></td></tr>`);
    }
    function total() {
        let t = 0;
        $('#oBody tr').each(function () { const v = qnum($(this).find('.o-qty').val()) * qnum($(this).find('.o-cost').val()); t += v; $(this).find('.o-val').text(v ? fmtNum(v) : '-'); });
        $('#oTotal').text(fmtRp(t));
    }
    // Biaya per unit yang jauh (> 50%) dari harga beli acuan ditandai; posting meminta konfirmasi.
    function deviants() {
        const out = [];
        $('#oBody tr').each(function () {
            const ref = +$(this).data('default'), cost = qnum($(this).find('.o-cost').val()), qty = qnum($(this).find('.o-qty').val());
            const bad = qty > 0 && ref > 0 && cost > 0 && (cost < ref * 0.5 || cost > ref * 1.5);
            $(this).find('.o-cost').toggleClass('border-warning', bad);
            if (bad) out.push($(this).find('td:first').text().trim() + ' (Rp ' + Math.round(cost).toLocaleString('id-ID') + ' vs acuan Rp ' + Math.round(ref).toLocaleString('id-ID') + ')');
        });
        return out;
    }
    const fromProduct = p => ({ product_id: p.id, qty: 0, unit_cost: p.cost, cost_source: p.source });

    INITIAL.forEach(addRow); total();
    $('#oBody').on('input', 'input', () => { total(); deviants(); }).on('click', '.o-del', function () { $(this).closest('tr').remove(); total(); });
    $('#oFind').on('input', function () {
        // Utamakan nama yang diawali kata kunci ("kol" -> Kol, bukan Brokoli).
        const q = this.value.trim().toLowerCase(); let first = null, prefix = null;
        $('#oProduct option[value!=""]').each(function () {
            const s = String($(this).data('search')), ok = !q || s.includes(q);
            $(this).prop('hidden', !ok);
            if (ok && !this.disabled) { first = first || this.value; if (!prefix && s.startsWith(q)) prefix = this.value; }
        });
        if (q && (prefix || first)) $('#oProduct').val(prefix || first);
    });
    $('#oAdd').on('click', () => { const id = +$('#oProduct').val(); if (id) { addRow(fromProduct(PRODUCTS[id])); total(); } });
    $('#oAddPos').on('click', () => addMany(p => p.is_pos && !p.has_stock, 'produk POS'));
    $('#oAddAll').on('click', () => addMany(p => !p.has_stock, 'produk aktif'));
    function addMany(filter, label) {
        const before = $('#oBody tr').length;
        Object.values(PRODUCTS).filter(filter).forEach(p => addRow(fromProduct(p)));
        total();
        const added = $('#oBody tr').length - before;
        if (added > 0) { showToast(added + ' ' + label + ' ditambahkan. Isi qty & biaya per unit.'); return; }
        const withStock = Object.values(PRODUCTS).filter(p => (label === 'produk POS' ? p.is_pos : true) && p.has_stock).length;
        showToast('Tidak ada ' + label + ' yang bisa ditambahkan: semua sudah ada di daftar' + (withStock ? ', dan ' + withStock + ' lainnya sudah punya mutasi stok (tidak bisa diberi stok awal)' : '') + '.', 'warning');
    }

    const payload = () => ({
        effective_at: $('#oAt').val(), notes: $('#oNotes').val(),
        lines: $('#oBody tr').map(function () {
            const cost = $(this).find('.o-cost').val() || '0';
            // Sumber harga hanya dipertahankan bila biaya tidak diubah dari default.
            const source = qnum(cost) === +$(this).data('default') && $(this).data('source') ? $(this).data('source') : 'Diisi manual';
            return { product_id: +$(this).data('pid'), qty: $(this).find('.o-qty').val() || '0', unit_cost: cost, cost_source: source, note: $(this).find('.o-note').val() };
        }).get(),
    });
    const save = then => $.ajax({ url: URL + '/save', method: 'POST', contentType: 'application/json', dataType: 'json', data: JSON.stringify(payload()) })
        .done(res => then ? then(res) : showToast(res.message)).fail(x => showToast(x.responseJSON?.message || 'Gagal menyimpan.', 'error'));
    $('#oSave').on('click', () => save());
    $('#oPost').on('click', () => confirmAction({ title: 'Posting stok awal', text: 'Qty & biaya akan masuk ke buku stok sebagai Stok awal dan tidak dapat diubah (koreksi berikutnya lewat stock opname). Biaya adalah PER UNIT (bukan total).' + (deviants().length ? ' PERHATIAN, biaya jauh dari acuan: ' + deviants().join('; ') + '.' : '') + ' Lanjutkan?',
        onConfirm: () => save(() => ajaxPost(URL + '/post', {}, () => location.reload())) }));
    $('#oCancel').on('click', () => confirmAction({ title: 'Batalkan draft', text: 'Draft stok awal ini dibatalkan.', onConfirm: () => ajaxPost(URL + '/cancel', {}, () => location.href = BASE_URL + 'admin/stock/opening') }));
})();
</script>
<?php endif ?>
<?= $this->endSection() ?>
