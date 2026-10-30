<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$badge = ['COUNTING' => ['warning', 'Sedang dihitung'], 'SUBMITTED' => ['info', 'Rekonsiliasi'], 'POSTED' => ['success', 'Diposting'], 'CANCELLED' => ['secondary', 'Dibatalkan']];
$canApprove = (new \App\Services\AuthService())->hasPermission('stock.opname_approve');
?>
<div class="card mb-3">
    <div class="card-body">
        <ol class="mb-3 small">
            <li>Toko tutup → lakukan <strong>EOD</strong> (Buka/Tutup Kas).</li>
            <li>Supervisor membuka sesi opname → <strong>cut-off</strong> dicatat & saldo buku di-snapshot; checkout dan penerimaan barang terkunci.</li>
            <li><strong>Blind count:</strong> hitung fisik barang, lalu langsung input dari HP (tersimpan per produk). Saldo sistem tidak ditampilkan.</li>
            <li>Semua produk terisi & tidak ada yang terlewat → submit → <strong>rekonsiliasi</strong> buku vs fisik.</li>
            <li>Selisih besar → <strong>hitung ulang fisik</strong> (blind). Masih selisih → supervisor konfirmasi penyebab.</li>
            <li><strong>Posting</strong>: fisik menjadi book stock; selisih kurang = unknown shrinkage.</li>
        </ol>
        <?php if ($active): ?>
            <a class="btn btn-primary btn-lg w-100" href="<?= base_url('admin/stock/opname/' . $active['id']) ?>">
                <i class="fa-solid fa-mobile-screen me-1"></i> Lanjutkan <?= esc($active['session_no']) ?> (<?= $badge[$active['status']][1] ?>)
            </a>
        <?php elseif ($storeOpen): ?>
            <div class="alert alert-warning mb-0">Toko masih buka. <a href="<?= base_url('admin/pos/day') ?>">Lakukan EOD</a> terlebih dahulu agar cut-off jelas.</div>
        <?php elseif ($canApprove): ?>
            <input type="text" id="soNotes" class="form-control mb-2" placeholder="Catatan sesi (opsional)" maxlength="255">
            <button class="btn btn-primary btn-lg w-100" id="btnOpenSo"><i class="fa-solid fa-clipboard-check me-1"></i> Mulai Stock Opname (cut-off sekarang)</button>
            <?php if ($lastDay): ?><div class="form-text">EOD terakhir: <?= date('d/m/Y', strtotime($lastDay['business_date'])) ?><?= $lastDay['closed_at'] ? ' pukul ' . date('H:i', strtotime($lastDay['closed_at'])) : '' ?>.</div><?php endif ?>
        <?php else: ?>
            <div class="alert alert-info mb-0">Belum ada sesi opname. Supervisor membuka sesi setelah EOD.</div>
        <?php endif ?>
    </div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-list me-1"></i> Riwayat Opname</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>No</th><th>Cut-off</th><th>Status</th><th class="text-end">Progres hitung</th><th class="text-end">Nilai unknown shrinkage</th><th>Dibuka oleh</th></tr></thead>
            <tbody>
            <?php if (! $sessions): ?><tr><td colspan="6" class="text-center text-muted py-3">Belum ada sesi.</td></tr><?php endif ?>
            <?php foreach ($sessions as $s): ?>
                <tr>
                    <td><a href="<?= base_url('admin/stock/opname/' . $s['id']) ?>"><?= esc($s['session_no']) ?></a></td>
                    <td><?= date('d/m/Y H:i', strtotime($s['cutoff_at'])) ?></td>
                    <td><span class="badge text-bg-<?= $badge[$s['status']][0] ?>"><?= $badge[$s['status']][1] ?></span></td>
                    <td class="text-end"><?= (int) $s['counted_count'] ?> / <?= (int) $s['line_count'] ?></td>
                    <td class="text-end"><?= number_format((float) $s['shrink_value'], 0, ',', '.') ?></td>
                    <td><?= esc($s['opened_name'] ?? '-') ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$('#btnOpenSo').on('click', () => confirmAction({
    title: 'Mulai stock opname', text: 'Saldo buku akan di-snapshot sekarang sebagai cut-off, dan checkout terkunci sampai hasil hitung disubmit. Lanjutkan?',
    onConfirm: () => ajaxPost(BASE_URL + 'admin/stock/opname/open', { notes: $('#soNotes').val() }, res => location.href = BASE_URL + 'admin/stock/opname/' + res.id)
}));
</script>
<?= $this->endSection() ?>
