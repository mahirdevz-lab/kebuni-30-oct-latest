<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $rp = static fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.'); ?>

<?php if ($opname && $opname['status'] === 'COUNTING'): ?>
    <div class="alert alert-warning"><i class="fa-solid fa-clipboard-check me-1"></i> Stock opname <a href="<?= base_url('admin/stock/opname/' . $opname['id']) ?>"><?= esc($opname['session_no']) ?></a> sedang dihitung. Toko belum bisa dibuka sampai hasil hitung disubmit.</div>
<?php endif ?>

<div class="row g-3 mb-3">
    <?php if (! $open): ?>
        <div class="col-lg-5">
            <div class="card">
                <div class="section-header"><i class="fa-solid fa-store me-1"></i> Buka Toko</div>
                <div class="card-body">
                    <?php if ($expectedOpening !== null): ?>
                        <div class="alert alert-info py-2 small">Kas awal <strong>seharusnya</strong>: <strong class="fs-6">Rp <?= number_format($expectedOpening, 0, ',', '.') ?></strong><br>(kas fisik EOD terakhir ± setoran/mutasi sesudahnya). Hitung uang di laci lalu isi di bawah — jangan menyalin angka ini.</div>
                    <?php else: ?>
                        <div class="alert alert-secondary py-2 small">Belum pernah ada EOD — tidak ada pembanding kas awal.</div>
                    <?php endif ?>
                    <label class="form-label">Kas awal di laci (hasil hitung) *</label>
                    <input type="number" id="openingCash" class="form-control form-control-lg text-end mb-2" min="0" step="1000" inputmode="numeric">
                    <div class="d-flex justify-content-between small mb-2"><span>Selisih dengan seharusnya</span><strong id="openDiff">-</strong></div>
                    <input type="text" id="openNotes" class="form-control mb-3" placeholder="Catatan (wajib bila ada selisih)" maxlength="255">
                    <button class="btn btn-primary btn-lg w-100" id="btnOpen"><i class="fa-solid fa-door-open me-1"></i> Buka Toko — <?= date('d/m/Y') ?></button>
                    <div class="form-text">Checkout hanya bisa dilakukan setelah toko dibuka. Satu kasir dari buka sampai tutup; tidak ada pergantian shift.</div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="col-lg-7">
            <div class="card">
                <div class="section-header"><i class="fa-solid fa-cash-register me-1"></i> Hari berjalan: <?= date('d/m/Y', strtotime($open['business_date'])) ?> (dibuka <?= date('H:i', strtotime($open['opened_at'])) ?>)</div>
                <div class="card-body">
                    <table class="table table-sm mb-3">
                        <tr><td>Kas awal laci<?php if ($open['expected_opening_cash'] !== null && abs((float) $open['opening_cash'] - (float) $open['expected_opening_cash']) > 0.5): ?> <span class="badge text-bg-warning" title="Seharusnya <?= $rp($open['expected_opening_cash']) ?>">selisih <?= $rp((float) $open['opening_cash'] - (float) $open['expected_opening_cash']) ?></span><?php endif ?></td><td class="text-end"><?= $rp($open['opening_cash']) ?></td></tr>
                        <tr><td>+ Penerimaan tunai penjualan</td><td class="text-end"><?= $rp($open['cash_sales']) ?></td></tr>
                        <tr><td>− Refund tunai (void & retur)</td><td class="text-end"><?= $rp($open['cash_refunds']) ?></td></tr>
                        <tr><td>± Setoran / pindah dana (transfer internal)</td><td class="text-end"><?= $rp($open['cash_transfers']) ?></td></tr>
                        <tr><td>± Mutasi kas lain (pengeluaran sah, dll.)</td><td class="text-end"><?= $rp($open['cash_other']) ?></td></tr>
                        <tr class="fw-bold"><td>= Kas seharusnya di laci</td><td class="text-end" id="expected" data-v="<?= (float) $open['expected_cash'] ?>"><?= $rp($open['expected_cash']) ?></td></tr>
                    </table>
                    <div class="row g-2 small mb-3">
                        <div class="col-6">Penjualan: <strong><?= (int) $open['sales_count'] ?> trx · <?= $rp($open['sales_total']) ?></strong></div>
                        <div class="col-6">QRIS + Transfer terkonfirmasi: <strong><?= $rp($open['qris_confirmed']) ?></strong></div>
                        <?php if ($open['qris_pending_count']): ?>
                            <div class="col-12 text-danger">QRIS/Transfer belum diverifikasi: <strong><?= (int) $open['qris_pending_count'] ?> trx · <?= $rp($open['qris_pending']) ?></strong> — periksa di <a href="<?= base_url('admin/pos/sales?payment_status=PENDING_VERIFICATION&from=' . $open['business_date'] . '&to=' . date('Y-m-d')) ?>">Penjualan POS</a> sebelum EOD.</div>
                        <?php endif ?>
                    </div>
                    <label class="form-label">Kas fisik di laci (hitung manual) *</label>
                    <input type="number" id="physicalCash" class="form-control form-control-lg text-end" min="0" step="500" inputmode="numeric">
                    <div class="d-flex justify-content-between mt-2"><span>Selisih (fisik − seharusnya)</span><strong id="diff">-</strong></div>
                    <input type="text" id="closeNotes" class="form-control mt-2" placeholder="Penjelasan selisih / catatan" maxlength="255">
                    <button class="btn btn-danger btn-lg w-100 mt-3" id="btnClose" data-id="<?= (int) $open['id'] ?>"><i class="fa-solid fa-lock me-1"></i> EOD — Tutup Kas Harian</button>
                    <div class="form-text">Setelah EOD, checkout terkunci. Lanjutkan dengan hitung fisik barang di menu Stock Opname.</div>
                </div>
            </div>
        </div>
    <?php endif ?>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-clock-rotate-left me-1"></i> Riwayat Kas Harian</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Tanggal</th><th>Status</th><th class="text-end">Kas awal</th><th class="text-end">Selisih buka</th><th class="text-end">Tunai masuk</th><th class="text-end">Seharusnya</th><th class="text-end">Fisik</th><th class="text-end">Selisih</th><th class="text-end">QRIS pending</th><th>Dibuka / ditutup</th></tr></thead>
            <tbody>
            <?php if (! $history): ?><tr><td colspan="10" class="text-center text-muted py-3">Belum ada.</td></tr><?php endif ?>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($h['business_date'])) ?></td>
                    <td><?= $h['status'] === 'OPEN' ? '<span class="badge text-bg-success">Buka</span>' : '<span class="badge text-bg-secondary">EOD</span>' ?></td>
                    <td class="text-end"><?= number_format((float) $h['opening_cash'], 0, ',', '.') ?></td>
                    <?php $od = $h['expected_opening_cash'] === null ? null : (float) $h['opening_cash'] - (float) $h['expected_opening_cash']; ?>
                    <td class="text-end <?= $od !== null && abs($od) > 0.5 ? ($od < 0 ? 'text-danger' : 'text-warning') : '' ?>" title="<?= $h['expected_opening_cash'] !== null ? 'Seharusnya ' . number_format((float) $h['expected_opening_cash'], 0, ',', '.') : '' ?>"><?= $od === null ? '-' : number_format($od, 0, ',', '.') ?></td>
                    <td class="text-end"><?= $h['cash_sales'] !== null ? number_format((float) $h['cash_sales'], 0, ',', '.') : '-' ?></td>
                    <td class="text-end"><?= $h['expected_cash'] !== null ? number_format((float) $h['expected_cash'], 0, ',', '.') : '-' ?></td>
                    <td class="text-end"><?= $h['physical_cash'] !== null ? number_format((float) $h['physical_cash'], 0, ',', '.') : '-' ?></td>
                    <td class="text-end <?= (float) $h['difference'] < 0 ? 'text-danger' : ((float) $h['difference'] > 0 ? 'text-warning' : '') ?>"><?= $h['difference'] !== null ? number_format((float) $h['difference'], 0, ',', '.') : '-' ?></td>
                    <td class="text-end"><?= $h['qris_pending'] !== null ? number_format((float) $h['qris_pending'], 0, ',', '.') : '-' ?></td>
                    <td class="small"><?= esc($h['opened_name'] ?? '-') ?> <?= date('H:i', strtotime($h['opened_at'])) ?><?= $h['closed_at'] ? ' / ' . esc($h['closed_name'] ?? '-') . ' ' . date('H:i', strtotime($h['closed_at'])) : '' ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const EXPECTED_OPEN = <?= json_encode($expectedOpening) ?>;
$('#openingCash').on('input', function () {
    if (EXPECTED_OPEN === null || this.value === '') { $('#openDiff').text('-').removeClass('text-danger text-warning'); return; }
    const d = Number(this.value) - EXPECTED_OPEN;
    $('#openDiff').text(fmtRp(d)).toggleClass('text-danger', d < 0).toggleClass('text-warning', d > 0);
});
$('#btnOpen').on('click', function () {
    if ($('#openingCash').val() === '') { showToast('Isi kas awal laci (0 jika kosong).', 'warning'); return; }
    if (EXPECTED_OPEN !== null && Math.abs(Number($('#openingCash').val()) - EXPECTED_OPEN) > 0.5 && !$('#openNotes').val().trim()) { showToast('Kas awal berbeda dari seharusnya — jelaskan di catatan.', 'warning'); return; }
    ajaxPost(BASE_URL + 'admin/pos/day/open', { opening_cash: $('#openingCash').val(), notes: $('#openNotes').val() }, () => location.reload());
});
$('#physicalCash').on('input', function () {
    if (this.value === '') { $('#diff').text('-'); return; }
    const d = Number(this.value) - Number($('#expected').data('v'));
    $('#diff').text(fmtRp(d)).toggleClass('text-danger', d < 0).toggleClass('text-warning', d > 0);
});
$('#btnClose').on('click', function () {
    const id = $(this).data('id');
    if ($('#physicalCash').val() === '') { showToast('Isi kas fisik hasil hitung laci.', 'warning'); return; }
    confirmAction({ title: 'EOD — Tutup Kas Harian', text: 'Checkout akan dikunci sampai hari berikutnya dibuka. Lanjutkan?',
        onConfirm: () => ajaxPost(BASE_URL + 'admin/pos/day/' + id + '/close', { physical_cash: $('#physicalCash').val(), notes: $('#closeNotes').val() }, () => location.reload()) });
});
</script>
<?= $this->endSection() ?>
