<?php

namespace App\Services;

/**
 * Daftar pengecualian POS untuk supervisor: semua kejadian di luar alur normal dalam satu rentang tanggal
 * (override stok, diskon, void, pembayaran ditolak/dikoreksi, retur, selisih kas, Broken Stock).
 */
class PosExceptionService
{
    public const TYPES = [
        'OVERRIDE' => ['Override stok', 'warning'],
        'DISKON'   => ['Diskon manual', 'info'],
        'VOID'     => ['Void', 'danger'],
        'BAYAR'    => ['Pembayaran ditolak/dikoreksi', 'danger'],
        'RETUR'    => ['Retur', 'secondary'],
        'KAS'      => ['Selisih kas', 'danger'],
        'BROKEN'   => ['Broken Stock', 'warning'],
    ];

    /** @return array{rows: list<array>, counts: array<string,int>} */
    public function build(string $from, string $to, ?string $type = null): array
    {
        $db   = db_connect();
        $a    = $from . ' 00:00:00';
        $b    = $to . ' 23:59:59';
        $rows = [];
        $add  = static function (string $t, ?string $at, ?string $ref, ?string $url, string $actor, ?string $approver, float $amount, string $detail) use (&$rows) {
            $rows[] = ['type' => $t, 'at' => $at, 'ref' => $ref, 'url' => $url, 'actor' => $actor, 'approver' => $approver, 'amount' => $amount, 'detail' => $detail];
        };
        $sale = static fn ($id) => base_url('admin/pos/sales/' . (int) $id);

        foreach ($db->query("SELECT o.created_at, o.requested_qty, o.available_qty, o.reason, o.sale_id, s.sale_no, p.product_name, c.full_name AS cashier, u.full_name AS sup
            FROM pos_stock_overrides o JOIN pos_sales s ON s.id = o.sale_id JOIN products p ON p.id = o.product_id
            LEFT JOIN users c ON c.id = s.cashier_id LEFT JOIN users u ON u.id = o.supervisor_id
            WHERE o.created_at BETWEEN ? AND ?", [$a, $b])->getResultArray() as $r) {
            $add('OVERRIDE', $r['created_at'], $r['sale_no'], $sale($r['sale_id']), (string) $r['cashier'], $r['sup'], 0,
                $r['product_name'] . ': jual ' . (float) $r['requested_qty'] . ', stok tercatat ' . (float) $r['available_qty'] . ($r['reason'] ? ' · ' . $r['reason'] : ''));
        }

        foreach ($db->query("SELECT s.id, s.sale_no, s.created_at, s.discount_total, s.discount_type, s.discount_value, s.discount_reason, c.full_name AS cashier, u.full_name AS sup
            FROM pos_sales s LEFT JOIN users c ON c.id = s.cashier_id LEFT JOIN users u ON u.id = s.discount_approved_by
            WHERE s.discount_total > 0 AND s.status <> 'VOIDED' AND s.created_at BETWEEN ? AND ?", [$a, $b])->getResultArray() as $r) {
            $add('DISKON', $r['created_at'], $r['sale_no'], $sale($r['id']), (string) $r['cashier'], $r['sup'], (float) $r['discount_total'],
                ($r['discount_type'] === 'PCT' ? (float) $r['discount_value'] . '%' : 'nominal') . ($r['discount_reason'] ? ' · ' . $r['discount_reason'] : ''));
        }

        foreach ($db->query("SELECT s.id, s.sale_no, s.voided_at, s.grand_total, s.void_reason, c.full_name AS cashier, u.full_name AS who
            FROM pos_sales s LEFT JOIN users c ON c.id = s.cashier_id LEFT JOIN users u ON u.id = s.voided_by
            WHERE s.status = 'VOIDED' AND s.voided_at BETWEEN ? AND ?", [$a, $b])->getResultArray() as $r) {
            $add('VOID', $r['voided_at'], $r['sale_no'], $sale($r['id']), (string) $r['cashier'], $r['who'], (float) $r['grand_total'], (string) $r['void_reason']);
        }

        foreach ($db->query("SELECT s.id, s.sale_no, p.method, p.corrected_method, p.status, p.amount, p.verified_at, p.created_at, p.reference_no, c.full_name AS cashier, u.full_name AS who
            FROM pos_payments p JOIN pos_sales s ON s.id = p.sale_id LEFT JOIN users c ON c.id = s.cashier_id LEFT JOIN users u ON u.id = p.verified_by
            WHERE (p.status = 'REJECTED' OR p.corrected_method IS NOT NULL) AND COALESCE(p.verified_at, p.created_at) BETWEEN ? AND ?", [$a, $b])->getResultArray() as $r) {
            $add('BAYAR', $r['verified_at'] ?: $r['created_at'], $r['sale_no'], $sale($r['id']), (string) $r['cashier'], $r['who'], (float) $r['amount'],
                $r['status'] === 'REJECTED' ? $r['method'] . ' ditolak' . ($r['reference_no'] ? ' (ref ' . $r['reference_no'] . ')' : '') : 'metode dikoreksi ' . $r['method'] . ' → ' . $r['corrected_method']);
        }

        foreach ($db->query("SELECT r.id, r.return_no, r.created_at, r.return_value, r.refund_amount, r.reason_code, r.reason, u.full_name AS who
            FROM pos_returns r LEFT JOIN users u ON u.id = r.created_by WHERE r.status <> 'CANCELLED' AND r.created_at BETWEEN ? AND ?", [$a, $b])->getResultArray() as $r) {
            $add('RETUR', $r['created_at'], $r['return_no'], base_url('admin/pos/returns/' . (int) $r['id']), (string) $r['who'], null, (float) $r['return_value'],
                $r['reason_code'] . ($r['reason'] ? ' · ' . $r['reason'] : '') . ' · refund Rp ' . number_format((float) $r['refund_amount'], 0, ',', '.'));
        }

        foreach ($db->query("SELECT d.id, d.business_date, d.status, d.opening_cash, d.expected_opening_cash, d.expected_cash, d.physical_cash, d.difference, d.closed_at, d.opened_at, ou.full_name AS opener, cu.full_name AS closer
            FROM pos_daily_closings d LEFT JOIN users ou ON ou.id = d.opened_by LEFT JOIN users cu ON cu.id = d.closed_by
            WHERE d.business_date BETWEEN ? AND ?", [$from, $to])->getResultArray() as $r) {
            if ($r['expected_opening_cash'] !== null && abs((float) $r['opening_cash'] - (float) $r['expected_opening_cash']) > 0.5) {
                $add('KAS', $r['opened_at'], 'Buka ' . $r['business_date'], base_url('admin/pos/day'), (string) $r['opener'], null, (float) $r['opening_cash'] - (float) $r['expected_opening_cash'],
                    'Kas awal Rp ' . number_format((float) $r['opening_cash'], 0, ',', '.') . ' vs seharusnya Rp ' . number_format((float) $r['expected_opening_cash'], 0, ',', '.'));
            }
            if ($r['status'] === 'CLOSED' && $r['difference'] !== null && abs((float) $r['difference']) > 0.5) {
                $add('KAS', $r['closed_at'], 'Tutup ' . $r['business_date'], base_url('admin/pos/day'), (string) $r['closer'], null, (float) $r['difference'],
                    'Kas fisik Rp ' . number_format((float) $r['physical_cash'], 0, ',', '.') . ' vs seharusnya Rp ' . number_format((float) $r['expected_cash'], 0, ',', '.'));
            }
        }

        foreach ($db->query("SELECT b.id, b.report_no, b.reported_at, b.qty, b.unit, b.reason_code, b.reason, b.posted_value, b.est_value, b.status, p.product_name, r.full_name AS reporter, a.full_name AS appr
            FROM stock_broken_reports b JOIN products p ON p.id = b.product_id LEFT JOIN users r ON r.id = b.reported_by LEFT JOIN users a ON a.id = b.approved_by
            WHERE b.status IN ('POSTED','REJECTED') AND b.reported_at BETWEEN ? AND ?", [$a, $b])->getResultArray() as $r) {
            $add('BROKEN', $r['reported_at'], $r['report_no'], base_url('admin/stock/broken'), (string) $r['reporter'], $r['appr'], (float) ($r['posted_value'] ?? $r['est_value']),
                $r['product_name'] . ' ' . (float) $r['qty'] . ' ' . $r['unit'] . ' · ' . $r['reason_code'] . ($r['status'] === 'REJECTED' ? ' (ditolak)' : ''));
        }

        $counts = array_fill_keys(array_keys(self::TYPES), 0);
        foreach ($rows as $r) {
            $counts[$r['type']]++;
        }
        if ($type && isset(self::TYPES[$type])) {
            $rows = array_values(array_filter($rows, static fn ($r) => $r['type'] === $type));
        }
        usort($rows, static fn ($x, $y) => strcmp((string) $y['at'], (string) $x['at']));

        return ['rows' => $rows, 'counts' => $counts];
    }
}
