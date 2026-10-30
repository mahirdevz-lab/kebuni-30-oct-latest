<?php

namespace App\Services;

use App\Models\SettingModel;

/**
 * Peringatan stok minimum & saran pemesanan ulang untuk produk POS.
 *
 *   rata-rata jual/hari = (keluar penjualan + gratis promo - retur pelanggan) / hari pengamatan
 *   saran pesan         = max(hari_cukup x rata-rata - stok, stok_min - stok, 0)  (dibulatkan ke atas per 0,5)
 *   status              : HABIS (stok <= 0) | KRITIS (<= stok minimum) | MENIPIS (cukup < 3 hari) | AMAN
 */
class ReorderService
{
    public const URGENCY = ['HABIS' => 0, 'KRITIS' => 1, 'MENIPIS' => 2, 'AMAN' => 3];

    public function settings(): array
    {
        $s = new SettingModel();

        return ['cover' => max(1, (int) $s->value('reorder_cover_days', '7')), 'lookback' => max(1, (int) $s->value('reorder_lookback_days', '14'))];
    }

    public function build(): array
    {
        $db  = db_connect();
        $cfg = $this->settings();
        $loc = (new DailyClosingService())->locationId();
        $since = date('Y-m-d H:i:s', strtotime('-' . $cfg['lookback'] . ' days'));

        $moves = array_column($db->query(
            "SELECT product_id, SUM(CASE WHEN movement_type IN ('SALE','PROMO_FREE') THEN qty_out WHEN movement_type = 'CUSTOMER_RETURN' THEN -qty_in ELSE 0 END) AS sold
             FROM stock_ledger WHERE location_id = ? AND status = 'POSTED' AND effective_at >= ? GROUP BY product_id",
            [$loc, $since]
        )->getResultArray(), 'sold', 'product_id');

        $lastSupplier = [];
        foreach ($db->query(
            "SELECT i.product_id, s.nama_supplier, g.receipt_date FROM goods_receipt_items i
             JOIN goods_receipts g ON g.id = i.receipt_id LEFT JOIN suppliers s ON s.id = g.supplier_id
             WHERE g.status = 'POSTED' ORDER BY g.receipt_date, g.id"
        )->getResultArray() as $r) {
            $lastSupplier[(int) $r['product_id']] = $r['nama_supplier'];
        }

        $rows = [];
        foreach ($db->query(
            "SELECT p.id, p.product_code, p.product_name, p.unit, p.min_stock, COALESCE(sb.qty,0) AS qty, COALESCE(sb.avg_cost,0) AS avg_cost
             FROM products p LEFT JOIN stock_balances sb ON sb.product_id = p.id AND sb.location_id = ?
             WHERE p.deleted_at IS NULL AND p.status = 'aktif' AND (p.is_pos = 1 OR COALESCE(sb.qty,0) <> 0) ORDER BY p.product_name",
            [$loc]
        )->getResultArray() as $p) {
            $pid   = (int) $p['id'];
            $qty   = (float) $p['qty'];
            $min   = $p['min_stock'] !== null ? (float) $p['min_stock'] : null;
            $avg   = max(0.0, (float) ($moves[$pid] ?? 0)) / $cfg['lookback'];
            $days  = $avg > 0 ? $qty / $avg : null;

            if ($qty <= 0) {
                $status = 'HABIS';
            } elseif ($min !== null && $qty <= $min) {
                $status = 'KRITIS';
            } elseif ($days !== null && $days < 3) {
                $status = 'MENIPIS';
            } else {
                $status = 'AMAN';
            }

            $need = max($cfg['cover'] * $avg - $qty, $min !== null ? $min - $qty : 0, 0);
            $sug  = $need > 0 ? ceil($need * 2) / 2 : 0.0;

            $rows[] = [
                'product_id' => $pid, 'code' => $p['product_code'], 'name' => $p['product_name'], 'unit' => $p['unit'],
                'qty' => $qty, 'min_stock' => $min, 'avg_per_day' => round($avg, 3), 'days_cover' => $days === null ? null : round($days, 1),
                'status' => $status, 'suggested' => $sug, 'est_value' => round($sug * (float) $p['avg_cost'], 0),
                'supplier' => $lastSupplier[$pid] ?? null,
            ];
        }
        usort($rows, static fn ($a, $b) => [self::URGENCY[$a['status']], $a['days_cover'] ?? 9999] <=> [self::URGENCY[$b['status']], $b['days_cover'] ?? 9999]);

        return ['rows' => $rows, 'cfg' => $cfg, 'counts' => array_count_values(array_column($rows, 'status'))];
    }

    /** Teks daftar pesanan per supplier (untuk disalin / dikirim lewat WhatsApp oleh pengguna). */
    public function orderText(array $rows): string
    {
        $by = [];
        foreach ($rows as $r) {
            if ($r['suggested'] > 0) {
                $by[$r['supplier'] ?: '(supplier belum ditentukan)'][] = $r;
            }
        }
        $out = [];
        foreach ($by as $sup => $items) {
            $out[] = "*Pesanan ke {$sup}* — " . date('d/m/Y');
            foreach ($items as $i) {
                $out[] = '- ' . $i['name'] . ': ' . rtrim(rtrim(number_format($i['suggested'], 1, ',', '.'), '0'), ',') . ' ' . $i['unit'];
            }
            $out[] = '';
        }

        return trim(implode("\n", $out));
    }
}
