<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $q = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ','); ?>
<div class="card">
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-3"><div class="text-muted small">No</div><strong><?= esc($receipt['receipt_no']) ?></strong></div>
            <div class="col-md-3"><div class="text-muted small">Tanggal</div><?= date('d/m/Y', strtotime($receipt['receipt_date'])) ?></div>
            <div class="col-md-3"><div class="text-muted small">Supplier</div><?= esc($receipt['nama_supplier'] ?? '-') ?></div>
            <div class="col-md-3"><div class="text-muted small">Nota / dicatat oleh</div><?= esc($receipt['reference_no'] ?? '-') ?> · <?= esc($receipt['user_name'] ?? '-') ?></div>
        </div>
        <table class="table align-middle">
            <thead><tr><th>Produk</th><th class="text-end">Qty reguler</th><th class="text-end">Qty bonus supplier</th><th class="text-end">Total diterima</th><th class="text-end">Biaya baris (total)</th><th class="text-end">Biaya / unit</th></tr></thead>
            <tbody>
            <?php foreach ($items as $i): ?>
                <tr>
                    <td><?= esc($i['product_name']) ?> <span class="small text-muted"><?= esc($i['product_code']) ?></span></td>
                    <td class="text-end"><?= $q($i['qty_regular']) ?> <?= esc($i['unit']) ?></td>
                    <td class="text-end"><?= $q($i['qty_bonus']) ?> <?= esc($i['unit']) ?></td>
                    <td class="text-end fw-semibold"><?= $q((float) $i['qty_regular'] + (float) $i['qty_bonus']) ?> <?= esc($i['unit']) ?></td>
                    <td class="text-end"><?= number_format((float) $i['line_cost'], 0, ',', '.') ?></td>
                    <td class="text-end"><?= (float) $i['qty_regular'] > 0 ? number_format((float) $i['line_cost'] / (float) $i['qty_regular'], 0, ',', '.') : '-' ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
        <?php if ($receipt['notes']): ?><p class="mb-0"><span class="text-muted">Catatan:</span> <?= esc($receipt['notes']) ?></p><?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
