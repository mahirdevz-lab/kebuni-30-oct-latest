<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$q   = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',') ?: '0';
$qs  = static fn (array $extra = []) => http_build_query(array_merge(['from' => $from, 'to' => $to, 'product_id' => $productId ?: null], $extra));
$typeLabel = [
    'OPENING' => 'Stok awal', 'PURCHASE' => 'Pembelian', 'BONUS' => 'Bonus supplier', 'SALE' => 'Penjualan', 'PROMO_FREE' => 'Gratis promo',
    'CUSTOMER_RETURN' => 'Retur pelanggan', 'SUPPLIER_RETURN' => 'Retur supplier', 'BROKEN' => 'Broken stock', 'OPNAME_ADJUST' => 'Adjustment opname',
    'TRANSFER_IN' => 'Transfer masuk', 'TRANSFER_OUT' => 'Transfer keluar', 'REVERSAL' => 'Reversal',
];
$refUrl = static function (string $type, $id): ?string {
    return match ($type) {
        'POS_SALE'      => base_url('admin/pos/sales/' . $id),
        'GOODS_RECEIPT' => base_url('admin/stock/receipts/' . $id),
        'STOCK_OPNAME'  => base_url('admin/stock/opname/' . $id),
        'POS_RETURN'    => base_url('admin/pos/returns/' . $id),
        'SUPPLIER_RETURN' => base_url('admin/stock/supplier-returns/' . $id),
        'STOCK_OPENING' => base_url('admin/stock/opening/' . $id),
        'BROKEN_STOCK'  => base_url('admin/stock/broken?status=ALL'),
        default         => null,
    };
};
?>
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-2"><label class="form-label mb-1">Dari</label><input type="date" name="from" value="<?= esc($from) ?>" class="form-control"></div>
            <div class="col-md-2"><label class="form-label mb-1">Sampai</label><input type="date" name="to" value="<?= esc($to) ?>" class="form-control"></div>
            <div class="col-md-4"><label class="form-label mb-1">Produk</label>
                <select name="product_id" class="form-select"><option value="">Semua produk</option>
                    <?php foreach ($products as $p): ?><option value="<?= $p['id'] ?>" <?= (int) $p['id'] === $productId ? 'selected' : '' ?>><?= esc($p['product_name']) ?> (<?= esc($p['product_code']) ?>)</option><?php endforeach ?>
                </select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Tampilkan</button></div>
            <div class="col-md-2"><a class="btn btn-outline-secondary w-100" target="_blank" href="<?= base_url('admin/stock/book?' . $qs(['print' => 1])) ?>"><i class="fa-solid fa-print me-1"></i> Cetak</a></div>
        </form>
        <div class="d-flex flex-wrap gap-2 mt-2 small">
            <span class="text-muted">Periode cepat:</span>
            <a href="?<?= http_build_query(['from' => date('Y-m-d'), 'to' => date('Y-m-d'), 'product_id' => $productId ?: null]) ?>">Hari ini</a>
            <a href="?<?= http_build_query(['from' => date('Y-m-d', strtotime('monday this week')), 'to' => date('Y-m-d'), 'product_id' => $productId ?: null]) ?>">Minggu ini</a>
            <a href="?<?= http_build_query(['from' => date('Y-m-01'), 'to' => date('Y-m-t'), 'product_id' => $productId ?: null]) ?>">Bulan ini</a>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="section-header"><i class="fa-solid fa-book-open me-1"></i> Buku Stok <?= date('d/m/Y', strtotime($from)) ?> – <?= date('d/m/Y', strtotime($to)) ?></div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Tanggal</th><th>Produk</th><th class="text-end">Saldo awal</th><th class="text-end">Masuk</th><th class="text-end">Keluar</th><th class="text-end">Saldo akhir</th><th>Satuan</th><th>Referensi</th></tr></thead>
            <tbody>
            <?php if (! $book): ?><tr><td colspan="8" class="text-center text-muted py-4">Tidak ada mutasi pada periode ini.</td></tr><?php endif ?>
            <?php foreach ($book as $b): ?>
                <?php if (! $b['lines']): ?>
                    <tr><td>-</td><td><?= esc($b['product']['product_name']) ?></td><td class="text-end"><?= $q($b['opening']) ?></td><td class="text-end">0</td><td class="text-end">0</td><td class="text-end fw-semibold"><?= $q($b['closing']) ?></td><td><?= esc($b['product']['unit']) ?></td><td class="text-muted small">tidak ada mutasi</td></tr>
                <?php endif ?>
                <?php foreach ($b['lines'] as $l): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($l['date'])) ?></td>
                        <td><?= esc($b['product']['product_name']) ?></td>
                        <td class="text-end"><?= $q($l['opening']) ?></td>
                        <td class="text-end text-success"><?= $q($l['in']) ?></td>
                        <td class="text-end text-danger"><?= $q($l['out']) ?></td>
                        <td class="text-end fw-semibold"><?= $q($l['closing']) ?></td>
                        <td><?= esc($b['product']['unit']) ?></td>
                        <td class="small text-muted text-truncate" style="max-width:260px" title="<?= esc($l['refs']) ?>"><?= esc($l['refs']) ?></td>
                    </tr>
                <?php endforeach ?>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($productId): ?>
<div class="card">
    <div class="section-header"><i class="fa-solid fa-list-ol me-1"></i> Kartu stok (setiap mutasi dapat ditelusuri ke transaksi sumber)</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Waktu efektif</th><th>Jenis</th><th class="text-end">Masuk</th><th class="text-end">Keluar</th><th class="text-end">Sebelum</th><th class="text-end">Sesudah</th><th class="text-end">Biaya/unit</th><th class="text-end">Avg sesudah</th><th>Referensi</th><th>Pengguna</th><th>Alasan</th></tr></thead>
            <tbody>
            <?php if (! $ledger): ?><tr><td colspan="11" class="text-center text-muted py-3">Tidak ada mutasi.</td></tr><?php endif ?>
            <?php foreach ($ledger as $l): ?>
                <?php $url = $refUrl($l['reference_type'], $l['reference_id']); ?>
                <tr class="<?= $l['status'] === 'REVERSED' ? 'text-muted text-decoration-line-through' : '' ?>">
                    <td><?= date('d/m/Y H:i', strtotime($l['effective_at'])) ?></td>
                    <td><?= $typeLabel[$l['movement_type']] ?? esc($l['movement_type']) ?></td>
                    <td class="text-end"><?= (float) $l['qty_in'] ? $q($l['qty_in']) : '' ?></td>
                    <td class="text-end"><?= (float) $l['qty_out'] ? $q($l['qty_out']) : '' ?></td>
                    <td class="text-end"><?= $q($l['balance_before']) ?></td>
                    <td class="text-end fw-semibold"><?= $q($l['balance_after']) ?></td>
                    <td class="text-end"><?= number_format((float) $l['unit_cost'], 2, ',', '.') ?></td>
                    <td class="text-end"><?= number_format((float) $l['avg_cost_after'], 2, ',', '.') ?></td>
                    <td><?= $url ? '<a href="' . $url . '">' . esc($l['reference_type'] . '#' . $l['reference_id']) . '</a>' : esc($l['reference_type'] . '#' . $l['reference_id']) ?></td>
                    <td><?= esc($l['user_name'] ?? '-') ?></td>
                    <td class="small"><?= esc($l['reason'] ?? '') ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif ?>
<?= $this->endSection() ?>
