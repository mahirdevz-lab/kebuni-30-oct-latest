<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $q = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',') ?: '0'; ?>
<div class="card">
    <div class="card-body">
        <div class="row g-2 small mb-3">
            <div class="col-sm-3"><span class="text-muted">No</span><div class="fw-semibold"><?= esc($doc['return_no']) ?></div></div>
            <div class="col-sm-3"><span class="text-muted">Tanggal</span><div><?= date('d/m/Y', strtotime($doc['return_date'])) ?></div></div>
            <div class="col-sm-3"><span class="text-muted">Supplier</span><div><?= esc($doc['nama_supplier'] ?? '-') ?></div></div>
            <div class="col-sm-3"><span class="text-muted">Penerimaan rujukan</span><div><?= esc($doc['receipt_no'] ?? '-') ?></div></div>
            <div class="col-sm-6"><span class="text-muted">Alasan</span><div><?= esc($reasons[$doc['reason_code']] ?? '') ?><?= $doc['reason'] ? ': ' . esc($doc['reason']) : '' ?></div></div>
            <div class="col-sm-3"><span class="text-muted">Diakui supplier</span><div>Rp <?= number_format((float) $doc['credit_amount'], 0, ',', '.') ?> (<?= esc($doc['settlement']) ?>)</div></div>
            <div class="col-sm-3"><span class="text-muted">Oleh</span><div><?= esc($doc['user_name'] ?? '-') ?></div></div>
        </div>
        <table class="table table-sm align-middle">
            <thead><tr><th>Produk</th><th class="text-end">Qty</th><th class="text-end">Biaya/unit</th><th class="text-end">Nilai stok</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $i): ?>
                <tr><td><?= esc($i['product_name']) ?> <span class="small text-muted"><?= esc($i['product_code']) ?></span></td>
                    <td class="text-end"><?= $q($i['qty']) ?> <?= esc($i['unit']) ?></td>
                    <td class="text-end"><?= number_format((float) $i['unit_cost'], 2, ',', '.') ?></td>
                    <td class="text-end"><?= number_format((float) $i['cost_value'], 0, ',', '.') ?></td>
                    <td><a href="<?= base_url('admin/stock/book?product_id=' . $i['product_id']) ?>" class="small">kartu stok</a></td></tr>
            <?php endforeach ?>
            </tbody>
            <tfoot><tr class="fw-bold"><td colspan="3">Total nilai stok keluar</td><td class="text-end"><?= number_format((float) $doc['cost_value'], 0, ',', '.') ?></td><td></td></tr></tfoot>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
