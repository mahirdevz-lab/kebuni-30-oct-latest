<?php

namespace App\Services;

use App\Models\SettingModel;

/**
 * Dampak penyusutan ke laba rugi + matriks keputusan produk (arahan owner 2026-10-23: "bisnis by data, bukan by feeling").
 *
 * Laba rugi ringkas POS (real-time dari transaksi yang diposting):
 *   Penjualan - HPP terjual - biaya barang gratis promo = Laba kotor
 *   Laba kotor - known shrinkage - unknown shrinkage + selisih lebih opname = Laba setelah penyusutan
 *
 * Matriks produk:
 *   - Fast / slow moving : klasifikasi ABC nilai penjualan; produk yang membentuk 80% penjualan pertama = fast.
 *   - High / low risk    : penyusutan / (HPP terjual + penyusutan) >= setting shrinkage_high_risk_pct.
 *   - Tren               : penjualan periode ini vs periode sebelumnya dengan panjang sama (+/-10% = stabil).
 *
 * Catatan: unknown shrinkage baru muncul setelah opname diposting, sehingga laba setelah penyusutan paling akurat
 * untuk periode yang sudah ditutup dengan opname.
 */
class ProfitShrinkageService
{
    public const TREND_BAND = 10.0; // % perubahan yang masih dianggap stabil
    public const FAST_SHARE = 80.0; // ABC: produk pembentuk 80% nilai penjualan = fast moving

    public const ACTIONS = [
        'GENJOT'      => ['Genjot', 'Tren penjualan naik dan penyusutan rendah: tambah volume order & display.', 'success'],
        'PERTAHANKAN' => ['Pertahankan', 'Laku dan penyusutan terkendali: jaga ketersediaan.', 'primary'],
        'PERBAIKI'    => ['Perbaiki handling', 'Laku tetapi penyusutan tinggi: order lebih sering dengan volume lebih kecil, perbaiki penyimpanan.', 'warning'],
        'STOK_MINIMAL' => ['Stok minimal', 'Lambat tetapi penyusutan rendah: pertahankan dengan stok minimal.', 'secondary'],
        'TURUNKAN'    => ['Turunkan volume', 'Penjualan turun atau penyusutan tinggi: kurangi volume order.', 'warning'],
        'DELETE'      => ['Kandidat delete', 'Lambat dan penyusutan tinggi / rugi setelah penyusutan: evaluasi untuk dihapus dari assortment.', 'danger'],
    ];

    public function build(string $from, string $to): array
    {
        $db    = db_connect();
        $loc   = (new DailyClosingService())->locationId();
        $start = $from . ' 00:00:00';
        $end   = $to . ' 23:59:59';
        $days  = max(1, (int) round((strtotime($to) - strtotime($from)) / 86400) + 1);
        $prevTo   = date('Y-m-d', strtotime($from . ' -1 day'));
        $prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($days - 1) . ' days'));
        $riskPct  = (float) (new SettingModel())->value('shrinkage_high_risk_pct', '5');

        // --- Penjualan per produk (hanya penjualan yang diposting; void dikecualikan)
        $salesBy = static function (string $s, string $e) use ($db, $loc): array {
            $rows = $db->query(
                "SELECT i.product_id,
                        SUM(CASE WHEN i.line_type = 'NORMAL' THEN i.line_total ELSE 0 END) AS revenue,
                        SUM(CASE WHEN i.line_type = 'NORMAL' THEN i.qty ELSE 0 END) AS qty,
                        SUM(CASE WHEN i.line_type = 'PROMO_FREE' THEN i.qty ELSE 0 END) AS promo_qty,
                        COUNT(DISTINCT s.id) AS trx
                 FROM pos_sale_items i JOIN pos_sales s ON s.id = i.sale_id
                 WHERE s.status = 'POSTED' AND s.location_id = ? AND s.sale_date BETWEEN ? AND ?
                 GROUP BY i.product_id",
                [$loc, $s, $e]
            )->getResultArray();

            return array_column($rows, null, 'product_id');
        };
        // Retur mengurangi penjualan & HPP pada tanggal retur (nilai yang dibayar dan biaya pokok asli).
        $returnsBy = static function (string $s, string $e) use ($db, $loc): array {
            $rows = $db->query(
                "SELECT ri.product_id, SUM(ri.value) AS value, SUM(ri.cost_value) AS cost,
                        SUM(CASE WHEN si.line_type = 'NORMAL' THEN ri.qty ELSE 0 END) AS qty
                 FROM pos_return_items ri JOIN pos_returns r ON r.id = ri.return_id JOIN pos_sale_items si ON si.id = ri.sale_item_id
                 WHERE r.status = 'POSTED' AND r.location_id = ? AND r.return_date BETWEEN ? AND ?
                 GROUP BY ri.product_id",
                [$loc, $s, $e]
            )->getResultArray();

            return array_column($rows, null, 'product_id');
        };
        $netOfReturns = static function (array $sales, array $returns): array {
            foreach ($returns as $pid => $r) {
                $sales[$pid] ??= ['product_id' => $pid, 'revenue' => 0, 'qty' => 0, 'promo_qty' => 0, 'trx' => 0];
                $sales[$pid]['revenue'] -= (float) $r['value'];
                $sales[$pid]['qty']     -= (float) $r['qty'];
            }

            return $sales;
        };
        $ret   = $returnsBy($start, $end);
        $sales = $netOfReturns($salesBy($start, $end), $ret);
        $prev  = $netOfReturns($salesBy($prevFrom . ' 00:00:00', $prevTo . ' 23:59:59'), $returnsBy($prevFrom . ' 00:00:00', $prevTo . ' 23:59:59'));

        // --- HPP dari ledger (biaya moving average yang di-snapshot saat jual)
        $cost = array_column($db->query(
            "SELECT product_id,
                    SUM(CASE WHEN movement_type = 'SALE' THEN total_cost ELSE 0 END) AS cogs,
                    SUM(CASE WHEN movement_type = 'PROMO_FREE' THEN total_cost ELSE 0 END) AS promo_cost
             FROM stock_ledger
             WHERE location_id = ? AND status = 'POSTED' AND movement_type IN ('SALE','PROMO_FREE') AND effective_at BETWEEN ? AND ?
             GROUP BY product_id",
            [$loc, $start, $end]
        )->getResultArray(), null, 'product_id');

        // --- Penyusutan per produk (sumber yang sama dengan laporan penyusutan)
        $shrink = (new ShrinkageReportService())->build($from, $to);
        $shrBy  = array_column($shrink['byProduct'], null, 'product_id');

        $ids = array_unique(array_merge(array_keys($sales), array_keys($cost), array_keys($shrBy), array_keys($ret)));
        $stock = [];
        if ($ids) {
            foreach ($db->table('products p')->select('p.id, p.product_code, p.product_name, p.unit, COALESCE(sb.qty,0) AS stock_qty, COALESCE(sb.value,0) AS stock_value', false)
                ->join('stock_balances sb', 'sb.product_id = p.id AND sb.location_id = ' . $loc, 'left')
                ->whereIn('p.id', $ids)->get()->getResultArray() as $p) {
                $stock[(int) $p['id']] = $p;
            }
        }

        $products = [];
        foreach ($ids as $pid) {
            $pid = (int) $pid;
            $p   = $stock[$pid] ?? null;
            if (! $p) {
                continue;
            }
            $rev     = (float) ($sales[$pid]['revenue'] ?? 0);
            $cogs    = (float) ($cost[$pid]['cogs'] ?? 0) - (float) ($ret[$pid]['cost'] ?? 0);
            $promo   = (float) ($cost[$pid]['promo_cost'] ?? 0);
            $known   = (float) ($shrBy[$pid]['known_value'] ?? 0);
            $unknown = (float) ($shrBy[$pid]['unknown_value'] ?? 0);
            $surplus = (float) ($shrBy[$pid]['surplus_value'] ?? 0);
            $prevRev = (float) ($prev[$pid]['revenue'] ?? 0);
            $outflow = $cogs + $promo + $known + $unknown;

            $products[$pid] = [
                'product_id' => $pid, 'product_code' => $p['product_code'], 'product_name' => $p['product_name'], 'unit' => $p['unit'],
                'qty' => (float) ($sales[$pid]['qty'] ?? 0), 'qty_per_day' => (float) ($sales[$pid]['qty'] ?? 0) / $days,
                'trx' => (int) ($sales[$pid]['trx'] ?? 0),
                'revenue' => $rev, 'cogs' => $cogs, 'promo_cost' => $promo, 'gross' => $rev - $cogs - $promo,
                'known' => $known, 'unknown' => $unknown, 'surplus' => $surplus, 'shrink' => $known + $unknown,
                'net' => $rev - $cogs - $promo - $known - $unknown + $surplus,
                'shrink_rate' => $outflow > 0 ? ($known + $unknown) / $outflow * 100 : 0.0,
                'prev_revenue' => $prevRev,
                'trend_pct' => $prevRev > 0 ? ($rev - $prevRev) / $prevRev * 100 : ($rev > 0 ? null : 0.0), // null = produk baru terjual
                'stock_qty' => (float) $p['stock_qty'], 'stock_value' => (float) $p['stock_value'],
            ];
        }

        // --- ABC (fast/slow) berdasarkan nilai penjualan
        uasort($products, static fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
        $totalRev = array_sum(array_column($products, 'revenue'));
        $cum      = 0.0;
        $minFast  = null;
        $maxSlow  = 0.0;
        foreach ($products as &$p) {
            $before   = $cum;
            $cum     += $p['revenue'];
            $p['share'] = $totalRev > 0 ? $p['revenue'] / $totalRev * 100 : 0;
            // Produk yang MULAI di bawah 80% kumulatif masuk kelas A (fast).
            $p['fast'] = $p['revenue'] > 0 && $totalRev > 0 && ($before / $totalRev * 100) < self::FAST_SHARE;
            if ($p['fast']) {
                $minFast = $p['revenue'];
            } else {
                $maxSlow = max($maxSlow, $p['revenue']);
            }
            $p['high_risk'] = $p['shrink_rate'] >= $riskPct && $p['shrink'] > 0;
            $p['trend']     = $p['trend_pct'] === null ? 'BARU' : ($p['trend_pct'] > self::TREND_BAND ? 'NAIK' : ($p['trend_pct'] < -self::TREND_BAND ? 'TURUN' : 'STABIL'));
            $p['action']    = $this->recommend($p);
        }
        unset($p);
        // Batas visual fast/slow di matriks: tengah antara produk fast terkecil dan produk slow terbesar.
        $fastCut = $minFast === null ? null : ($minFast + $maxSlow) / 2;

        // --- Laba rugi ringkas
        $sum = static fn (string $k) => array_sum(array_column($products, $k));
        $pl  = [
            'returns' => array_sum(array_map(static fn ($r) => (float) $r['value'], $ret)),
            'revenue' => $sum('revenue'), 'cogs' => $sum('cogs'), 'promo_cost' => $sum('promo_cost'), 'gross' => $sum('gross'),
            'known' => $shrink['totals']['known'], 'unknown' => $shrink['totals']['unknown'], 'surplus' => $shrink['totals']['surplus'],
        ];
        $pl['shrink'] = $pl['known'] + $pl['unknown'];
        $pl['net']    = $pl['gross'] - $pl['shrink'] + $pl['surplus'];

        $actionCount = array_count_values(array_column($products, 'action'));

        return [
            'from' => $from, 'to' => $to, 'days' => $days, 'prevFrom' => $prevFrom, 'prevTo' => $prevTo,
            'pl' => $pl, 'products' => array_values($products), 'riskPct' => $riskPct, 'fastCut' => $fastCut,
            'actionCount' => $actionCount, 'pendingBroken' => $shrink['pending'],
            'lastOpname' => $db->table('stock_opname_sessions')->where('location_id', $loc)->where('status', 'POSTED')
                ->orderBy('cutoff_at', 'DESC')->get(1)->getRowArray(),
        ];
    }

    private function recommend(array $p): string
    {
        if ($p['revenue'] <= 0) {
            // Tidak terjual sama sekali: barang mati bila masih ada stok atau ada penyusutan.
            return ($p['shrink'] > 0 || $p['stock_qty'] > 0) ? 'DELETE' : 'STOK_MINIMAL';
        }
        if ($p['fast']) {
            if ($p['high_risk']) {
                return 'PERBAIKI';
            }

            return $p['trend'] === 'NAIK' ? 'GENJOT' : ($p['trend'] === 'TURUN' ? 'TURUNKAN' : 'PERTAHANKAN');
        }
        if ($p['high_risk']) {
            return ($p['net'] < 0 || $p['trend'] === 'TURUN') ? 'DELETE' : 'TURUNKAN';
        }
        if ($p['trend'] === 'NAIK') {
            return 'GENJOT';
        }

        return $p['trend'] === 'TURUN' ? 'TURUNKAN' : 'STOK_MINIMAL';
    }
}
