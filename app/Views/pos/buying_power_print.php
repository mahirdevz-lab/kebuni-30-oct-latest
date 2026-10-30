<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Buying Power <?= esc($from) ?> s.d. <?= esc($to) ?></title>
    <style>
        @page { size: A4 landscape; margin: 12mm 12mm 14mm; @bottom-center { content: "Halaman " counter(page) " dari " counter(pages); font: 9px Arial, sans-serif; color: #666; } }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { font-family: "Segoe UI", Arial, sans-serif; font-size: 11px; color: #1f2937; background: #fff; margin: 0; padding: 14px; line-height: 1.35; }
        .toolbar { margin-bottom: 10px; } .toolbar button { padding: 6px 14px; font-size: 13px; cursor: pointer; }
        header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 3px solid #166534; padding-bottom: 8px; margin-bottom: 12px; }
        .brand { display: flex; align-items: center; gap: 10px; } .brand img { height: 40px; width: auto; }
        .brand .store { font-size: 17px; font-weight: 700; color: #166534; } .brand .doc { font-size: 12px; color: #374151; }
        .meta { text-align: right; font-size: 10px; color: #4b5563; } .meta b { color: #111827; }
        .kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 12px; }
        .kpi { border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 10px; background: #f9fafb; }
        .kpi .l { font-size: 9.5px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; } .kpi .v { font-size: 18px; font-weight: 700; margin-top: 2px; } .kpi .s { font-size: 9.5px; color: #6b7280; }
        .kpi.main { background: #ecfdf5; border-color: #16a34a; } .kpi.main .v { color: #166534; }
        h3 { font-size: 12.5px; margin: 14px 0 6px; color: #166534; border-left: 4px solid #16a34a; padding-left: 7px; page-break-after: avoid; }
        .cols { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .bars .row { display: grid; grid-template-columns: 150px 1fr 82px; align-items: center; gap: 6px; margin: 3px 0; }
        .bars .name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; } .bars .track { background: #e5e7eb; height: 11px; border-radius: 3px; overflow: hidden; }
        .bars .fill { height: 100%; background: #16a34a; } .bars .fill.b { background: #2563eb; } .bars .val { text-align: right; font-variant-numeric: tabular-nums; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; } tr { page-break-inside: avoid; }
        th { background: #166534; color: #fff; font-weight: 600; text-align: left; padding: 5px 6px; font-size: 10px; }
        td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; } tbody tr:nth-child(even) td { background: #f9fafb; }
        .r { text-align: right; font-variant-numeric: tabular-nums; } .c { text-align: center; } .muted { color: #6b7280; } .strong { font-weight: 700; }
        tr.total td { background: #ecfdf5 !important; font-weight: 700; border-top: 2px solid #16a34a; }
        .note { margin-top: 10px; padding: 8px 10px; background: #f3f4f6; border-radius: 6px; font-size: 9.5px; color: #4b5563; }
        .sign { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; margin-top: 26px; text-align: center; font-size: 10px; page-break-inside: avoid; }
        .sign .line { margin-top: 44px; border-top: 1px solid #6b7280; padding-top: 3px; }
        @media print { .toolbar { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
<?php
$rp   = static fn ($v) => number_format((float) $v, 0, ',', '.');
$q    = static fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',') ?: '0';
$o    = $r['overall'];
$days = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
$byNoi   = $r['products'];  usort($byNoi, static fn ($a, $b) => [$b['noi'], $b['sales']] <=> [$a['noi'], $a['sales']]);
$bySales = $r['products'];  usort($bySales, static fn ($a, $b) => $b['sales'] <=> $a['sales']);
$topNoi  = array_slice($byNoi, 0, 8);   $maxNoi   = max(1, ...array_column($topNoi ?: [['noi' => 1]], 'noi'));
$topSale = array_slice($bySales, 0, 8); $maxSale  = max(1, ...array_column($topSale ?: [['sales' => 1]], 'sales'));
$topBasket = $r['products']; usort($topBasket, static fn ($a, $b) => $b['basket'] <=> $a['basket']);
?>
<div class="toolbar"><button onclick="window.print()">Cetak / Simpan PDF</button> <span class="muted">Pilih "Save as PDF" pada pilihan printer. Orientasi landscape sudah diatur otomatis.</span></div>

<header>
    <div class="brand">
        <img src="<?= base_url('kebuni.png') ?>" alt="" onerror="this.style.display='none'">
        <div><div class="store"><?= esc($store) ?></div><div class="doc">Laporan Buying Power &amp; Frekuensi Pembelian Produk</div></div>
    </div>
    <div class="meta">
        Periode <b><?= date('d/m/Y', strtotime($from)) ?> – <?= date('d/m/Y', strtotime($to)) ?></b> (<?= $days ?> hari)<br>
        Dicetak <?= date('d/m/Y H:i') ?> oleh <?= esc(session()->get('full_name')) ?>
    </div>
</header>

<div class="kpis">
    <div class="kpi"><div class="l">NoI (Number of Invoice)</div><div class="v"><?= $rp($o['noi']) ?></div><div class="s">rata-rata <?= $q($days ? $o['noi'] / $days : 0) ?> invoice / hari</div></div>
    <div class="kpi"><div class="l">Sales</div><div class="v">Rp <?= $rp($o['sales']) ?></div><div class="s">omzet kotor, sebelum retur</div></div>
    <div class="kpi main"><div class="l">Buying power (Sales ÷ NoI)</div><div class="v">Rp <?= $rp($o['buying_power']) ?></div><div class="s">rata-rata belanja per invoice</div></div>
    <div class="kpi"><div class="l">Isi keranjang rata-rata</div><div class="v"><?= $q($o['lines_per_invoice']) ?> <span style="font-size:11px;font-weight:400">jenis</span></div><div class="s"><?= $q($o['qty_per_invoice']) ?> qty per invoice</div></div>
</div>

<?php if ($r['daily']): ?>
<h3>Tren buying power per hari</h3>
<?php
$n = count($r['daily']);
if ($n >= 2 && $n <= 62):
    $maxBp = max(1, ...array_column($r['daily'], 'buying_power'));
    $W = 1000; $H = 150; $padL = 4; $padB = 26; $bw = ($W - $padL) / $n;
?>
<svg viewBox="0 0 <?= $W ?> <?= $H ?>" width="100%" style="max-height:170px" role="img" aria-label="Buying power per hari">
    <?php foreach ($r['daily'] as $i => $d): $h = ($H - $padB - 16) * $d['buying_power'] / $maxBp; $x = $padL + $i * $bw; ?>
        <rect x="<?= round($x + $bw * 0.15, 1) ?>" y="<?= round($H - $padB - $h, 1) ?>" width="<?= round($bw * 0.7, 1) ?>" height="<?= round($h, 1) ?>" fill="#16a34a" rx="2"/>
        <?php if ($n <= 31): ?><text x="<?= round($x + $bw / 2, 1) ?>" y="<?= round($H - $padB - $h - 3, 1) ?>" font-size="9" text-anchor="middle" fill="#374151"><?= $rp($d['buying_power'] / 1000) ?>k</text><?php endif ?>
        <?php if ($n <= 31 || $i % 2 === 0): ?><text x="<?= round($x + $bw / 2, 1) ?>" y="<?= $H - 12 ?>" font-size="9" text-anchor="middle" fill="#6b7280"><?= date('d/m', strtotime($d['date'])) ?></text><?php endif ?>
        <text x="<?= round($x + $bw / 2, 1) ?>" y="<?= $H - 2 ?>" font-size="8" text-anchor="middle" fill="#9ca3af"><?= $n <= 31 ? $d['noi'] . ' inv' : '' ?></text>
    <?php endforeach ?>
    <line x1="0" y1="<?= $H - $padB ?>" x2="<?= $W ?>" y2="<?= $H - $padB ?>" stroke="#9ca3af" stroke-width="1"/>
</svg>
<?php else: ?>
<table style="width:auto"><thead><tr><th>Tanggal</th><th class="r">NoI</th><th class="r">Sales</th><th class="r">Buying power</th></tr></thead><tbody>
<?php foreach ($r['daily'] as $d): ?><tr><td><?= date('d/m/Y', strtotime($d['date'])) ?></td><td class="r"><?= $d['noi'] ?></td><td class="r"><?= $rp($d['sales']) ?></td><td class="r"><?= $rp($d['buying_power']) ?></td></tr><?php endforeach ?>
</tbody></table>
<?php endif ?>
<?php endif ?>

<?php if ($r['products']): ?>
<div class="cols">
    <div>
        <h3>Most wanted item — NoI by article tertinggi</h3>
        <div class="bars">
            <?php foreach ($topNoi as $p): ?>
                <div class="row"><div class="name"><?= esc($p['name']) ?></div><div class="track"><div class="fill" style="width:<?= round($p['noi'] / $maxNoi * 100) ?>%"></div></div><div class="val"><?= $rp($p['noi']) ?>× <span class="muted" style="font-weight:400">(<?= $q($p['penetration']) ?>%)</span></div></div>
            <?php endforeach ?>
        </div>
    </div>
    <div>
        <h3>Penjualan terbesar (Rp)</h3>
        <div class="bars">
            <?php foreach ($topSale as $p): ?>
                <div class="row"><div class="name"><?= esc($p['name']) ?></div><div class="track"><div class="fill b" style="width:<?= round($p['sales'] / $maxSale * 100) ?>%"></div></div><div class="val"><?= $rp($p['sales']) ?></div></div>
            <?php endforeach ?>
        </div>
    </div>
</div>
<?php endif ?>

<h3>Rincian per artikel — NoI, total qty &amp; sales</h3>
<table>
    <thead><tr><th class="c" style="width:28px">#</th><th>Produk</th><th class="r">NoI</th><th class="r">Penetrasi</th><th class="r">Total qty</th><th class="r">Qty gratis</th><th class="r">Total sales</th><th class="r">Rata-rata qty / invoice</th><th class="r">Rata-rata sales / invoice</th><th class="r">Basket size</th></tr></thead>
    <tbody>
    <?php if (! $r['products']): ?><tr><td colspan="10" class="c muted">Belum ada penjualan pada periode ini.</td></tr><?php endif ?>
    <?php foreach ($byNoi as $i => $p): ?>
        <tr><td class="c muted"><?= $i + 1 ?></td><td><span class="strong"><?= esc($p['name']) ?></span> <span class="muted"><?= esc($p['code']) ?></span></td>
            <td class="r strong"><?= $rp($p['noi']) ?>×</td><td class="r"><?= $q($p['penetration']) ?>%</td>
            <td class="r"><?= $q($p['qty']) ?> <?= esc($p['unit']) ?></td><td class="r"><?= $p['free_qty'] > 0 ? $q($p['free_qty']) . ' ' . esc($p['unit']) : '–' ?></td>
            <td class="r strong"><?= $rp($p['sales']) ?></td><td class="r"><?= $q($p['avg_qty']) ?> <?= esc($p['unit']) ?></td><td class="r"><?= $rp($p['avg_sales']) ?></td><td class="r"><?= $rp($p['basket']) ?></td></tr>
    <?php endforeach ?>
    <?php if ($r['products']): ?>
        <tr class="total"><td></td><td>Total (<?= count($r['products']) ?> produk)</td><td class="r"><?= $rp($o['noi']) ?> invoice</td><td></td><td></td><td></td><td class="r"><?= $rp(array_sum(array_column($r['products'], 'sales'))) ?></td><td></td><td></td><td class="r"><?= $rp($o['buying_power']) ?></td></tr>
    <?php endif ?>
    </tbody>
</table>
<?php if ($r['products'] && $topBasket[0]['basket'] > 0): ?>
<div class="note" style="margin-top:8px">
    <b>Catatan membaca laporan:</b> produk dengan <b>basket size tertinggi</b> adalah <?= esc($topBasket[0]['name']) ?> (Rp <?= $rp($topBasket[0]['basket']) ?> per invoice) — produk yang sering hadir pada belanja besar.
    Artikel dengan <b>NoI tertinggi</b> adalah <b>most wanted item</b> (paling dicari pelanggan): jaga ketersediaan stoknya dan manfaatkan sebagai pemicu kunjungan; pasangkan dengan promo/bundling untuk menaikkan buying power.
</div>
<?php endif ?>
<div class="note">
    <b>Definisi.</b> NoI = jumlah invoice (transaksi berstatus Diposting; void tidak dihitung). Buying power / basket size = Sales ÷ NoI. NoI by article = jumlah invoice yang memuat artikel itu (NoI tinggi = most wanted item). Penetrasi = NoI artikel ÷ NoI total.
    Basket size produk = rata-rata total belanja pada invoice yang berisi produk itu. Sales = omzet kotor sebelum retur. Qty gratis berasal dari promo/BOGO atau pemberian manual.
</div>

<div class="sign">
    <div>Dibuat oleh<div class="line"><?= esc(session()->get('full_name')) ?></div></div>
    <div>Diperiksa oleh<div class="line">&nbsp;</div></div>
    <div>Disetujui oleh<div class="line">&nbsp;</div></div>
</div>
</body>
</html>
