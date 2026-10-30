<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$q  = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',') ?: '0';
$rp = static fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
$method = ['CASH' => 'Tunai dari Kas Kasir', 'TRANSFER' => 'Transfer dari ' . ($r['account_name'] ?? '-'), 'NONE' => 'Tanpa pengembalian uang'];
?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <div class="row g-2 small mb-3">
                    <div class="col-sm-4"><span class="text-muted">No retur</span><div class="fw-semibold"><?= esc($r['return_no']) ?></div></div>
                    <div class="col-sm-4"><span class="text-muted">Dari penjualan</span><div><a href="<?= base_url('admin/pos/sales/' . $r['sale_id']) ?>"><?= esc($r['sale_no']) ?></a></div></div>
                    <div class="col-sm-4"><span class="text-muted">Waktu</span><div><?= date('d/m/Y H:i', strtotime($r['return_date'])) ?> · <?= esc($r['user_name'] ?? '-') ?></div></div>
                    <div class="col-sm-8"><span class="text-muted">Alasan</span><div><?= esc($reasons[$r['reason_code']] ?? $r['reason_code']) ?><?= $r['reason'] ? ': ' . esc($r['reason']) : '' ?></div></div>
                    <div class="col-sm-4"><span class="text-muted">Pelanggan</span><div><?= esc($r['customer_name'] ?? '-') ?></div></div>
                </div>
                <table class="table table-sm align-middle">
                    <thead><tr><th>Produk</th><th class="text-end">Qty</th><th class="text-end">Harga</th><th class="text-end">Nilai</th><th>Kondisi</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $i): ?>
                        <tr>
                            <td><?= esc($i['product_name']) ?><?= $i['line_type'] === 'PROMO_FREE' ? ' <span class="badge text-bg-info">gratis promo</span>' : '' ?></td>
                            <td class="text-end"><?= $q($i['qty']) ?> <?= esc($i['unit']) ?></td>
                            <td class="text-end"><?= $rp($i['unit_price']) ?></td>
                            <td class="text-end"><?= $rp($i['value']) ?></td>
                            <td><?= $i['item_condition'] === 'RESTOCK' ? 'Masuk stok' : 'Rusak → <a href="' . base_url('admin/stock/broken?status=ALL') . '">' . esc($i['broken_no']) . '</a>' ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
                <div class="d-flex justify-content-between"><span>Nilai retur</span><strong><?= $rp($r['return_value']) ?></strong></div>
                <div class="d-flex justify-content-between"><span>Uang dikembalikan</span><strong><?= $rp($r['refund_amount']) ?></strong></div>
                <?php if ((float) $r['due_reduction'] > 0): ?><div class="d-flex justify-content-between"><span>Mengurangi sisa tagihan</span><span><?= $rp($r['due_reduction']) ?></span></div><?php endif ?>
                <div class="small text-muted mt-1"><?= esc($method[$r['refund_method']]) ?> · HPP dibalik <?= $rp($r['cost_value']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body d-flex flex-wrap gap-2">
                <a href="<?= esc($whatsappUrl) ?>" target="_blank" rel="noopener" class="btn btn-success"><i class="fa-brands fa-whatsapp me-1"></i> Kirim Nota Retur via WhatsApp</a>
                <a href="<?= base_url('admin/pos/returns') ?>" class="btn btn-light">Daftar retur</a>
            </div>
            <div class="card-footer small text-muted">WhatsApp hanya dibuka dengan pesan terisi; kasir tetap menekan kirim.</div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
