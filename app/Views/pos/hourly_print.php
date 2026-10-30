<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hourly Sales <?= esc($from) ?> s.d. <?= esc($to) ?></title>
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
        .kpi.main { background: #ecfdf5; border-color: #16a34a; } .kpi.main .v { color: #166534; } .kpi.gold { background: #fffbeb; border-color: #f59e0b; } .kpi.gold .v { color: #b45309; }
        h3 { font-size: 12.5px; margin: 14px 0 6px; color: #166534; border-left: 4px solid #16a34a; padding-left: 7px; page-break-after: avoid; }
        ul.ins { margin: 0; padding-left: 18px; } ul.ins li { margin: 3px 0; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; } tr { page-break-inside: avoid; }
        th { background: #166534; color: #fff; font-weight: 600; text-align: left; padding: 5px 6px; font-size: 10px; }
        td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; } tbody tr:nth-child(even) td { background: #f9fafb; }
        .r { text-align: right; font-variant-numeric: tabular-nums; } .c { text-align: center; } .muted { color: #6b7280; } .strong { font-weight: 700; }
        tr.gold td { background: #fffbeb !important; font-weight: 700; }
        table.heat th, table.heat td { text-align: center; padding: 3px 1px; font-size: 9.5px; border: 1px solid #e5e7eb; } table.heat td.d { text-align: left; padding-left: 5px; font-weight: 600; white-space: nowrap; background: #fff !important; }
        .note { margin-top: 10px; padding: 8px 10px; background: #f3f4f6; border-radius: 6px; font-size: 9.5px; color: #4b5563; }
        .sign { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; margin-top: 26px; text-align: center; font-size: 10px; page-break-inside: avoid; }
        .sign .line { margin-top: 44px; border-top: 1px solid #6b7280; padding-top: 3px; }
        @media print { .toolbar { display: none; } body { padding: 0; } .pb { page-break-before: always; } }
    </style>
</head>
<body>
<?php
$rp  = static fn ($v) => number_format((float) $v, 0, ',', '.');
$q   = static fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',') ?: '0';
$t   = $r['totals'];
$ins = $r['insights'];
$lbl = static fn (int $h) => sprintf('%02d.00–%02d.00', $h, ($h + 1) % 24);
$dayNames = \App\Services\PosHourlySalesService::DAYS;
$dayLabel = match (true) {
    $day === 'WEEKDAY' => 'Hari kerja (Sen–Jum)', $day === 'WEEKEND' => 'Akhir pekan (Sab–Min)',
    ctype_digit($day) => 'Hari ' . $dayNames[(int) $day], default => 'Semua hari',
};
$act = array_values(array_filter($r['hours'], static fn ($h) => $h['noi'] > 0));
$h0  = $act ? max(0, $act[0]['hour'] - 1) : 6;
$h1  = $act ? min(23, end($act)['hour'] + 1) : 22;
$hrs = array_values(array_filter($r['hours'], static fn ($x) => $x['hour'] >= $h0 && $x['hour'] <= $h1));
?>
<div class="toolbar"><button onclick="window.print()">Cetak / Simpan PDF</button> <span class="muted">Pilih "Save as PDF" pada pilihan printer. Orientasi landscape sudah diatur otomatis.</span></div>

<header>
    <div class="brand">
        <img src="<?= base_url('kebuni.png') ?>" alt="" onerror="this.style.display='none'">
        <div><div class="store"><?= esc($store) ?></div><div class="doc">Hourly Sales Report — Golden Time &amp; Karakter Belanja</div></div>
    </div>
    <div class="meta">
        Periode <b><?= date('d/m/Y', strtotime($from)) ?> – <?= date('d/m/Y', strtotime($to)) ?></b> · <?= esc($dayLabel) ?><br>
        Dicetak <?= date('d/m/Y H:i') ?> oleh <?= esc(session()->get('full_name')) ?>
    </div>
</header>

<div class="kpis">
    <div class="kpi"><div class="l">NoI (Number of Invoice)</div><div class="v"><?= $rp($t['noi']) ?></div><div class="s"><?= $t['active_days'] ?> hari ada transaksi</div></div>
    <div class="kpi"><div class="l">Sales</div><div class="v">Rp <?= $rp($t['sales']) ?></div><div class="s">omzet kotor, sebelum retur</div></div>
    <div class="kpi main"><div class="l">Buying power (Sales ÷ NoI)</div><div class="v">Rp <?= $rp($t['buying_power']) ?></div><div class="s">rata-rata belanja per invoice</div></div>
    <div class="kpi gold"><div class="l">Golden time</div><div class="v"><?= $ins['golden_hour'] !== null ? $lbl($ins['golden_hour']) : '-' ?></div><div class="s"><?= $ins['golden_window'] ? 'window 3 jam: ' . sprintf('%02d.00–%02d.00', $ins['golden_window']['start'], $ins['golden_window']['start'] + 3) . ' (' . $q($ins['golden_window']['share']) . '% sales)' : '' ?></div></div>
</div>

<h3>Kesimpulan</h3>
<ul class="ins"><?php foreach ($ins['lines'] as $l): ?><li><?= esc($l) ?></li><?php endforeach ?></ul>

<?php if ($t['noi']): ?>
<h3>Sales &amp; NoI per jam</h3>
<?php
$n = count($hrs); $W = 1000; $H = 170; $padB = 30; $bw = $W / $n;
$maxS = max(1, ...array_column($hrs, 'sales')); $maxN = max(1, ...array_column($hrs, 'noi'));
$pts = [];
?>
<svg viewBox="0 0 <?= $W ?> <?= $H ?>" width="100%" style="max-height:200px" role="img" aria-label="Sales per jam">
    <?php foreach ($hrs as $i => $x): $h = ($H - $padB - 18) * $x['sales'] / $maxS; $cx = $i * $bw + $bw / 2; $isG = $x['hour'] === $ins['golden_hour'];
        $pts[] = round($cx, 1) . ',' . round(($H - $padB) - ($H - $padB - 18) * $x['noi'] / $maxN, 1); ?>
        <rect x="<?= round($i * $bw + $bw * 0.15, 1) ?>" y="<?= round($H - $padB - $h, 1) ?>" width="<?= round($bw * 0.7, 1) ?>" height="<?= round($h, 1) ?>" fill="<?= $isG ? '#f59e0b' : '#16a34a' ?>" rx="2"/>
        <?php if ($x['sales'] > 0): ?><text x="<?= round($cx, 1) ?>" y="<?= round($H - $padB - $h - 3, 1) ?>" font-size="9" text-anchor="middle" fill="#374151"><?= $rp($x['sales'] / 1000) ?>k</text><?php endif ?>
        <text x="<?= round($cx, 1) ?>" y="<?= $H - 16 ?>" font-size="9.5" text-anchor="middle" fill="#6b7280"><?= sprintf('%02d', $x['hour']) ?></text>
        <text x="<?= round($cx, 1) ?>" y="<?= $H - 4 ?>" font-size="8" text-anchor="middle" fill="#2563eb"><?= $x['noi'] ?: '' ?></text>
    <?php endforeach ?>
    <polyline points="<?= implode(' ', $pts) ?>" fill="none" stroke="#2563eb" stroke-width="2"/>
    <line x1="0" y1="<?= $H - $padB ?>" x2="<?= $W ?>" y2="<?= $H - $padB ?>" stroke="#9ca3af"/>
</svg>
<div class="muted" style="font-size:9.5px">Batang = sales per jam (oranye = golden time); garis biru &amp; angka biru di bawah = NoI (jumlah invoice).</div>

<h3>Heatmap hari × jam <span class="muted" style="font-weight:400">(makin pekat = sales makin besar; angka = NoI)</span></h3>
<table class="heat">
    <thead><tr><th></th><?php for ($h = $h0; $h <= $h1; $h++): ?><th><?= sprintf('%02d', $h) ?></th><?php endfor ?></tr></thead>
    <tbody>
    <?php foreach ($dayNames as $d => $name): ?>
        <tr><td class="d"><?= $name ?></td>
        <?php for ($h = $h0; $h <= $h1; $h++): $c = $r['heat'][$d][$h]; $a = $r['heat_max'] > 0 ? $c['sales'] / $r['heat_max'] : 0; ?>
            <td style="background: rgba(22,163,74,<?= round(0.04 + $a * 0.88, 2) ?>); <?= $a > 0.55 ? 'color:#fff;font-weight:700;' : '' ?>"><?= $c['noi'] ?: '' ?></td>
        <?php endfor ?>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>

<h3 class="pb">Karakter belanja per bagian hari</h3>
<table>
    <thead><tr><th>Bagian hari</th><th class="r">NoI</th><th class="r">Sales</th><th class="r">% sales</th><th class="r">Buying power</th><th class="r">Qty / invoice</th><th>Artikel paling dicari (NoI)</th></tr></thead>
    <tbody>
    <?php foreach ($r['dayparts'] as $d): ?>
        <tr><td><span class="strong"><?= $d['label'] ?></span> <span class="muted"><?= $d['range'] ?></span></td><td class="r"><?= $rp($d['noi']) ?></td><td class="r"><?= $rp($d['sales']) ?></td><td class="r"><?= $q($d['share']) ?>%</td>
            <td class="r"><?= $d['noi'] ? $rp($d['buying_power']) : '–' ?></td><td class="r"><?= $d['noi'] ? $q($d['qty_per_invoice']) : '–' ?></td>
            <td><?= $d['top'] ? esc(implode(', ', array_map(static fn ($p) => $p['name'] . ' (' . $p['noi'] . '×)', $d['top']))) : '–' ?></td></tr>
    <?php endforeach ?>
    </tbody>
</table>

<h3>Detail per jam</h3>
<table>
    <thead><tr><th>Jam</th><th class="r">NoI</th><th class="r">Rata-rata NoI / hari aktif</th><th class="r">Sales</th><th class="r">% sales</th><th class="r">Buying power</th><th class="r">Total qty</th></tr></thead>
    <tbody>
    <?php foreach ($hrs as $x): ?>
        <tr class="<?= $x['hour'] === $ins['golden_hour'] ? 'gold' : '' ?>"><td><?= $lbl($x['hour']) ?><?= $x['hour'] === $ins['golden_hour'] ? ' ★ golden' : '' ?></td><td class="r"><?= $x['noi'] ?></td><td class="r"><?= $q($x['avg_noi_day']) ?></td>
            <td class="r"><?= $rp($x['sales']) ?></td><td class="r"><?= $q($x['share']) ?>%</td><td class="r"><?= $x['noi'] ? $rp($x['buying_power']) : '–' ?></td><td class="r"><?= $q($x['qty']) ?></td></tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>

<div class="note">
    <b>Cara membaca.</b> Golden time = jam dengan sales tertinggi; golden window = 3 jam berurutan terkuat — pastikan stok artikel laris, kasir, dan promo siap pada jam ini. Jam sepi cocok untuk restock/rapi rak.
    Buying power per jam &gt; rata-rata menandakan belanja besar; ramai namun buying power rendah menandakan belanja cepat/kecil. Jam diambil dari waktu transaksi; Sales = omzet kotor transaksi Diposting (void tidak dihitung, retur belum dikurangkan).
</div>
<div class="sign">
    <div>Dibuat oleh<div class="line"><?= esc(session()->get('full_name')) ?></div></div>
    <div>Diperiksa oleh<div class="line">&nbsp;</div></div>
    <div>Disetujui oleh<div class="line">&nbsp;</div></div>
</div>
</body>
</html>
