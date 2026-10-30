<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Buku Stok <?= esc($from) ?> s.d. <?= esc($to) ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 16px; color: #000; background: #fff; }
        h2 { margin: 0 0 2px; font-size: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #444; padding: 3px 5px; }
        th { background: #eee; }
        .r { text-align: right; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<?php $q = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',') ?: '0'; ?>
<button class="no-print" onclick="window.print()">Cetak / Simpan PDF</button>
<h2><?= esc($store) ?> — Buku Stok</h2>
<div>Periode: <?= date('d/m/Y', strtotime($from)) ?> s.d. <?= date('d/m/Y', strtotime($to)) ?> · Dicetak <?= date('d/m/Y H:i') ?> oleh <?= esc(session()->get('full_name')) ?></div>
<table>
    <thead><tr><th>Tanggal</th><th>Produk</th><th>Lokasi</th><th class="r">Saldo awal</th><th class="r">Masuk</th><th class="r">Keluar</th><th class="r">Saldo akhir</th><th>Satuan</th><th>Referensi</th></tr></thead>
    <tbody>
    <?php if (! $book): ?><tr><td colspan="9">Tidak ada mutasi pada periode ini.</td></tr><?php endif ?>
    <?php foreach ($book as $b): ?>
        <?php if (! $b['lines']): ?>
            <tr><td>-</td><td><?= esc($b['product']['product_name']) ?></td><td>Toko</td><td class="r"><?= $q($b['opening']) ?></td><td class="r">0</td><td class="r">0</td><td class="r"><?= $q($b['closing']) ?></td><td><?= esc($b['product']['unit']) ?></td><td>-</td></tr>
        <?php endif ?>
        <?php foreach ($b['lines'] as $l): ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($l['date'])) ?></td><td><?= esc($b['product']['product_name']) ?></td><td>Toko</td>
                <td class="r"><?= $q($l['opening']) ?></td><td class="r"><?= $q($l['in']) ?></td><td class="r"><?= $q($l['out']) ?></td>
                <td class="r"><b><?= $q($l['closing']) ?></b></td><td><?= esc($b['product']['unit']) ?></td><td><?= esc($l['refs']) ?></td>
            </tr>
        <?php endforeach ?>
    <?php endforeach ?>
    </tbody>
</table>
<p>Saldo akhir periode = saldo awal periode berikutnya (dihitung dari stock ledger; koreksi hanya lewat mutasi resmi).</p>
</body>
</html>
