<?php

namespace App\Services;

/**
 * Buying power / basket size: rata-rata nilai belanja per invoice = Sales ÷ NOI (Number of Invoice).
 * Juga per produk: berapa kali dibeli (jumlah invoice yang memuat produk), total qty, total sales, dan basket size saat produk itu ada di keranjang.
 * Sales = omzet kotor transaksi berstatus Diposting (sebelum retur); void tidak dihitung.
 */
class PosBuyingPowerService
{
    /** @return array{overall: array, daily: list<array>, products: list<array>} */
    public function build(string $from, string $to): array
    {
        $db  = db_connect();
        $loc = (new DailyClosingService())->locationId();
        $a   = $from . ' 00:00:00';
        $b   = $to . ' 23:59:59';

        $o = $db->query("SELECT COUNT(*) noi, COALESCE(SUM(grand_total),0) sales, COALESCE(SUM(discount_total),0) disc
            FROM pos_sales WHERE status = 'POSTED' AND location_id = ? AND sale_date BETWEEN ? AND ?", [$loc, $a, $b])->getRowArray();
        $l = $db->query("SELECT COALESCE(SUM(CASE WHEN i.line_type = 'NORMAL' THEN 1 END),0) n_lines, COALESCE(SUM(CASE WHEN i.line_type = 'NORMAL' THEN i.qty END),0) qty
            FROM pos_sale_items i JOIN pos_sales s ON s.id = i.sale_id WHERE s.status = 'POSTED' AND s.location_id = ? AND s.sale_date BETWEEN ? AND ?", [$loc, $a, $b])->getRowArray();
        $noi   = (int) $o['noi'];
        $sales = (float) $o['sales'];
        $overall = [
            'noi' => $noi, 'sales' => $sales, 'discount' => (float) $o['disc'],
            'buying_power' => $noi ? round($sales / $noi, 0) : 0.0,
            'lines_per_invoice' => $noi ? round((float) $l['n_lines'] / $noi, 2) : 0.0,
            'qty_per_invoice'   => $noi ? round((float) $l['qty'] / $noi, 2) : 0.0,
        ];

        $daily = [];
        foreach ($db->query("SELECT DATE(sale_date) d, COUNT(*) noi, SUM(grand_total) sales FROM pos_sales
            WHERE status = 'POSTED' AND location_id = ? AND sale_date BETWEEN ? AND ? GROUP BY DATE(sale_date) ORDER BY d", [$loc, $a, $b])->getResultArray() as $r) {
            $daily[] = ['date' => $r['d'], 'noi' => (int) $r['noi'], 'sales' => (float) $r['sales'], 'buying_power' => round((float) $r['sales'] / max(1, (int) $r['noi']), 0)];
        }

        // Basket size per produk: rata-rata total invoice yang memuat produk tsb.
        $basket = array_column($db->query("SELECT t.product_id, AVG(t.grand_total) bp FROM (
                SELECT DISTINCT i.product_id, s.id sid, s.grand_total FROM pos_sale_items i JOIN pos_sales s ON s.id = i.sale_id
                WHERE s.status = 'POSTED' AND s.location_id = ? AND s.sale_date BETWEEN ? AND ?) t GROUP BY t.product_id", [$loc, $a, $b])->getResultArray(), 'bp', 'product_id');

        $products = [];
        foreach ($db->query("SELECT i.product_id, pr.product_code code, pr.product_name name, pr.unit, COUNT(DISTINCT s.id) noi,
                COALESCE(SUM(CASE WHEN i.line_type = 'NORMAL' THEN i.qty END),0) qty, COALESCE(SUM(CASE WHEN i.line_type = 'PROMO_FREE' THEN i.qty END),0) free_qty, COALESCE(SUM(i.line_total),0) sales
            FROM pos_sale_items i JOIN pos_sales s ON s.id = i.sale_id JOIN products pr ON pr.id = i.product_id
            WHERE s.status = 'POSTED' AND s.location_id = ? AND s.sale_date BETWEEN ? AND ?
            GROUP BY i.product_id, pr.product_code, pr.product_name, pr.unit ORDER BY noi DESC, sales DESC", [$loc, $a, $b])->getResultArray() as $r) {
            $n = (int) $r['noi'];
            $products[] = [
                'product_id' => (int) $r['product_id'], 'code' => $r['code'], 'name' => $r['name'], 'unit' => $r['unit'],
                'noi' => $n, 'penetration' => $noi ? round($n / $noi * 100, 1) : 0.0,
                'qty' => (float) $r['qty'], 'free_qty' => (float) $r['free_qty'], 'sales' => (float) $r['sales'],
                'avg_qty' => $n ? round((float) $r['qty'] / $n, 2) : 0.0, 'avg_sales' => $n ? round((float) $r['sales'] / $n, 0) : 0.0,
                'basket' => round((float) ($basket[$r['product_id']] ?? 0), 0),
            ];
        }

        return ['overall' => $overall, 'daily' => $daily, 'products' => $products];
    }
}
