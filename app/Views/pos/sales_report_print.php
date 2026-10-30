<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penjualan <?= esc($from) ?> s.d. <?= esc($to) ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 16px; color: #000; background: #fff; }
        h2 { margin: 0 0 2px; font-size: 16px; } h3 { font-size: 13px; margin: 14px 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #444; padding: 3px 5px; } th { background: #eee; }
        .r { text-align: right; } tr.tot td { font-weight: bold; background: #f5f5f5; } .void { color: #888; text-decoration: line-through; }
        @media print { .no-print { display: none; } h3 { page-break-after: avoid; } tr { page-break-inside: avoid; } }
    </style>
</head>
<body>
<?php
$rp = static fn ($v) => number_format((float) $v, 0, ',', '.');
$q  = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',') ?: '0';
$t  = $report['totals'];
?>
<button class="no-print" onclick="window.print()">Cetak / Simpan PDF</button>
<h2><?= esc($store) ?> — Laporan Penjualan Harian</h2>
<div>Periode: <?= date('d/m/Y', strtotime($from)) ?> s.d. <?= date('d/m/Y', strtotime($to)) ?><?= $mine ? ' · hanya transaksi ' . esc(session()->get('full_name')) : '' ?> · Dicetak <?= date('d/m/Y H:i') ?> oleh <?= esc(session()->get('full_name')) ?></div>

<h3>Ringkasan per hari</h3>
<table>
    <thead><tr><th>Tanggal</th><th class="r">Transaksi</th><th class="r">Omzet kotor</th><th class="r">Retur</th><th class="r">Omzet bersih</th><th class="r">Diskon</th><th class="r">Modal (HPP)</th><th class="r">Laba kotor</th><th class="r">Margin</th><th class="r">Tunai</th><th class="r">QRIS</th><th class="r">Transfer</th><th class="r">Sisa tagihan</th><th class="r">Void</th></tr></thead>
    <tbody>
    <?php if (! $report['daily']): ?><tr><td colspan="14">Tidak ada transaksi pada periode ini.</td></tr><?php endif ?>
    <?php foreach ($report['daily'] as $d): ?>
        <tr><td><?= date('d/m/Y', strtotime($d['date'])) ?></td><td class="r"><?= $d['count'] ?></td><td class="r"><?= $rp($d['omzet']) ?></td><td class="r"><?= $rp($d['returns']) ?></td><td class="r"><strong><?= $rp($d['net']) ?></strong></td>
            <td class="r"><?= $rp($d['discount']) ?></td><td class="r"><?= $rp($d['cogs_net']) ?></td><td class="r"><?= $rp($d['profit']) ?></td><td class="r"><?= $d['margin'] ?>%</td><td class="r"><?= $rp($d['cash']) ?></td>
            <td class="r"><?= $rp($d['qris']) ?></td><td class="r"><?= $rp($d['transfer']) ?></td><td class="r"><?= $rp($d['due']) ?></td><td class="r"><?= $d['voids'] ?></td></tr>
    <?php endforeach ?>
    <?php if ($report['daily']): ?>
        <tr class="tot"><td>Total</td><td class="r"><?= $t['count'] ?></td><td class="r"><?= $rp($t['omzet']) ?></td><td class="r"><?= $rp($t['returns']) ?></td><td class="r"><?= $rp($t['net']) ?></td>
            <td class="r"><?= $rp($t['discount']) ?></td><td class="r"><?= $rp($t['cogs_net']) ?></td><td class="r"><?= $rp($t['profit']) ?></td><td class="r"><?= $t['margin'] ?>%</td><td class="r"><?= $rp($t['cash']) ?></td>
            <td class="r"><?= $rp($t['qris']) ?></td><td class="r"><?= $rp($t['transfer']) ?></td><td class="r"><?= $rp($t['due']) ?></td><td class="r"><?= $t['voids'] ?></td></tr>
    <?php endif ?>
    </tbody>
</table>
<div style="margin-top:4px;color:#444">Omzet kotor = transaksi berstatus Diposting (void tidak dihitung). <b>Omzet bersih = omzet kotor − retur</b> (nilai yang dibayar pelanggan, pada tanggal retur). Modal (HPP) = biaya rata-rata barang terjual dikurangi biaya pokok barang yang diretur; barang retur yang rusak masuk ke laporan penyusutan. Laba kotor = omzet bersih − HPP. Tunai/QRIS/Transfer = pembayaran diterima, belum dikurangi refund (QRIS &amp; Transfer termasuk yang belum terverifikasi).<?= $mine ? ' Retur tidak ditampilkan pada laporan per kasir.' : '' ?></div>
<h3>Penjualan per produk (sebelum retur)</h3>
<table>
    <thead><tr><th>Produk</th><th class="r">Qty</th><th class="r">Penjualan</th><th class="r">Modal (HPP)</th><th class="r">Laba kotor</th></tr></thead>
    <tbody>
    <?php if (! $report['products']): ?><tr><td colspan="5">-</td></tr><?php endif ?>
    <?php foreach ($report['products'] as $p): ?>
        <tr><td><?= esc($p['name']) ?></td><td class="r"><?= $q($p['qty']) ?> <?= esc($p['unit']) ?></td><td class="r"><?= $rp($p['omzet']) ?></td><td class="r"><?= $rp($p['cogs']) ?></td><td class="r"><?= $rp($p['profit']) ?></td></tr>
    <?php endforeach ?>
    </tbody>
</table>

<h3>Rincian transaksi</h3>
<table>
    <thead><tr><th>No</th><th>Waktu</th><th>Kasir</th><th>Pelanggan</th><th class="r">Total</th><th class="r">Dibayar</th><th class="r">Sisa</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($report['sales'] as $s): $v = $s['status'] === 'VOIDED'; ?>
        <tr class="<?= $v ? 'void' : '' ?>"><td><?= esc($s['sale_no']) ?></td><td><?= date('d/m/Y H:i', strtotime($s['sale_date'])) ?></td><td><?= esc($s['cashier'] ?? '-') ?></td><td><?= esc($s['customer'] ?? '-') ?></td>
            <td class="r"><?= $rp($s['grand_total']) ?></td><td class="r"><?= $rp($s['paid_total']) ?></td><td class="r"><?= $rp($s['balance_due']) ?></td><td><?= $v ? 'Dibatalkan' : esc($s['payment_status']) ?></td></tr>
    <?php endforeach ?>
    </tbody>
</table>
</body>
</html>
