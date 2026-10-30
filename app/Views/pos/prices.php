<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-md-6"><input type="text" name="search" value="<?= esc($search) ?>" class="form-control" placeholder="Cari kode, nama, barcode"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Cari</button></div>
        </form>
        <div class="form-text mt-2">Perubahan harga membuat versi harga baru; transaksi lama tetap memakai harga saat transaksi. Produk baru tampil di kasir setelah dicentang "Jual di POS" dan diberi harga.</div>
    </div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-tags me-1"></i> Katalog POS</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Produk</th><th style="width:80px">Jual di POS</th><th style="width:160px">Barcode</th><th style="width:120px">Stok min.</th><th style="width:150px">Harga retail</th><th>Markdown aktif</th><th style="width:170px"></th></tr></thead>
            <tbody>
            <?php foreach ($products as $p): ?>
                <tr data-id="<?= $p['id'] ?>">
                    <td><?= esc($p['product_name']) ?><div class="small text-muted"><?= esc($p['product_code']) ?> · <?= esc($p['unit']) ?></div></td>
                    <td class="text-center"><input type="checkbox" class="form-check-input f-pos" <?= $p['is_pos'] ? 'checked' : '' ?>></td>
                    <td><input type="text" class="form-control form-control-sm f-barcode" value="<?= esc($p['barcode'] ?? '') ?>"></td>
                    <td><input type="number" class="form-control form-control-sm f-min" value="<?= esc($p['min_stock'] ?? '') ?>" min="0" step="0.001"></td>
                    <td><input type="number" class="form-control form-control-sm f-price" value="<?= $p['price'] !== null ? (float) $p['price'] : '' ?>" min="0" step="100"></td>
                    <td>
                        <?php if ($p['promo'] !== null): ?>
                            <span class="badge text-bg-danger">Rp <?= number_format((float) $p['promo'], 0, ',', '.') ?></span>
                            <button class="btn btn-sm btn-link text-danger btn-md-cancel" data-md="<?= (int) $p['markdown_id'] ?>">batalkan</button>
                        <?php else: ?><span class="text-muted">-</span><?php endif ?>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-primary btn-save">Simpan</button>
                        <?php if ($p['price'] !== null): ?><button class="btn btn-sm btn-outline-danger btn-md" data-name="<?= esc($p['product_name']) ?>" data-price="<?= (float) $p['price'] ?>">Markdown</button><?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="mdModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Markdown: <span id="mdName"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" id="mdId">
            <div class="mb-2">Harga normal: <strong id="mdNormal"></strong></div>
            <label class="form-label">Harga promo *</label><input type="number" id="mdPrice" class="form-control mb-2" min="0" step="100">
            <label class="form-label">Alasan *</label><input type="text" id="mdReason" class="form-control mb-2" placeholder="Mis. mendekati batas kualitas, promo mingguan" maxlength="255">
            <div class="row g-2">
                <div class="col"><label class="form-label">Mulai</label><input type="datetime-local" id="mdStart" class="form-control"></div>
                <div class="col"><label class="form-label">Berakhir</label><input type="datetime-local" id="mdEnd" class="form-control"></div>
            </div>
            <div class="form-text">Disetujui atas nama pengguna yang login dan tercatat di audit log.</div>
        </div>
        <div class="modal-footer"><button class="btn btn-danger" id="mdSave">Simpan Markdown</button></div>
    </div></div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const P = BASE_URL + 'admin/pos/prices/';
$('.btn-save').on('click', function () {
    const $r = $(this).closest('tr');
    ajaxPost(P + $r.data('id') + '/save', {
        is_pos: $r.find('.f-pos').is(':checked') ? 1 : '', barcode: $r.find('.f-barcode').val(),
        min_stock: $r.find('.f-min').val(), retail_price: $r.find('.f-price').val()
    }, () => setTimeout(() => location.reload(), 600));
});
const pad = n => String(n).padStart(2, '0');
const local = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
$('.btn-md').on('click', function () {
    const now = new Date(), end = new Date(); end.setHours(23, 59, 0, 0);
    $('#mdId').val($(this).closest('tr').data('id')); $('#mdName').text($(this).data('name')); $('#mdNormal').text(fmtRp($(this).data('price')));
    $('#mdPrice,#mdReason').val(''); $('#mdStart').val(local(now)); $('#mdEnd').val(local(end));
    new bootstrap.Modal('#mdModal').show();
});
$('#mdSave').on('click', () => ajaxPost(P + $('#mdId').val() + '/markdown', {
    promo_price: $('#mdPrice').val(), reason: $('#mdReason').val(), start_at: $('#mdStart').val(), end_at: $('#mdEnd').val()
}, () => location.reload()));
$('.btn-md-cancel').on('click', function () {
    const id = $(this).data('md');
    confirmAction({ title: 'Batalkan markdown', text: 'Harga kembali ke harga normal.', onConfirm: () => ajaxPost(P + 'markdown/' + id + '/cancel', {}, () => location.reload()) });
});
</script>
<?= $this->endSection() ?>
