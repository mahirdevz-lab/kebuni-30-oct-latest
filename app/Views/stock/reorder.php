<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$q = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',') ?: '0';
$ui = ['HABIS' => ['danger', 'Habis'], 'KRITIS' => ['danger', 'Di bawah minimum'], 'MENIPIS' => ['warning', 'Menipis'], 'AMAN' => ['success', 'Aman']];
$totalValue = array_sum(array_column($rows, 'est_value'));
$nSug = count(array_filter($rows, static fn ($r) => $r['suggested'] > 0));
?>
<div class="row g-3 mb-3">
    <?php foreach (['HABIS', 'KRITIS', 'MENIPIS', 'AMAN'] as $k): ?>
        <div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="text-muted small"><?= $ui[$k][1] ?></div><div class="fs-3 fw-bold text-<?= $ui[$k][0] ?>"><?= (int) ($counts[$k] ?? 0) ?></div></div></div></div>
    <?php endforeach ?>
</div>

<div class="card mb-3">
    <div class="section-header d-flex justify-content-between flex-wrap gap-2">
        <span><i class="fa-solid fa-cart-arrow-down me-1"></i> Saran pemesanan ulang</span>
        <span class="small">Stok cukup untuk <strong><?= (int) $cfg['cover'] ?></strong> hari · rata-rata jual dari <strong><?= (int) $cfg['lookback'] ?></strong> hari terakhir · <a href="<?= base_url('admin/setting') ?>">ubah</a></span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Produk</th><th>Status</th><th class="text-end">Stok</th><th class="text-end">Minimum</th><th class="text-end">Jual/hari</th><th class="text-end">Cukup (hari)</th><th class="text-end">Saran pesan</th><th class="text-end">Est. nilai</th><th>Supplier terakhir</th></tr></thead>
            <tbody>
            <?php if (! $rows): ?><tr><td colspan="9" class="text-center text-muted py-3">Belum ada produk POS. Atur di Katalog & Harga.</td></tr><?php endif ?>
            <?php foreach ($rows as $r): ?>
                <tr class="<?= $r['suggested'] > 0 && in_array($r['status'], ['HABIS', 'KRITIS'], true) ? 'table-danger' : '' ?>">
                    <td><?= esc($r['name']) ?> <span class="small text-muted"><?= esc($r['code']) ?></span></td>
                    <td><span class="badge text-bg-<?= $ui[$r['status']][0] ?>"><?= $ui[$r['status']][1] ?></span></td>
                    <td class="text-end"><?= $q($r['qty']) ?> <?= esc($r['unit']) ?></td>
                    <td class="text-end"><?= $r['min_stock'] !== null ? $q($r['min_stock']) : '<span class="text-muted">-</span>' ?></td>
                    <td class="text-end"><?= $r['avg_per_day'] > 0 ? $q($r['avg_per_day']) : '<span class="text-muted">0</span>' ?></td>
                    <td class="text-end"><?= $r['days_cover'] !== null ? $q($r['days_cover']) : '<span class="text-muted">-</span>' ?></td>
                    <td class="text-end fw-bold"><?= $r['suggested'] > 0 ? $q($r['suggested']) . ' ' . esc($r['unit']) : '<span class="text-muted fw-normal">-</span>' ?></td>
                    <td class="text-end"><?= $r['est_value'] > 0 ? number_format($r['est_value'], 0, ',', '.') : '-' ?></td>
                    <td class="small"><?= esc($r['supplier'] ?? '-') ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
            <?php if ($nSug): ?><tfoot><tr class="fw-bold"><td colspan="6"><?= $nSug ?> produk disarankan dipesan</td><td></td><td class="text-end"><?= number_format($totalValue, 0, ',', '.') ?></td><td></td></tr></tfoot><?php endif ?>
        </table>
    </div>
    <div class="card-footer small text-muted">Saran = hari cukup × rata-rata jual − stok sekarang (dibulatkan ke atas per 0,5), minimal sampai stok minimum. Rata-rata dihitung dari penjualan bersih (termasuk gratis promo, dikurangi retur). Ini <strong>saran</strong>; sesuaikan dengan musim, promo, dan kapasitas penyimpanan.</div>
</div>

<?php if ($orderText !== ''): ?>
<div class="card">
    <div class="section-header"><i class="fa-brands fa-whatsapp me-1"></i> Teks pesanan per supplier</div>
    <div class="card-body">
        <textarea id="orderText" class="form-control mb-2" rows="<?= min(14, substr_count($orderText, "\n") + 2) ?>" readonly><?= esc($orderText) ?></textarea>
        <button class="btn btn-sm btn-outline-secondary" id="btnCopy"><i class="fa-regular fa-copy me-1"></i> Salin</button>
        <a class="btn btn-sm btn-success" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($orderText) ?>"><i class="fa-brands fa-whatsapp me-1"></i> Buka WhatsApp</a>
    </div>
</div>
<?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$('#btnCopy').on('click', function () {
    const t = document.getElementById('orderText'); t.select();
    (navigator.clipboard ? navigator.clipboard.writeText(t.value) : Promise.resolve(document.execCommand('copy'))).then(() => showToast('Teks pesanan disalin.'));
});
</script>
<?= $this->endSection() ?>
