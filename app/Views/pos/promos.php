<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$q   = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',') ?: '0';
$now = date('Y-m-d H:i:s');
?>
<div class="card mb-3">
    <div class="section-header"><i class="fa-solid fa-bullhorn me-1"></i> Buat Promo Terjadwal</div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-4"><label class="form-label mb-1">Jenis *</label>
                <div class="btn-group w-100">
                    <input type="radio" class="btn-check" name="pType" id="pBogo" value="BOGO" checked><label class="btn btn-outline-primary" for="pBogo">Beli X gratis Y (BOGO)</label>
                    <input type="radio" class="btn-check" name="pType" id="pTier" value="TIER"><label class="btn btn-outline-primary" for="pTier">Harga bertingkat</label>
                </div></div>
            <div class="col-md-4"><label class="form-label mb-1">Produk *</label>
                <select id="pProduct" class="form-select"><option value="">- pilih -</option><?php foreach ($products as $p): ?><option value="<?= $p['id'] ?>"><?= esc($p['product_name']) ?> (<?= esc($p['product_code']) ?>)</option><?php endforeach ?></select></div>
            <div class="col-md-4"><label class="form-label mb-1">Nama promo *</label><input type="text" id="pName" class="form-control" maxlength="120" placeholder="Mis. Weekend Beli 1 Gratis 1"></div>

            <div class="col-md-3 p-bogo"><label class="form-label mb-1">Beli (qty)</label><input type="number" id="pBuy" class="form-control" min="0" step="any" value="1"></div>
            <div class="col-md-3 p-bogo"><label class="form-label mb-1">Gratis (qty)</label><input type="number" id="pGet" class="form-control" min="0" step="any" value="1"></div>
            <div class="col-md-3 p-bogo"><label class="form-label mb-1">Maks. kelipatan / transaksi</label><input type="number" id="pMax" class="form-control" min="1" placeholder="tanpa batas"></div>
            <div class="col-md-3 p-tier d-none"><label class="form-label mb-1">Minimal qty</label><input type="number" id="pMin" class="form-control" min="0" step="any"></div>
            <div class="col-md-3 p-tier d-none"><label class="form-label mb-1">Harga satuan promo (Rp)</label><input type="number" id="pTierPrice" class="form-control" min="0" step="100"></div>

            <div class="col-md-3"><label class="form-label mb-1">Mulai *</label><input type="datetime-local" id="pStart" class="form-control" value="<?= date('Y-m-d\TH:i') ?>"></div>
            <div class="col-md-3"><label class="form-label mb-1">Berakhir *</label><input type="datetime-local" id="pEnd" class="form-control" value="<?= date('Y-m-d\T23:59', strtotime('+7 days')) ?>"></div>
            <div class="col-md-9"><label class="form-label mb-1">Alasan / catatan</label><input type="text" id="pReason" class="form-control" maxlength="255"></div>
            <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary w-100" id="pSave"><i class="fa-solid fa-floppy-disk me-1"></i> Simpan Promo</button></div>
        </div>
        <div class="form-text mt-2">BOGO: untuk setiap <em>beli</em> yang dibeli, pelanggan mendapat <em>gratis</em> (harga Rp0, stok & HPP tetap tercatat). Harga bertingkat: bila qty ≥ minimal, harga satuan memakai harga promo. Berlaku otomatis di kasir selama periode; transaksi lama tidak berubah.</div>
    </div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-list me-1"></i> Daftar promo</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Promo</th><th>Produk</th><th>Aturan</th><th>Periode</th><th>Status</th><th>Oleh</th><th></th></tr></thead>
            <tbody>
            <?php if (! $promos): ?><tr><td colspan="7" class="text-center text-muted py-3">Belum ada promo.</td></tr><?php endif ?>
            <?php foreach ($promos as $r): ?>
                <?php
                $live = $r['status'] === 'ACTIVE' && $r['start_at'] <= $now && $r['end_at'] >= $now;
                $state = $r['status'] === 'CANCELLED' ? ['secondary', 'Dibatalkan'] : ($live ? ['success', 'Berjalan'] : ($r['end_at'] < $now ? ['light text-dark', 'Selesai'] : ['info', 'Terjadwal']));
                ?>
                <tr>
                    <td><?= esc($r['name']) ?><?= $r['reason'] ? '<div class="small text-muted">' . esc($r['reason']) . '</div>' : '' ?></td>
                    <td><?= esc($r['product_name']) ?></td>
                    <td class="small"><?= $r['promo_type'] === 'BOGO'
                        ? 'Beli ' . $q($r['buy_qty']) . ' gratis ' . $q($r['get_qty']) . ' ' . esc($r['unit']) . ($r['max_sets'] ? ' (maks. ' . (int) $r['max_sets'] . '×)' : '')
                        : 'Min. ' . $q($r['min_qty']) . ' ' . esc($r['unit']) . ' → Rp ' . number_format((float) $r['tier_price'], 0, ',', '.') . '/' . esc($r['unit']) ?></td>
                    <td class="small"><?= date('d/m H:i', strtotime($r['start_at'])) ?> – <?= date('d/m H:i', strtotime($r['end_at'])) ?></td>
                    <td><span class="badge text-bg-<?= $state[0] ?>"><?= $state[1] ?></span></td>
                    <td class="small"><?= esc($r['user_name'] ?? '-') ?></td>
                    <td class="text-end"><?php if ($r['status'] === 'ACTIVE' && $r['end_at'] >= $now): ?><button class="btn btn-sm btn-outline-danger btn-cancel" data-id="<?= $r['id'] ?>">Batalkan</button><?php endif ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const P = BASE_URL + 'admin/pos/promos';
$('input[name=pType]').on('change', function () { $('.p-bogo').toggleClass('d-none', this.value !== 'BOGO'); $('.p-tier').toggleClass('d-none', this.value !== 'TIER'); });
$('#pSave').on('click', function () {
    ajaxPost(P + '/store', {
        promo_type: $('input[name=pType]:checked').val(), product_id: $('#pProduct').val(), name: $('#pName').val(),
        buy_qty: $('#pBuy').val(), get_qty: $('#pGet').val(), max_sets: $('#pMax').val(), min_qty: $('#pMin').val(), tier_price: $('#pTierPrice').val(),
        start_at: $('#pStart').val().replace('T', ' ') + ':00', end_at: $('#pEnd').val().replace('T', ' ') + ':00', reason: $('#pReason').val()
    }, () => setTimeout(() => location.reload(), 800));
});
$('.btn-cancel').on('click', function () {
    const id = $(this).data('id');
    confirmAction({ title: 'Batalkan promo', text: 'Promo berhenti berlaku di kasir. Transaksi lama tidak berubah.', onConfirm: () => ajaxPost(P + '/' + id + '/cancel', {}, () => location.reload()) });
});
</script>
<?= $this->endSection() ?>
