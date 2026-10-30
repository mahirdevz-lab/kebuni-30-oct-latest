<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$q      = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',') ?: '0';
$rp     = static fn ($v) => number_format((float) $v, 0, ',', '.');
$badge  = ['SUBMITTED' => ['info', 'Rekonsiliasi'], 'POSTED' => ['success', 'Diposting'], 'CANCELLED' => ['secondary', 'Dibatalkan']];
$stateUi = [
    'OK'           => ['', '', ''],
    'NEED_RECOUNT' => ['table-danger', 'danger', 'Wajib hitung ulang'],
    'NEED_CONFIRM' => ['table-warning', 'warning', 'Masih selisih — konfirmasi'],
    'CONFIRMED'    => ['', 'secondary', 'Dikonfirmasi'],
];
$sum = ['shrink' => 0, 'over' => 0, 'diffCount' => 0, 'NEED_RECOUNT' => 0, 'NEED_CONFIRM' => 0, 'CONFIRMED' => 0, 'flagged' => 0];
foreach ($lines as $l) {
    $d = (float) $l['difference'];
    if ($d > 0) { $sum['shrink'] += (float) $l['diff_value']; }
    if ($d < 0) { $sum['over'] += -(float) $l['diff_value']; }
    if ($d != 0) { $sum['diffCount']++; }
    if ($l['state'] !== 'OK') { $sum['flagged']++; $sum[$l['state']]++; }
}
$editable = $session['status'] === 'SUBMITTED' && $canApprove;
$ready    = ! $sum['NEED_RECOUNT'] && ! $sum['NEED_CONFIRM'];
// Urutan tampil: yang butuh tindakan dulu, lalu selisih terbesar.
usort($lines, static function ($a, $b) {
    $rank = ['NEED_RECOUNT' => 0, 'NEED_CONFIRM' => 1, 'CONFIRMED' => 2, 'OK' => 3];
    return [$rank[$a['state']], -abs((float) $a['diff_value'])] <=> [$rank[$b['state']], -abs((float) $b['diff_value'])];
});
?>
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3"><div class="text-muted small">Sesi</div><strong><?= esc($session['session_no']) ?></strong> <span class="badge text-bg-<?= $badge[$session['status']][0] ?>"><?= $badge[$session['status']][1] ?></span>
                <div class="small text-muted">cut-off <?= date('d/m/Y H:i', strtotime($session['cutoff_at'])) ?> · putaran hitung <?= (int) $session['count_round'] ?></div></div>
            <div class="col-md-3"><div class="text-muted small">Unknown shrinkage (fisik kurang)</div><strong class="text-danger">Rp <?= $rp($sum['shrink']) ?></strong></div>
            <div class="col-md-3"><div class="text-muted small">Fisik lebih</div><strong class="text-warning">Rp <?= $rp($sum['over']) ?></strong></div>
            <div class="col-md-3"><div class="text-muted small">Selisih besar (≥ <?= rtrim(rtrim(number_format($thresholds['pct'], 2, ',', '.'), '0'), ',') ?>% atau ≥ Rp<?= $rp($thresholds['value']) ?>)</div>
                <strong><?= $sum['flagged'] ?> produk</strong>
                <div class="small"><?= $sum['NEED_RECOUNT'] ?> wajib hitung ulang · <?= $sum['NEED_CONFIRM'] ?> perlu konfirmasi · <?= $sum['CONFIRMED'] ?> dikonfirmasi</div></div>
        </div>
        <?php if ($session['status'] === 'POSTED'): ?>
            <div class="alert alert-success mt-3 mb-0">Diposting <?= esc($session['approved_name'] ?? '') ?> pada <?= date('d/m/Y H:i', strtotime($session['approved_at'])) ?>. Fisik telah menjadi saldo buku (efektif pada cut-off); selisih kurang tercatat sebagai unknown shrinkage.</div>
        <?php elseif ($session['status'] === 'CANCELLED'): ?>
            <div class="alert alert-secondary mt-3 mb-0"><?= esc($session['notes'] ?? 'Dibatalkan') ?></div>
        <?php elseif (! $ready): ?>
            <div class="alert alert-warning mt-3 mb-0">
                <strong>Rekonsiliasi:</strong> produk dengan selisih besar wajib <strong>dicek ulang fisik</strong> (salah input, salah hitung, terlewat, double count, belum dihitung).
                Pilih produk lalu klik <em>Minta Hitung Ulang</em>. Jika setelah hitung ulang selisih tetap besar, konfirmasi penyebabnya. Posting baru bisa setelah semua beres.
            </div>
        <?php else: ?>
            <div class="alert alert-success mt-3 mb-0">Semua selisih besar sudah dicek ulang/dikonfirmasi. Siap diposting.</div>
        <?php endif ?>
    </div>
</div>

<div class="card mb-3">
    <div class="section-header"><i class="fa-solid fa-scale-unbalanced me-1"></i> Rekonsiliasi buku vs fisik (selisih = buku saat cut-off − fisik)</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr>
                <?php if ($editable): ?><th style="width:32px"><input type="checkbox" class="form-check-input" id="chkAll" title="Pilih semua yang wajib hitung ulang"></th><?php endif ?>
                <th>Produk</th><th class="text-end">Buku</th><th class="text-end">Fisik</th><th class="text-end">Selisih</th><th class="text-end">Nilai</th><th>Riwayat hitung</th><th>Status</th><th>Keterangan</th>
            </tr></thead>
            <tbody>
            <?php foreach ($lines as $l): ?>
                <?php [$rowCls, $bdg, $label] = $stateUi[$l['state']]; $d = (float) $l['difference']; ?>
                <tr class="<?= $rowCls ?>">
                    <?php if ($editable): ?><td><input type="checkbox" class="form-check-input chk-line" value="<?= $l['id'] ?>" <?= $l['state'] === 'NEED_RECOUNT' ? 'data-need="1"' : '' ?>></td><?php endif ?>
                    <td><?= esc($l['product_name']) ?> <span class="small text-muted"><?= esc($l['product_code']) ?></span>
                        <?= (int) $l['added_during_count'] ? '<span class="badge text-bg-info">ditemukan saat hitung</span>' : '' ?></td>
                    <td class="text-end"><?= $q($l['book_qty']) ?> <?= esc($l['unit']) ?></td>
                    <td class="text-end"><?= $l['counted_qty'] !== null ? $q($l['counted_qty']) : '-' ?></td>
                    <td class="text-end fw-semibold <?= $d > 0 ? 'text-danger' : ($d < 0 ? 'text-warning' : '') ?>"><?= $l['difference'] !== null ? $q($l['difference']) : '-' ?></td>
                    <td class="text-end"><?= $l['diff_value'] !== null ? $rp($l['diff_value']) : '-' ?></td>
                    <td class="small">
                        <?php foreach ($l['history'] as $h): ?>
                            <div>#<?= (int) $h['round'] ?>: <strong><?= $q($h['counted_qty']) ?></strong> <span class="text-muted"><?= esc($h['full_name'] ?? '') ?> <?= date('H:i', strtotime($h['counted_at'])) ?></span></div>
                        <?php endforeach ?>
                    </td>
                    <td><?= $label ? '<span class="badge text-bg-' . $bdg . '">' . $label . '</span>' : '<span class="text-muted small">' . ($d == 0 ? 'Sesuai' : 'Selisih kecil') . '</span>' ?></td>
                    <td class="small">
                        <?= $l['note'] ? esc($l['note']) : '' ?>
                        <?php if ($l['state'] === 'CONFIRMED'): ?>
                            <div><strong><?= esc($reasons[$l['resolution_reason']] ?? '') ?></strong>: <?= esc($l['resolution_note']) ?> <span class="text-muted">(<?= esc($l['resolved_name'] ?? '') ?>)</span></div>
                        <?php endif ?>
                        <?php if ($editable && in_array($l['state'], ['NEED_CONFIRM', 'CONFIRMED'], true)): ?>
                            <button class="btn btn-sm btn-outline-warning btn-confirm mt-1" data-id="<?= $l['id'] ?>" data-name="<?= esc($l['product_name']) ?>"><?= $l['state'] === 'CONFIRMED' ? 'Ubah konfirmasi' : 'Konfirmasi selisih' ?></button>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($editable): ?>
    <div class="card border-primary">
        <div class="card-body d-flex flex-wrap gap-2 align-items-center">
            <button class="btn btn-outline-danger" id="btnRecount"><i class="fa-solid fa-rotate-left me-1"></i> Minta Hitung Ulang (<span id="selCount">0</span>)</button>
            <button class="btn btn-success" id="btnApprove" <?= $ready ? '' : 'disabled' ?>><i class="fa-solid fa-check-double me-1"></i> Posting <?= $sum['diffCount'] ?> Adjustment ke Book Stock</button>
            <button class="btn btn-link text-danger ms-auto" id="btnCancel">Batalkan sesi</button>
            <div class="form-text w-100">Hitung ulang hanya bisa setelah EOD dan sebelum ada penjualan/penerimaan untuk produk tsb. Anda juga bisa memilih produk selisih kecil untuk dicek ulang.</div>
        </div>
    </div>

    <div class="modal fade" id="confirmModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Konfirmasi selisih: <span id="cfName"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" id="cfLine">
            <label class="form-label">Penyebab *</label>
            <select id="cfReason" class="form-select mb-2"><?php foreach ($reasons as $k => $v): ?><option value="<?= $k ?>"><?= esc($v) ?></option><?php endforeach ?></select>
            <label class="form-label">Hasil pemeriksaan *</label>
            <textarea id="cfNote" class="form-control" rows="2" maxlength="255" placeholder="Mis. sudah dihitung ulang 2x oleh 2 orang, rak & gudang sudah dicek"></textarea>
        </div>
        <div class="modal-footer"><button class="btn btn-warning" id="cfSave">Simpan konfirmasi</button></div>
    </div></div></div>
<?php elseif ($session['status'] === 'SUBMITTED'): ?>
    <div class="alert alert-info">Menunggu rekonsiliasi oleh supervisor.</div>
<?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const SO = BASE_URL + 'admin/stock/opname/<?= (int) $session['id'] ?>';
const syncSel = () => $('#selCount').text($('.chk-line:checked').length);
$('#chkAll').on('change', function () { $('.chk-line[data-need]').prop('checked', this.checked); syncSel(); });
$(document).on('change', '.chk-line', syncSel);
// Default: semua yang wajib hitung ulang sudah tercentang.
$('.chk-line[data-need]').prop('checked', true); syncSel();

$('#btnRecount').on('click', function () {
    const ids = $('.chk-line:checked').map((i, c) => c.value).get();
    if (!ids.length) { showToast('Pilih produk yang akan dihitung ulang.', 'warning'); return; }
    confirmAction({ title: 'Minta hitung ulang', text: ids.length + ' produk akan dihitung ulang secara blind. Checkout terkunci sampai hasil hitung ulang disubmit.',
        onConfirm: () => ajaxPost(SO + '/recount', { line_ids: ids }, () => location.reload()) });
});
$('.btn-confirm').on('click', function () {
    $('#cfLine').val($(this).data('id')); $('#cfName').text($(this).data('name')); $('#cfNote').val('');
    new bootstrap.Modal('#confirmModal').show();
});
$('#cfSave').on('click', () => ajaxPost(SO + '/confirm', { line_id: $('#cfLine').val(), reason: $('#cfReason').val(), note: $('#cfNote').val() }, () => location.reload()));
$('#btnApprove').on('click', () => confirmAction({ title: 'Posting stock opname', text: 'Hasil hitung fisik akan menjadi saldo buku. Selisih kurang tercatat sebagai unknown shrinkage. Tidak dapat diubah (koreksi hanya lewat mutasi baru). Lanjutkan?', onConfirm: () => ajaxPost(SO + '/approve', {}, () => location.reload()) }));
$('#btnCancel').on('click', () => { const r = prompt('Alasan pembatalan:'); if (r) ajaxPost(SO + '/cancel', { reason: r }, () => location.reload()); });
</script>
<?= $this->endSection() ?>
