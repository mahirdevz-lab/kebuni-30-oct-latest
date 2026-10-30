<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $rp = static fn ($v) => number_format((float) $v, 0, ',', '.'); ?>
<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="section-header"><i class="fa-solid fa-hand-holding-dollar me-1"></i> Setoran Kas Kasir</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Kas tersedia di laci</span>
                    <strong>Rp <?= $rp($avail['amount']) ?></strong>
                </div>
                <div class="small text-muted mb-3">
                    <?= $avail['timing'] === 'DURING_DAY' ? 'Toko buka — setoran mengurangi kas seharusnya EOD hari ini.' : 'Toko sudah EOD — setoran dicatat setelah penutupan dan otomatis mengurangi kas awal seharusnya besok.' ?>
                </div>
                <?php if ($canDeposit): ?>
                    <label class="form-label mb-1">Nominal setoran *</label>
                    <input type="number" id="dAmount" class="form-control form-control-lg text-end mb-2" min="0" step="1000" inputmode="numeric" max="<?= (float) $avail['amount'] ?>">
                    <label class="form-label mb-1">Rekening tujuan *</label>
                    <select id="dTo" class="form-select mb-2"><?php foreach ($accounts as $a): ?><option value="<?= $a['id'] ?>"><?= esc($a['name']) ?></option><?php endforeach ?></select>
                    <input type="text" id="dRef" class="form-control mb-2" maxlength="60" placeholder="No. bukti setoran (opsional)">
                    <input type="text" id="dNotes" class="form-control mb-2" maxlength="255" placeholder="Catatan (opsional)">
                    <label class="form-label small mb-1">Foto/PDF bukti setoran (opsional)</label>
                    <input type="file" id="dProof" class="form-control mb-3" accept="image/*,application/pdf" capture="environment">
                    <button class="btn btn-primary w-100" id="btnDeposit"><i class="fa-solid fa-paper-plane me-1"></i> Catat Setoran</button>
                    <div class="form-text">Setoran adalah transfer internal — tidak menambah omzet.</div>
                <?php endif ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="section-header d-flex justify-content-between"><span><i class="fa-solid fa-qrcode me-1"></i> Settlement QRIS</span><span class="small">Saldo QRIS Clearing: <strong>Rp <?= $rp($clearingBalance) ?></strong></span></div>
            <div class="card-body">
                <?php if (! $qris['ok'] && ! $qris['blocked']): ?>
                    <div class="text-muted">Tidak ada pembayaran QRIS terkonfirmasi yang menunggu settlement.</div>
                <?php else: ?>
                    <div class="table-responsive" style="max-height:260px; overflow:auto">
                        <table class="table table-sm align-middle mb-2">
                            <thead><tr><?php if ($canSettle): ?><th style="width:30px"><input type="checkbox" id="qAll" class="form-check-input"></th><?php endif ?><th>Penjualan</th><th>Waktu</th><th>Ref</th><th class="text-end">Nilai</th></tr></thead>
                            <tbody>
                            <?php foreach ($qris['ok'] as $q): ?>
                                <tr><?php if ($canSettle): ?><td><input type="checkbox" class="form-check-input q-pay" value="<?= $q['id'] ?>" data-amt="<?= (float) $q['amount'] ?>"></td><?php endif ?>
                                    <td><a href="<?= base_url('admin/pos/sales/' . $q['sale_id']) ?>"><?= esc($q['sale_no']) ?></a></td>
                                    <td><?= date('d/m H:i', strtotime($q['sale_date'])) ?></td><td class="small"><?= esc($q['reference_no'] ?? '-') ?></td><td class="text-end"><?= $rp($q['amount']) ?></td></tr>
                            <?php endforeach ?>
                            <?php foreach ($qris['blocked'] as $q): ?>
                                <tr class="text-muted"><?php if ($canSettle): ?><td></td><?php endif ?><td><?= esc($q['sale_no']) ?></td><td colspan="3" class="small">punya retur — settle manual lewat Keuangan</td></tr>
                            <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($canSettle && $qris['ok']): ?>
                        <div class="row g-2 align-items-end">
                            <div class="col-sm-4"><label class="form-label mb-1 small">Bruto terpilih</label><div class="form-control-plaintext fw-semibold" id="qGross">Rp 0</div></div>
                            <div class="col-sm-4"><label class="form-label mb-1 small">Dana bersih masuk bank *</label><input type="number" id="qNet" class="form-control" min="0" step="100" inputmode="numeric"></div>
                            <div class="col-sm-4"><label class="form-label mb-1 small">MDR (otomatis)</label><div class="form-control-plaintext fw-semibold text-danger" id="qMdr">Rp 0</div></div>
                            <div class="col-sm-4"><label class="form-label mb-1 small">Bank penerima *</label><select id="qBank" class="form-select"><?php foreach ($accounts as $a): ?><option value="<?= $a['id'] ?>"><?= esc($a['name']) ?></option><?php endforeach ?></select></div>
                            <div class="col-sm-4"><label class="form-label mb-1 small">Tanggal settlement</label><input type="date" id="qDate" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                            <div class="col-sm-4"><label class="form-label mb-1 small">No. referensi</label><input type="text" id="qRef" class="form-control" maxlength="60"></div>
                            <div class="col-12"><label class="form-label mb-1 small">Foto/PDF bukti settlement (opsional)</label><input type="file" id="qProof" class="form-control" accept="image/*,application/pdf"></div>
                            <div class="col-12"><button class="btn btn-success w-100" id="btnSettle"><i class="fa-solid fa-check-double me-1"></i> Catat Settlement</button></div>
                        </div>
                        <div class="form-text">MDR = bruto − dana bersih sesuai bukti settlement; dicatat sebagai biaya.</div>
                    <?php endif ?>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card"><div class="section-header">Riwayat setoran</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>No</th><th>Tanggal</th><th>Ke</th><th class="text-end">Nominal</th><th>Waktu</th><th>Oleh</th></tr></thead>
                <tbody>
                <?php if (! $deposits): ?><tr><td colspan="6" class="text-center text-muted py-3">Belum ada.</td></tr><?php endif ?>
                <?php foreach ($deposits as $d): ?><tr><td><?= esc($d['deposit_no']) ?></td><td><?= date('d/m/Y', strtotime($d['business_date'])) ?></td><td><?= esc($d['to_name']) ?></td><td class="text-end"><?= $rp($d['amount']) ?></td><td class="small"><?= $d['timing'] === 'DURING_DAY' ? 'saat buka' : 'setelah EOD' ?><?= $d['proof_path'] ? ' <a href="' . base_url('admin/pos/cash/proof/deposit/' . $d['id']) . '" target="_blank" title="Bukti"><i class="fa-solid fa-paperclip"></i></a>' : '' ?></td><td class="small"><?= esc($d['user_name'] ?? '-') ?></td></tr><?php endforeach ?>
                </tbody></table></div></div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="section-header">Riwayat settlement QRIS</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>No</th><th>Tanggal</th><th class="text-end">Bruto</th><th class="text-end">Bersih</th><th class="text-end">MDR</th><th>Oleh</th></tr></thead>
                <tbody>
                <?php if (! $settlements): ?><tr><td colspan="6" class="text-center text-muted py-3">Belum ada.</td></tr><?php endif ?>
                <?php foreach ($settlements as $s): ?><tr><td><?= esc($s['settlement_no']) ?> <span class="small text-muted">(<?= (int) $s['payment_count'] ?> trx)</span></td><td><?= date('d/m/Y', strtotime($s['settlement_date'])) ?></td><td class="text-end"><?= $rp($s['gross_amount']) ?></td><td class="text-end"><?= $rp($s['net_amount']) ?></td><td class="text-end text-danger"><?= $rp($s['mdr_amount']) ?><?= $s['proof_path'] ? ' <a href="' . base_url('admin/pos/cash/proof/settlement/' . $s['id']) . '" target="_blank" title="Bukti"><i class="fa-solid fa-paperclip"></i></a>' : '' ?></td><td class="small"><?= esc($s['user_name'] ?? '-') ?></td></tr><?php endforeach ?>
                </tbody></table></div></div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const CASH = BASE_URL + 'admin/pos/cash/';
function postForm(url, fd) {
    showLoading();
    $.ajax({ url, method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
        .done(res => { showToast(res.message); setTimeout(() => location.reload(), 800); })
        .fail(x => showToast(x.responseJSON?.message || 'Gagal menyimpan.', 'error'))
        .always(hideLoading);
}
$('#btnDeposit').on('click', function () {
    const amt = $('#dAmount').val();
    if (!amt || +amt <= 0) { showToast('Isi nominal setoran.', 'warning'); return; }
    confirmAction({ title: 'Catat setoran', text: 'Setoran Rp ' + fmtNum(amt) + ' dari Kas Kasir ke ' + $('#dTo option:selected').text() + '. Lanjutkan?',
        onConfirm: () => {
            const fd = new FormData(); fd.append('amount', amt); fd.append('to_account_id', $('#dTo').val()); fd.append('reference_no', $('#dRef').val()); fd.append('notes', $('#dNotes').val());
            if ($('#dProof')[0].files[0]) fd.append('proof', $('#dProof')[0].files[0]);
            postForm(CASH + 'deposit', fd);
        } });
});
const sync = () => {
    let g = 0; $('.q-pay:checked').each(function () { g += +$(this).data('amt'); });
    $('#qGross').text(fmtRp(g)); const net = $('#qNet').val();
    $('#qMdr').text(net === '' ? 'Rp 0' : fmtRp(Math.max(0, g - +net)));
};
$('#qAll').on('change', function () { $('.q-pay').prop('checked', this.checked); sync(); });
$(document).on('change', '.q-pay', sync); $('#qNet').on('input', sync);
$('#btnSettle').on('click', function () {
    const ids = $('.q-pay:checked').map((i, c) => c.value).get();
    if (!ids.length) { showToast('Pilih pembayaran QRIS.', 'warning'); return; }
    if ($('#qNet').val() === '') { showToast('Isi dana bersih yang masuk bank.', 'warning'); return; }
    confirmAction({ title: 'Catat settlement QRIS', text: ids.length + ' transaksi · bruto ' + $('#qGross').text() + ' · MDR ' + $('#qMdr').text() + '. Lanjutkan?',
        onConfirm: () => {
            const fd = new FormData(); ids.forEach(i => fd.append('payment_ids[]', i)); fd.append('net_amount', $('#qNet').val()); fd.append('bank_account_id', $('#qBank').val()); fd.append('settlement_date', $('#qDate').val()); fd.append('reference_no', $('#qRef').val());
            if ($('#qProof')[0].files[0]) fd.append('proof', $('#qProof')[0].files[0]);
            postForm(CASH + 'settle', fd);
        } });
});
</script>
<?= $this->endSection() ?>
