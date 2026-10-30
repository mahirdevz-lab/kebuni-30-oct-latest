<?php

namespace App\Services;

/** Laporan penjualan POS per hari: ringkasan harian, per metode bayar, per produk, dan rincian transaksi. */
class PosSalesReportService
{
    /**
     * @param int|null $cashierId bila diisi, hanya transaksi kasir tersebut
     * @return array{daily: list<array>, totals: array, products: list<array>, sales: list<array>}
     */
    public function build(string $from, string $to, ?int $cashierId = null): array
    {
        $db   = db_connect();
        $loc  = (new DailyClosingService())->locationId();
        $a    = $from . ' 00:00:00';
        $b    = $to . ' 23:59:59';
        $cash = $cashierId ? ' AND s.cashier_id = ' . (int) $cashierId : '';

        $daily = [];
        $row   = function (string $d) use (&$daily) { return $daily[$d] ??= ['date' => $d, 'count' => 0, 'omzet' => 0.0, 'discount' => 0.0, 'cogs' => 0.0, 'profit' => 0.0, 'margin' => 0.0,
            'voids' => 0, 'void_value' => 0.0, 'returns' => 0.0, 'returns_cost' => 0.0, 'net' => 0.0, 'cogs_net' => 0.0, 'cash' => 0.0, 'qris' => 0.0, 'transfer' => 0.0, 'due' => 0.0]; };

        foreach ($db->query("SELECT DATE(s.sale_date) d, COUNT(*) c, SUM(s.grand_total) omzet, SUM(s.discount_total) disc, SUM(s.cogs_total) cogs, SUM(s.balance_due) due
            FROM pos_sales s WHERE s.status = 'POSTED' AND s.location_id = ? AND s.sale_date BETWEEN ? AND ?{$cash} GROUP BY DATE(s.sale_date)", [$loc, $a, $b])->getResultArray() as $r) {
            $x = $row($r['d']);
            $daily[$r['d']] = array_merge($x, ['count' => (int) $r['c'], 'omzet' => (float) $r['omzet'], 'discount' => (float) $r['disc'], 'cogs' => (float) $r['cogs'], 'due' => (float) $r['due']]);
        }
        foreach ($db->query("SELECT DATE(s.sale_date) d, COUNT(*) c, SUM(s.grand_total) v FROM pos_sales s
            WHERE s.status = 'VOIDED' AND s.location_id = ? AND s.sale_date BETWEEN ? AND ?{$cash} GROUP BY DATE(s.sale_date)", [$loc, $a, $b])->getResultArray() as $r) {
            $daily[$r['d']] = array_merge($row($r['d']), ['voids' => (int) $r['c'], 'void_value' => (float) $r['v']]);
        }
        foreach ($db->query("SELECT DATE(p.created_at) d, COALESCE(p.corrected_method, p.method) m, SUM(p.amount) a
            FROM pos_payments p JOIN pos_sales s ON s.id = p.sale_id
            WHERE s.status = 'POSTED' AND s.location_id = ? AND s.sale_date BETWEEN ? AND ? AND p.status IN ('CONFIRMED','PENDING_VERIFICATION'){$cash}
            GROUP BY DATE(p.created_at), m", [$loc, $a, $b])->getResultArray() as $r) {
            $key = ['QRIS' => 'qris', 'TRANSFER' => 'transfer'][$r['m']] ?? 'cash';
            $x   = $row($r['d']);
            $daily[$r['d']] = array_merge($x, [$key => $x[$key] + (float) $r['a']]);
        }
        if (! $cashierId) {
            foreach ($db->query("SELECT DATE(return_date) d, SUM(return_value) v, SUM(cost_value) cv FROM pos_returns WHERE status = 'POSTED' AND location_id = ? AND return_date BETWEEN ? AND ? GROUP BY DATE(return_date)", [$loc, $a, $b])->getResultArray() as $r) {
                $daily[$r['d']] = array_merge($row($r['d']), ['returns' => (float) $r['v'], 'returns_cost' => (float) $r['cv']]);
            }
        }

        ksort($daily);
        $totals = ['count' => 0, 'omzet' => 0.0, 'discount' => 0.0, 'cogs' => 0.0, 'profit' => 0.0, 'voids' => 0, 'void_value' => 0.0, 'returns' => 0.0, 'returns_cost' => 0.0, 'cash' => 0.0, 'qris' => 0.0, 'transfer' => 0.0, 'due' => 0.0];
        foreach ($daily as &$d) {
            // Retur mengurangi penjualan (nilai yang dibayar) dan HPP (biaya pokok asli barang yang kembali), pada tanggal retur.
            $d['net']      = $d['omzet'] - $d['returns'];
            $d['cogs_net'] = $d['cogs'] - $d['returns_cost'];
            $d['profit']   = $d['net'] - $d['cogs_net'];
            $d['margin']   = $d['net'] > 0 ? round($d['profit'] / $d['net'] * 100, 1) : 0.0;
            foreach (array_keys($totals) as $k) {
                if ($k !== 'profit') {
                    $totals[$k] += $d[$k];
                }
            }
        }
        unset($d);
        $totals['net']      = $totals['omzet'] - $totals['returns'];
        $totals['cogs_net'] = $totals['cogs'] - $totals['returns_cost'];
        $totals['profit']   = $totals['net'] - $totals['cogs_net'];
        $totals['margin']   = $totals['net'] > 0 ? round($totals['profit'] / $totals['net'] * 100, 1) : 0.0;

        $products = $db->query("SELECT pr.product_name name, pr.unit, SUM(i.qty) qty, SUM(i.line_total) omzet, SUM(i.cogs) cogs
            FROM pos_sale_items i JOIN pos_sales s ON s.id = i.sale_id JOIN products pr ON pr.id = i.product_id
            WHERE s.status = 'POSTED' AND s.location_id = ? AND s.sale_date BETWEEN ? AND ?{$cash}
            GROUP BY i.product_id, pr.product_name, pr.unit ORDER BY omzet DESC", [$loc, $a, $b])->getResultArray();
        foreach ($products as &$p) {
            $p['profit'] = (float) $p['omzet'] - (float) $p['cogs'];
        }
        unset($p);

        $sales = $db->query("SELECT s.id, s.sale_no, s.sale_date, s.status, s.payment_status, s.subtotal, s.discount_total, s.grand_total, s.paid_total, s.balance_due, s.cogs_total,
                a.nama_account customer, u.full_name cashier
            FROM pos_sales s LEFT JOIN accounts a ON a.id = s.customer_id LEFT JOIN users u ON u.id = s.cashier_id
            WHERE s.location_id = ? AND s.sale_date BETWEEN ? AND ?{$cash} ORDER BY s.sale_date, s.id", [$loc, $a, $b])->getResultArray();

        return ['daily' => array_values($daily), 'totals' => $totals, 'products' => $products, 'sales' => $sales];
    }
}
