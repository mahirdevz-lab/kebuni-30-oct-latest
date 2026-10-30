<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penyusutan <?= esc($from) ?> s.d. <?= esc($to) ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 16px; color: #000; background: #fff; }
        h2 { margin: 0 0 2px; font-size: 16px; } h3 { font-size: 13px; margin: 14px 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #444; padding: 3px 5px; } th { background: #eee; }
        .r { text-align: right; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<?php
$q  = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',') ?: '0';
$rp = static fn ($v) => number_format((float) $v, 0, ',', '.');
$t  = $r['totals'];
?>
<button class="no-print" onclick="window.print()">Cetak / Simpan PDF</button>
<h2><?= esc($store) ?> — Laporan Penyusutan</h2>
<div>Periode: <?= date('d/m/Y', strtotime($from)) ?> s.d. <?= date('d/m/Y', strtotime($to)) ?> · Lokasi: Toko · Dicetak <?= date('d/m/Y H:i') ?> oleh <?= esc(session()->get('full_name')) ?></div>

<h3>Ringkasan</h3>
<table>
    <tr><td>Known shrinkage (Broken Stock disetujui)</td><td class="r">Rp <?= $rp($t['known']) ?></td></tr>
    <tr><td>Unknown shrinkage (selisih opname, fisik kurang)</td><td class="r">Rp <?= $rp($t['unknown']) ?></td></tr>
    <tr><th style="text-align:left">Total penyusutan</th><th class="r">Rp <?= $rp($t['total']) ?></th></tr>
    <tr><td>Fisik lebih (selisih opname, dicatat terpisah)</td><td class="r">Rp <?= $rp($t['surplus']) ?></td></tr>
    <tr><td>Broken Stock belum disetujui (estimasi, belum mengurangi stok)</td><td class="r"><?= $r['pending']['count'] ?> laporan · Rp <?= $rp($r['pending']['value']) ?></td></tr>
</table>

<h3>Per produk</h3>
<table>
    <thead><tr><th>Produk</th><th class="r">Known (qty)</th><th class="r">Known (Rp)</th><th class="r">Unknown (qty)</th><th class="r">Unknown (Rp)</th><th class="r">Total (Rp)</th><th class="r">HPP terjual</th><th class="r">Rasio</th></tr></thead>
    <tbody>
    <?php if (! $r['byProduct']): ?><tr><td colspan="8">Tidak ada penyusutan.</td></tr><?php endif ?>
    <?php foreach ($r['byProduct'] as $p): ?>
        <tr><td><?= esc($p['product_name']) ?></td><td class="r"><?= $q($p['known_qty']) ?> <?= esc($p['unit']) ?></td><td class="r"><?= $rp($p['known_value']) ?></td>
            <td class="r"><?= $q($p['unknown_qty']) ?> <?= esc($p['unit']) ?></td><td class="r"><?= $rp($p['unknown_value']) ?></td><td class="r"><b><?= $rp($p['total_value']) ?></b></td>
            <td class="r"><?= $rp($p['cogs']) ?></td><td class="r"><?= $p['ratio'] !== null ? number_format($p['ratio'], 2, ',', '.') . '%' : '-' ?></td></tr>
    <?php endforeach ?>
    </tbody>
</table>

<h3>Detail Broken Stock</h3>
<table>
    <thead><tr><th>No</th><th>Waktu</th><th>Produk</th><th class="r">Qty</th><th>Alasan</th><th class="r">Nilai</th><th>Status</th><th>Pelapor / Penyetuju</th></tr></thead>
    <tbody>
    <?php if (! $r['known']): ?><tr><td colspan="8">Tidak ada.</td></tr><?php endif ?>
    <?php foreach ($r['known'] as $k): ?>
        <tr><td><?= esc($k['report_no']) ?></td><td><?= date('d/m/Y H:i', strtotime($k['effective_at'] ?? $k['reported_at'])) ?></td><td><?= esc($k['product_name']) ?></td>
            <td class="r"><?= $q($k['qty']) ?> <?= esc($k['unit']) ?></td><td><?= esc($reasons[$k['reason_code']] ?? $k['reason_code']) ?><?= $k['reason'] ? ': ' . esc($k['reason']) : '' ?></td>
            <td class="r"><?= $k['status'] === 'POSTED' ? $rp($k['ledger_value']) : '~' . $rp($k['est_value']) ?></td><td><?= esc($k['status']) ?></td>
            <td><?= esc($k['reporter'] ?? '-') ?> / <?= esc($k['approver'] ?? '-') ?></td></tr>
    <?php endforeach ?>
    </tbody>
</table>

<h3>Detail selisih opname</h3>
<table>
    <thead><tr><th>Sesi</th><th>Cut-off</th><th>Produk</th><th class="r">Buku</th><th class="r">Fisik</th><th class="r">Selisih</th><th class="r">Nilai</th><th>Jenis</th><th>Catatan</th></tr></thead>
    <tbody>
    <?php $rows = array_merge($r['unknown'], $r['surplus']); ?>
    <?php if (! $rows): ?><tr><td colspan="9">Tidak ada.</td></tr><?php endif ?>
    <?php foreach ($rows as $o): ?>
        <tr><td><?= esc($o['session_no']) ?></td><td><?= date('d/m/Y H:i', strtotime($o['cutoff_at'])) ?></td><td><?= esc($o['product_name']) ?></td>
            <td class="r"><?= $q($o['book_qty']) ?></td><td class="r"><?= $q($o['counted_qty']) ?></td><td class="r"><?= $q($o['difference']) ?> <?= esc($o['unit']) ?></td>
            <td class="r"><?= $rp($o['ledger_value']) ?></td><td><?= (float) $o['difference'] > 0 ? 'Unknown shrinkage' : 'Fisik lebih' ?></td><td><?= esc($o['note'] ?? '') ?></td></tr>
    <?php endforeach ?>
    </tbody>
</table>
</body>
</html>
