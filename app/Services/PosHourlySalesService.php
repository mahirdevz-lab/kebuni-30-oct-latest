<?php

namespace App\Services;

/**
 * Hourly sales report: penjualan per jam untuk mencari golden time dan membaca karakter belanja konsumen.
 * Basis: transaksi berstatus Diposting (void tidak dihitung), jam dari waktu transaksi (sale_date), omzet kotor sebelum retur.
 */
class PosHourlySalesService
{
    /** Bagian hari: [kunci => [label, jam mulai, jam akhir (inklusif)]] */
    public const DAYPARTS = [
        'DINI'  => ['Dini hari', 0, 4],
        'PAGI'  => ['Pagi', 5, 10],
        'SIANG' => ['Siang', 11, 14],
        'SORE'  => ['Sore', 15, 17],
        'MALAM' => ['Malam', 18, 23],
    ];

    public const DAYS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

    /**
     * @param string $dayFilter ALL | WEEKDAY (Sen-Jum) | WEEKEND (Sab-Min) | 0..6 (0 = Senin)
     * @return array<string,mixed>
     */
    public function build(string $from, string $to, string $dayFilter = 'ALL'): array
    {
        $db  = db_connect();
        $loc = (new DailyClosingService())->locationId();
        $a   = $from . ' 00:00:00';
        $b   = $to . ' 23:59:59';

        $dow = match (true) {
            $dayFilter === 'WEEKDAY' => ' AND WEEKDAY(s.sale_date) <= 4',
            $dayFilter === 'WEEKEND' => ' AND WEEKDAY(s.sale_date) >= 5',
            ctype_digit($dayFilter) && (int) $dayFilter <= 6 => ' AND WEEKDAY(s.sale_date) = ' . (int) $dayFilter,
            default => '',
        };
        $base = "FROM pos_sales s WHERE s.status = 'POSTED' AND s.location_id = ? AND s.sale_date BETWEEN ? AND ?{$dow}";

        // ---- per jam
        $hours = [];
        for ($h = 0; $h < 24; $h++) {
            $hours[$h] = ['hour' => $h, 'noi' => 0, 'sales' => 0.0, 'buying_power' => 0.0, 'qty' => 0.0, 'share' => 0.0, 'avg_noi_day' => 0.0];
        }
        foreach ($db->query("SELECT HOUR(s.sale_date) h, COUNT(*) noi, SUM(s.grand_total) sales {$base} GROUP BY HOUR(s.sale_date)", [$loc, $a, $b])->getResultArray() as $r) {
            $hours[(int) $r['h']]['noi']   = (int) $r['noi'];
            $hours[(int) $r['h']]['sales'] = (float) $r['sales'];
        }
        foreach ($db->query("SELECT HOUR(s.sale_date) h, SUM(i.qty) q FROM pos_sale_items i JOIN pos_sales s ON s.id = i.sale_id
            WHERE i.line_type = 'NORMAL' AND s.status = 'POSTED' AND s.location_id = ? AND s.sale_date BETWEEN ? AND ?{$dow} GROUP BY HOUR(s.sale_date)", [$loc, $a, $b])->getResultArray() as $r) {
            $hours[(int) $r['h']]['qty'] = (float) $r['q'];
        }
        $activeDays = (int) $db->query("SELECT COUNT(DISTINCT DATE(s.sale_date)) c {$base}", [$loc, $a, $b])->getRow('c');
        $totNoi     = array_sum(array_column($hours, 'noi'));
        $totSales   = array_sum(array_column($hours, 'sales'));
        foreach ($hours as &$h) {
            $h['buying_power'] = $h['noi'] ? round($h['sales'] / $h['noi'], 0) : 0.0;
            $h['share']        = $totSales > 0 ? round($h['sales'] / $totSales * 100, 1) : 0.0;
            $h['avg_noi_day']  = $activeDays ? round($h['noi'] / $activeDays, 2) : 0.0;
        }
        unset($h);

        // ---- heatmap hari x jam
        $heat = [];
        for ($d = 0; $d < 7; $d++) {
            $heat[$d] = array_fill(0, 24, ['noi' => 0, 'sales' => 0.0]);
        }
        foreach ($db->query("SELECT WEEKDAY(s.sale_date) d, HOUR(s.sale_date) h, COUNT(*) noi, SUM(s.grand_total) sales {$base} GROUP BY WEEKDAY(s.sale_date), HOUR(s.sale_date)", [$loc, $a, $b])->getResultArray() as $r) {
            $heat[(int) $r['d']][(int) $r['h']] = ['noi' => (int) $r['noi'], 'sales' => (float) $r['sales']];
        }
        $heatMax = 0.0;
        foreach ($heat as $row) {
            foreach ($row as $c) {
                $heatMax = max($heatMax, $c['sales']);
            }
        }

        // ---- bagian hari + top artikel per bagian hari (berdasarkan NoI)
        $perHourProduct = $db->query("SELECT HOUR(s.sale_date) h, i.product_id pid, pr.product_name name, pr.unit, COUNT(DISTINCT s.id) noi, SUM(CASE WHEN i.line_type = 'NORMAL' THEN i.qty ELSE 0 END) qty
            FROM pos_sale_items i JOIN pos_sales s ON s.id = i.sale_id JOIN products pr ON pr.id = i.product_id
            WHERE s.status = 'POSTED' AND s.location_id = ? AND s.sale_date BETWEEN ? AND ?{$dow} GROUP BY HOUR(s.sale_date), i.product_id, pr.product_name, pr.unit", [$loc, $a, $b])->getResultArray();
        $dayparts = [];
        foreach (self::DAYPARTS as $key => [$label, $h0, $h1]) {
            $noi = 0; $sales = 0.0; $qty = 0.0;
            for ($h = $h0; $h <= $h1; $h++) {
                $noi += $hours[$h]['noi']; $sales += $hours[$h]['sales']; $qty += $hours[$h]['qty'];
            }
            $prod = [];
            foreach ($perHourProduct as $r) {
                if ((int) $r['h'] >= $h0 && (int) $r['h'] <= $h1) {
                    $p = $prod[$r['pid']] ?? ['name' => $r['name'], 'unit' => $r['unit'], 'noi' => 0, 'qty' => 0.0];
                    $prod[$r['pid']] = ['name' => $r['name'], 'unit' => $r['unit'], 'noi' => $p['noi'] + (int) $r['noi'], 'qty' => $p['qty'] + (float) $r['qty']];
                }
            }
            usort($prod, static fn ($x, $y) => [$y['noi'], $y['qty']] <=> [$x['noi'], $x['qty']]);
            $dayparts[$key] = [
                'key' => $key, 'label' => $label, 'range' => sprintf('%02d.00–%02d.59', $h0, $h1), 'noi' => $noi, 'sales' => $sales,
                'share' => $totSales > 0 ? round($sales / $totSales * 100, 1) : 0.0,
                'buying_power' => $noi ? round($sales / $noi, 0) : 0.0, 'qty_per_invoice' => $noi ? round($qty / $noi, 2) : 0.0,
                'top' => array_slice($prod, 0, 3),
            ];
        }

        return [
            'hours' => array_values($hours), 'heat' => $heat, 'heat_max' => $heatMax, 'dayparts' => array_values($dayparts),
            'totals' => ['noi' => $totNoi, 'sales' => $totSales, 'buying_power' => $totNoi ? round($totSales / $totNoi, 0) : 0.0, 'active_days' => $activeDays],
            'insights' => $this->insights($hours, array_values($dayparts), $totSales, $totNoi),
        ];
    }

    /** Kesimpulan otomatis: golden time/window, jam sepi, dan karakter belanja per bagian hari. */
    private function insights(array $hours, array $dayparts, float $totSales, int $totNoi): array
    {
        if ($totNoi === 0) {
            return ['golden_hour' => null, 'golden_window' => null, 'quiet_hour' => null, 'lines' => ['Belum ada transaksi pada periode/filter ini.']];
        }
        $label = static fn (int $h) => sprintf('%02d.00–%02d.00', $h, ($h + 1) % 24);

        $best = $hours[0];
        foreach ($hours as $h) {
            if ($h['sales'] > $best['sales']) {
                $best = $h;
            }
        }
        // golden window = 3 jam berurutan dengan sales terbesar
        $win = ['start' => 0, 'sales' => -1.0, 'noi' => 0];
        for ($s = 0; $s <= 21; $s++) {
            $sum = $hours[$s]['sales'] + $hours[$s + 1]['sales'] + $hours[$s + 2]['sales'];
            if ($sum > $win['sales']) {
                $win = ['start' => $s, 'sales' => $sum, 'noi' => $hours[$s]['noi'] + $hours[$s + 1]['noi'] + $hours[$s + 2]['noi']];
            }
        }
        $win['share'] = round($win['sales'] / $totSales * 100, 1);

        // jam sepi: dalam rentang jam operasional (jam pertama–terakhir yang pernah ada transaksi)
        $active = array_values(array_filter($hours, static fn ($h) => $h['noi'] > 0));
        $first  = $active ? $active[0]['hour'] : 0;
        $last   = $active ? end($active)['hour'] : 0;
        $quiet  = null;
        foreach ($hours as $h) {
            if ($h['hour'] >= $first && $h['hour'] <= $last && ($quiet === null || $h['sales'] < $quiet['sales'])) {
                $quiet = $h;
            }
        }
        $bpBest = $hours[0];
        foreach ($hours as $h) {
            if ($h['noi'] >= 2 && $h['buying_power'] > ($bpBest['noi'] >= 2 ? $bpBest['buying_power'] : 0)) {
                $bpBest = $h;
            }
        }

        $lines = [];
        $lines[] = sprintf('Golden time (jam dengan sales tertinggi): %s — Rp %s (%s%% dari seluruh sales, %d invoice).', $label($best['hour']), number_format($best['sales'], 0, ',', '.'), number_format($best['share'], 1, ',', '.'), $best['noi']);
        $lines[] = sprintf('Golden window (3 jam berurutan terkuat): %s – %s, menyumbang %s%% sales (%d invoice).', sprintf('%02d.00', $win['start']), sprintf('%02d.00', $win['start'] + 3), number_format($win['share'], 1, ',', '.'), $win['noi']);
        if ($quiet && $quiet['hour'] !== $best['hour']) {
            $lines[] = sprintf('Jam tersepi dalam jam operasional: %s (Rp %s, %d invoice) — cocok untuk restock, rapikan rak, atau promo penarik trafik.', $label($quiet['hour']), number_format($quiet['sales'], 0, ',', '.'), $quiet['noi']);
        }
        if ($bpBest['noi'] >= 2) {
            $lines[] = sprintf('Belanja paling besar terjadi pukul %s: buying power Rp %s per invoice (rata-rata keseluruhan Rp %s).', $label($bpBest['hour']), number_format($bpBest['buying_power'], 0, ',', '.'), number_format($totSales / $totNoi, 0, ',', '.'));
        }

        // karakter belanja per bagian hari
        $avgBp   = $totSales / $totNoi;
        $withTx  = array_values(array_filter($dayparts, static fn ($d) => $d['noi'] > 0));
        foreach ($withTx as $d) {
            if ($d['noi'] < 2) {
                continue;
            }
            $busy  = $d['share'] >= 100 / max(1, count($withTx)) * 1.15; // ramai: porsi sales jauh di atas rata-rata bagian hari
            $big   = $d['buying_power'] >= $avgBp * 1.15;
            $small = $d['buying_power'] <= $avgBp * 0.85;
            $char  = match (true) {
                $busy && $big   => 'ramai dan belanja besar (belanja bulanan/mingguan) — jaga stok & kasir siap',
                $busy && $small => 'ramai tetapi belanja kecil (beli cepat/impulsif) — pastikan antrean cepat dan barang cepat laku tersedia',
                $busy           => 'ramai dengan belanja sedang',
                $big            => 'tidak terlalu ramai tetapi belanja besar — pelanggan terencana',
                $small          => 'sepi dan belanja kecil — beli sekadar kebutuhan mendadak',
                default         => 'belanja sedang',
            };
            $top = $d['top'] ? ' Artikel yang paling dicari: ' . implode(', ', array_map(static fn ($p) => $p['name'], $d['top'])) . '.' : '';
            $lines[] = sprintf('%s (%s): %s%% sales, buying power Rp %s → %s.%s', $d['label'], $d['range'], number_format($d['share'], 1, ',', '.'), number_format($d['buying_power'], 0, ',', '.'), $char, $top);
        }

        return ['golden_hour' => $best['hour'], 'golden_window' => $win, 'quiet_hour' => $quiet ? $quiet['hour'] : null, 'lines' => $lines];
    }
}
