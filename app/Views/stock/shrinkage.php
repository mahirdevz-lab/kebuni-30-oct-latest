<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$q  = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',') ?: '0';
$rp = static fn ($v) => number_format((float) $v, 0, ',', '.');
$t  = $r['totals'];
$statusLabel = ['DRAFT' => ['warning', 'Menunggu'], 'POSTED' => ['success', 'Disetujui'], 'REJECTED' => ['danger', 'Ditolak'], 'CANCELLED' => ['secondary', 'Dibatalkan']];
$qs = http_build_query(['from' => $from, 'to' => $to, 'product_id' => $productId, 'print' => 1]);
?>
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-2"><label class="form-label mb-1">Dari</label><input type="date" name="from" value="<?= esc($from) ?>" class="form-control"></div>
            <div class="col-md-2"><label class="form-label mb-1">Sampai</label><input type="date" name="to" value="<?= esc($to) ?>" class="form-control"></div>
            <div class="col-md-4"><label class="form-label mb-1">Produk</label>
                <select name="product_id" class="form-select"><option value="">Semua produk</option>
                    <?php foreach ($products as $p): ?><option value="<?= $p['id'] ?>" <?= (int) $p['id'] === (int) $productId ? 'selected' : '' ?>><?= esc($p['product_name']) ?> (<?= esc($p['product_code']) ?>)</option><?php endforeach ?>
                </select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Tampilkan</button></div>
            <div class="col-md-2"><a class="btn btn-outline-secondary w-100" target="_blank" href="<?= base_url('admin/stock/shrinkage?' . $qs) ?>"><i class="fa-solid fa-print me-1"></i> Cetak</a></div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg"><div class="card h-100"><div class="card-body"><div class="text-muted small">Known shrinkage (Broken Stock)</div><div class="fs-5 fw-bold">Rp <?= $rp($t['known']) ?></div></div></div></div>
    <div class="col-6 col-lg"><div class="card h-100"><div class="card-body"><div class="text-muted small">Unknown shrinkage (opname)</div><div class="fs-5 fw-bold text-danger">Rp <?= $rp($t['unknown']) ?></div></div></div></div>
    <div class="col-6 col-lg"><div class="card h-100"><div class="card-body"><div class="text-muted small">Total penyusutan</div><div class="fs-5 fw-bold">Rp <?= $rp($t['total']) ?></div>
        <div class="small text-muted"><?= ($t['cogs'] + $t['total']) > 0 ? number_format($t['total'] / ($t['cogs'] + $t['total']) * 100, 2, ',', '.') . '% dari persediaan keluar' : '' ?></div></div></div></div>
    <div class="col-6 col-lg"><div class="card h-100"><div class="card-body"><div class="text-muted small">Fisik lebih (opname)</div><div class="fs-5 fw-bold text-warning">Rp <?= $rp($t['surplus']) ?></div><div class="small text-muted">tidak mengurangi penyusutan</div></div></div></div>
    <div class="col-6 col-lg"><div class="card h-100"><div class="card-body"><div class="text-muted small">Broken Stock belum disetujui</div><div class="fs-5 fw-bold"><?= $r['pending']['count'] ?> laporan</div><div class="small text-muted">estimasi Rp <?= $rp($r['pending']['value']) ?></div></div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="section-header"><i class="fa-solid fa-boxes-stacked me-1"></i> Per produk</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Produk</th><th class="text-end">Known</th><th class="text-end">Unknown</th><th class="text-end">Total (Rp)</th><th class="text-end">HPP terjual (Rp)</th><th class="text-end">Rasio</th><th class="text-end">Fisik lebih</th></tr></thead>
                    <tbody>
                    <?php if (! $r['byProduct']): ?><tr><td colspan="7" class="text-center text-muted py-3">Tidak ada penyusutan pada periode ini.</td></tr><?php endif ?>
                    <?php foreach ($r['byProduct'] as $p): ?>
                        <tr>
                            <td><?= esc($p['product_name']) ?> <span class="small text-muted"><?= esc($p['product_code']) ?></span></td>
                            <td class="text-end"><?= $q($p['known_qty']) ?> <?= esc($p['unit']) ?><div class="small text-muted"><?= $rp($p['known_value']) ?></div></td>
                            <td class="text-end"><?= $q($p['unknown_qty']) ?> <?= esc($p['unit']) ?><div class="small text-muted"><?= $rp($p['unknown_value']) ?></div></td>
                            <td class="text-end fw-semibold"><?= $rp($p['total_value']) ?></td>
                            <td class="text-end"><?= $rp($p['cogs']) ?></td>
                            <td class="text-end"><?= $p['ratio'] !== null ? number_format($p['ratio'], 2, ',', '.') . '%' : '-' ?></td>
                            <td class="text-end"><?= (float) $p['surplus_qty'] ? $q($p['surplus_qty']) . ' ' . esc($p['unit']) : '-' ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-muted">Rasio = penyusutan ÷ (HPP terjual + penyusutan) pada periode — porsi persediaan keluar yang hilang/rusak.</div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="section-header"><i class="fa-solid fa-tags me-1"></i> Known shrinkage per alasan</div>
            <ul class="list-group list-group-flush">
                <?php if (! $r['byReason']): ?><li class="list-group-item text-muted">Belum ada.</li><?php endif ?>
                <?php foreach ($r['byReason'] as $code => $v): ?>
                    <li class="list-group-item d-flex justify-content-between"><span><?= esc($reasons[$code] ?? $code) ?></span><strong>Rp <?= $rp($v) ?></strong></li>
                <?php endforeach ?>
            </ul>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="section-header"><i class="fa-solid fa-heart-crack me-1"></i> Detail known shrinkage (Broken Stock)</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>No</th><th>Waktu</th><th>Produk</th><th class="text-end">Qty</th><th>Alasan</th><th class="text-end">Nilai biaya</th><th>Status</th><th>Pelapor / penyetuju</th></tr></thead>
            <tbody>
            <?php if (! $r['known']): ?><tr><td colspan="8" class="text-center text-muted py-3">Tidak ada.</td></tr><?php endif ?>
            <?php foreach ($r['known'] as $k): ?>
                <tr>
                    <td><?= esc($k['report_no']) ?><?= $k['photo_path'] ? ' <a href="' . base_url('admin/stock/broken/' . $k['id'] . '/photo') . '" target="_blank" title="Foto"><i class="fa-solid fa-image"></i></a>' : '' ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($k['effective_at'] ?? $k['reported_at'])) ?></td>
                    <td><?= esc($k['product_name']) ?></td>
                    <td class="text-end"><?= $q($k['qty']) ?> <?= esc($k['unit']) ?></td>
                    <td class="small"><?= esc($reasons[$k['reason_code']] ?? $k['reason_code']) ?><?= $k['reason'] ? ': ' . esc($k['reason']) : '' ?></td>
                    <td class="text-end"><?= $k['status'] === 'POSTED' ? $rp($k['ledger_value']) : '<span class="text-muted">~' . $rp($k['est_value']) . '</span>' ?></td>
                    <td><span class="badge text-bg-<?= $statusLabel[$k['status']][0] ?>"><?= $statusLabel[$k['status']][1] ?></span></td>
                    <td class="small"><?= esc($k['reporter'] ?? '-') ?> / <?= esc($k['approver'] ?? '-') ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-scale-unbalanced me-1"></i> Detail selisih opname</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Sesi</th><th>Cut-off</th><th>Produk</th><th class="text-end">Buku</th><th class="text-end">Fisik</th><th class="text-end">Selisih</th><th class="text-end">Nilai</th><th>Jenis</th><th>Catatan</th></tr></thead>
            <tbody>
            <?php $rows = array_merge($r['unknown'], $r['surplus']); ?>
            <?php if (! $rows): ?><tr><td colspan="9" class="text-center text-muted py-3">Tidak ada.</td></tr><?php endif ?>
            <?php foreach ($rows as $o): ?>
                <?php $d = (float) $o['difference']; ?>
                <tr class="<?= $d > 0 ? '' : 'table-warning' ?>">
                    <td><a href="<?= base_url('admin/stock/opname/' . $o['session_id']) ?>"><?= esc($o['session_no']) ?></a></td>
                    <td><?= date('d/m/Y H:i', strtotime($o['cutoff_at'])) ?></td>
                    <td><?= esc($o['product_name']) ?></td>
                    <td class="text-end"><?= $q($o['book_qty']) ?></td>
                    <td class="text-end"><?= $q($o['counted_qty']) ?></td>
                    <td class="text-end fw-semibold"><?= $q($o['difference']) ?> <?= esc($o['unit']) ?></td>
                    <td class="text-end"><?= $rp($o['ledger_value']) ?></td>
                    <td><?= $d > 0 ? '<span class="badge text-bg-danger">Unknown shrinkage</span>' : '<span class="badge text-bg-warning">Fisik lebih</span>' ?></td>
                    <td class="small"><?= esc($o['note'] ?? '') ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
