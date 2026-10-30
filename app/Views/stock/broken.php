<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<style>
    .bs-card { border: 1px solid var(--bs-border-color); border-radius: .6rem; padding: .75rem; margin-bottom: .6rem; background: var(--bs-body-bg); }
    .bs-thumb { width: 64px; height: 64px; object-fit: cover; border-radius: .4rem; cursor: zoom-in; }
    .reason-chips .btn { margin: 0 .25rem .35rem 0; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$q      = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
$rp     = static fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
$badge  = ['DRAFT' => ['warning', 'Menunggu persetujuan'], 'POSTED' => ['success', 'Disetujui'], 'REJECTED' => ['danger', 'Ditolak'], 'CANCELLED' => ['secondary', 'Dibatalkan']];
$canReport = (new \App\Services\AuthService())->hasPermission('stock.broken');
?>
<div class="row g-3">
    <?php if ($canReport): ?>
    <div class="col-lg-5">
        <div class="card">
            <div class="section-header"><i class="fa-solid fa-heart-crack me-1"></i> Lapor Barang Rusak / Busuk</div>
            <div class="card-body">
                <div class="alert alert-info py-2 small">Catat <strong>sebelum barang dibuang</strong>. Stok baru berkurang setelah supervisor menyetujui.</div>
                <form id="bsForm" enctype="multipart/form-data">
                    <label class="form-label mb-1">Produk *</label>
                    <input type="search" id="bsFind" class="form-control mb-1" placeholder="Cari nama / kode / scan barcode" autocomplete="off">
                    <select name="product_id" id="bsProduct" class="form-select form-select-lg mb-3" required>
                        <option value="">- pilih produk -</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>" data-unit="<?= esc($p['unit']) ?>" data-search="<?= esc(mb_strtolower($p['product_name'] . ' ' . $p['product_code'] . ' ' . ($p['barcode'] ?? ''))) ?>"><?= esc($p['product_name']) ?> (<?= esc($p['product_code']) ?>)</option>
                        <?php endforeach ?>
                    </select>

                    <label class="form-label mb-1">Jumlah *</label>
                    <div class="input-group input-group-lg mb-3">
                        <input type="text" name="qty" id="bsQty" class="form-control text-end" inputmode="decimal" placeholder="0" required>
                        <span class="input-group-text" id="bsUnit">-</span>
                    </div>

                    <label class="form-label mb-1">Alasan *</label>
                    <div class="reason-chips mb-2">
                        <?php foreach ($reasons as $code => $label): ?>
                            <input type="radio" class="btn-check" name="reason_code" id="rc<?= $code ?>" value="<?= $code ?>" <?= $code === 'BUSUK' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-secondary btn-sm" for="rc<?= $code ?>"><?= esc($label) ?></label>
                        <?php endforeach ?>
                    </div>
                    <input type="text" name="reason" class="form-control mb-3" placeholder="Keterangan (wajib jika Lainnya)" maxlength="255">

                    <label class="form-label mb-1">Foto (opsional)</label>
                    <input type="file" name="photo" id="bsPhoto" class="form-control mb-1" accept="image/*" capture="environment">
                    <img id="bsPreview" class="bs-thumb d-none mb-2" alt="Pratinjau foto">
                    <div class="form-text mb-3">JPG/PNG/WEBP, maks 5 MB.</div>

                    <button type="submit" class="btn btn-danger btn-lg w-100" id="bsSave"><i class="fa-solid fa-paper-plane me-1"></i> Kirim Laporan</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif ?>

    <div class="<?= $canReport ? 'col-lg-7' : 'col-12' ?>">
        <div class="card">
            <div class="section-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fa-solid fa-list me-1"></i> Laporan Broken Stock</span>
                <div class="btn-group btn-group-sm">
                    <?php foreach (['DRAFT' => 'Menunggu (' . $draftCount . ')', 'POSTED' => 'Disetujui', 'REJECTED' => 'Ditolak', 'ALL' => 'Semua'] as $k => $v): ?>
                        <a href="?status=<?= $k ?>" class="btn <?= $status === $k ? 'btn-primary' : 'btn-outline-primary' ?>"><?= $v ?></a>
                    <?php endforeach ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (! $rows): ?><div class="text-center text-muted py-4">Tidak ada laporan.</div><?php endif ?>
                <?php foreach ($rows as $r): ?>
                    <div class="bs-card" data-id="<?= $r['id'] ?>">
                        <div class="d-flex gap-3">
                            <?php if ($r['photo_path']): ?>
                                <img src="<?= base_url('admin/stock/broken/' . $r['id'] . '/photo') ?>" class="bs-thumb bs-zoom" alt="Foto <?= esc($r['report_no']) ?>" loading="lazy">
                            <?php endif ?>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between flex-wrap gap-1">
                                    <strong><?= esc($r['product_name']) ?> — <?= $q($r['qty']) ?> <?= esc($r['unit']) ?></strong>
                                    <span class="badge text-bg-<?= $badge[$r['status']][0] ?>"><?= $badge[$r['status']][1] ?></span>
                                </div>
                                <div class="small"><?= esc($reasons[$r['reason_code']] ?? $r['reason_code']) ?><?= $r['reason'] ? ': ' . esc($r['reason']) : '' ?></div>
                                <div class="small text-muted">
                                    <?= esc($r['report_no']) ?> · <?= date('d/m/Y H:i', strtotime($r['reported_at'])) ?> · pelapor <?= esc($r['reporter'] ?? '-') ?>
                                    <?php if ($r['approver']): ?> · <?= $r['status'] === 'REJECTED' ? 'ditolak' : 'disetujui' ?> <?= esc($r['approver']) ?><?php endif ?>
                                </div>
                                <div class="small">
                                    <?php if ($r['status'] === 'POSTED'): ?>Nilai biaya: <strong><?= $rp($r['posted_value']) ?></strong>
                                    <?php else: ?>Estimasi nilai: <?= $rp($r['est_value']) ?><?php endif ?>
                                    <?php if ($r['status'] === 'REJECTED' && $r['reject_reason']): ?><span class="text-danger"> · <?= esc($r['reject_reason']) ?></span><?php endif ?>
                                </div>
                            </div>
                        </div>
                        <?php if ($r['status'] === 'DRAFT'): ?>
                            <div class="d-flex gap-2 mt-2 justify-content-end">
                                <?php if ((int) $r['reported_by'] === $userId): ?><button class="btn btn-sm btn-link text-muted bs-cancel">Batalkan</button><?php endif ?>
                                <?php if ($canApprove): ?>
                                    <button class="btn btn-sm btn-outline-danger bs-reject">Tolak</button>
                                    <button class="btn btn-sm btn-success bs-approve"><i class="fa-solid fa-check me-1"></i> Setujui</button>
                                <?php endif ?>
                            </div>
                        <?php endif ?>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="photoModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
    <div class="modal-body p-0"><img id="photoBig" class="w-100" alt="Foto bukti"></div>
</div></div></div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const BS = BASE_URL + 'admin/stock/broken/';
$('#bsFind').on('input', function () {
    const q = this.value.trim().toLowerCase();
    let first = null;
    $('#bsProduct option[value!=""]').each(function () {
        const ok = !q || $(this).data('search').includes(q);
        $(this).prop('hidden', !ok);
        if (ok && !first) first = this.value;
    });
    if (q && first) $('#bsProduct').val(first).trigger('change');
});
$('#bsProduct').on('change', function () { $('#bsUnit').text($(this).find(':selected').data('unit') || '-'); });
$('#bsPhoto').on('change', function () {
    const f = this.files[0];
    f ? $('#bsPreview').attr('src', URL.createObjectURL(f)).removeClass('d-none') : $('#bsPreview').addClass('d-none');
});

let sending = false;
$('#bsForm').on('submit', function (e) {
    e.preventDefault();
    if (sending) return;
    const fd = new FormData(this);
    fd.set('qty', String(fd.get('qty') || '').replace(',', '.'));
    sending = true; $('#bsSave').prop('disabled', true); showLoading();
    $.ajax({ url: BS + 'store', method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
        .done(res => { showToast(res.message, res.message.includes('Perhatian') ? 'warning' : 'success'); setTimeout(() => location.href = BS + '?status=DRAFT', 900); })
        .fail(x => { sending = false; $('#bsSave').prop('disabled', false); showToast(x.responseJSON?.message || 'Gagal menyimpan laporan.', 'error'); })
        .always(hideLoading);
});

$('.bs-approve').on('click', function () {
    const id = $(this).closest('.bs-card').data('id');
    confirmAction({ title: 'Setujui Broken Stock', text: 'Stok akan berkurang dan tercatat sebagai known shrinkage. Pastikan barang sudah diperiksa.', onConfirm: () => ajaxPost(BS + id + '/approve', {}, () => location.reload()) });
});
$('.bs-reject').on('click', function () {
    const id = $(this).closest('.bs-card').data('id'), reason = prompt('Alasan menolak laporan:');
    if (reason) ajaxPost(BS + id + '/reject', { reason }, () => location.reload());
});
$('.bs-cancel').on('click', function () {
    const id = $(this).closest('.bs-card').data('id');
    confirmAction({ title: 'Batalkan draft', text: 'Laporan ini dibatalkan (tetap tersimpan di riwayat).', onConfirm: () => ajaxPost(BS + id + '/cancel', {}, () => location.reload()) });
});
$('.bs-zoom').on('click', function () { $('#photoBig').attr('src', this.src); new bootstrap.Modal('#photoModal').show(); });
</script>
<?= $this->endSection() ?>
