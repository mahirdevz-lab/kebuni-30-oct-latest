<?php

namespace App\Services;

use App\Models\SettingModel;

/** Ringkasan "kondisi toko hari ini" untuk pemilik/supervisor. */
class PosDashboardService
{
    public function build(): array
    {
        $db       = db_connect();
        $set      = new SettingModel();
        $closing  = new DailyClosingService();
        $loc      = $closing->locationId();
        $today    = date('Y-m-d');
        $cashAcc  = (int) $set->value('pos_cash_account', '0');
        $qrisAcc  = (int) $set->value('pos_qris_account', '0');
        $accBal   = static fn (int $id): float => (float) $db->query(
            "SELECT COALESCE(a.initial_balance,0) + COALESCE(SUM(CASE WHEN f.type = 'INCOME' THEN f.amount ELSE -f.amount END),0) b
             FROM cash_accounts a LEFT JOIN financial_transactions f ON f.cash_account_id = a.id WHERE a.id = ? GROUP BY a.id, a.initial_balance",
            [$id]
        )->getRow('b');

        $day  = $closing->openDay();
        $last = $closing->lastDay();

        $t = $db->query("SELECT COUNT(*) c, COALESCE(SUM(grand_total),0) total, COALESCE(SUM(grand_total - cogs_total),0) profit, COALESCE(SUM(discount_total),0) disc
            FROM pos_sales WHERE DATE(sale_date) = ? AND status = 'POSTED' AND location_id = ?", [$today, $loc])->getRowArray();
        $ret = $db->query("SELECT COALESCE(SUM(return_value),0) v, COALESCE(SUM(cost_value),0) c, COUNT(*) n FROM pos_returns WHERE status = 'POSTED' AND location_id = ? AND DATE(return_date) = ?", [$loc, $today])->getRowArray();
        $voids = (int) $db->query("SELECT COUNT(*) c FROM pos_sales WHERE DATE(sale_date) = ? AND status = 'VOIDED' AND location_id = ?", [$today, $loc])->getRow('c');

        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $trend[date('Y-m-d', strtotime("-{$i} days"))] = ['total' => 0.0, 'profit' => 0.0, 'count' => 0];
        }
        foreach ($db->query("SELECT DATE(sale_date) d, COUNT(*) c, SUM(grand_total) t, SUM(grand_total - cogs_total) p FROM pos_sales
            WHERE status = 'POSTED' AND location_id = ? AND sale_date >= ? GROUP BY DATE(sale_date)", [$loc, array_key_first($trend)])->getResultArray() as $r) {
            $trend[$r['d']] = ['total' => (float) $r['t'], 'profit' => (float) $r['p'], 'count' => (int) $r['c']];
        }
        // Retur mengurangi penjualan & HPP pada tanggal retur.
        foreach ($db->query("SELECT DATE(return_date) d, SUM(return_value) v, SUM(cost_value) c FROM pos_returns WHERE status = 'POSTED' AND location_id = ? AND return_date >= ? GROUP BY DATE(return_date)", [$loc, array_key_first($trend)])->getResultArray() as $r) {
            if (isset($trend[$r['d']])) {
                $trend[$r['d']]['total']  -= (float) $r['v'];
                $trend[$r['d']]['profit'] -= (float) $r['v'] - (float) $r['c'];
            }
        }

        $mix = $db->query("SELECT COALESCE(p.corrected_method, p.method) m, SUM(p.amount) a FROM pos_payments p JOIN pos_sales s ON s.id = p.sale_id
            WHERE DATE(s.sale_date) = ? AND s.status = 'POSTED' AND p.status IN ('CONFIRMED','PENDING_VERIFICATION') AND s.location_id = ? GROUP BY m", [$today, $loc])->getResultArray();

        $top = $db->query("SELECT pr.product_name name, SUM(i.qty) q, SUM(i.line_total) t FROM pos_sale_items i JOIN pos_sales s ON s.id = i.sale_id JOIN products pr ON pr.id = i.product_id
            WHERE DATE(s.sale_date) = ? AND s.status = 'POSTED' AND i.line_type = 'NORMAL' AND s.location_id = ? GROUP BY i.product_id, pr.product_name ORDER BY t DESC LIMIT 5", [$today, $loc])->getResultArray();

        $reorder = (new ReorderService())->build();
        $urgent  = array_values(array_filter($reorder['rows'], static fn ($r) => in_array($r['status'], ['HABIS', 'KRITIS', 'MENIPIS'], true)));

        $exc = (new PosExceptionService())->build($today, $today)['counts'];

        return [
            'today' => $today, 'day' => $day, 'last' => $last,
            'sales' => ['count' => (int) $t['c'], 'gross' => (float) $t['total'], 'returns' => (float) $ret['v'], 'return_count' => (int) $ret['n'],
                'total' => (float) $t['total'] - (float) $ret['v'], 'profit' => (float) $t['profit'] - ((float) $ret['v'] - (float) $ret['c']), 'discount' => (float) $t['disc'], 'voids' => $voids,
                'avg' => $t['c'] ? round((float) $t['total'] / (int) $t['c'], 0) : 0],
            'trend' => $trend, 'mix' => $mix, 'top' => $top,
            'cash_balance' => $cashAcc ? $accBal($cashAcc) : 0.0,
            'qris_clearing' => $qrisAcc ? $accBal($qrisAcc) : 0.0,
            'qris_pending' => $db->query("SELECT COUNT(*) c, COALESCE(SUM(amount),0) a FROM pos_payments WHERE status = 'PENDING_VERIFICATION'")->getRowArray(),
            'receivable' => $db->query("SELECT COUNT(*) c, COALESCE(SUM(balance_due),0) a FROM pos_sales WHERE status = 'POSTED' AND balance_due > 0")->getRowArray(),
            'stock_urgent' => array_slice($urgent, 0, 6), 'stock_counts' => $reorder['counts'],
            'broken_drafts' => (int) $db->table('stock_broken_reports')->where('status', 'DRAFT')->countAllResults(),
            'opname' => $db->table('stock_opname_sessions')->where('location_id', $loc)->whereIn('status', ['COUNTING', 'SUBMITTED'])->orderBy('id', 'DESC')->get(1)->getRowArray(),
            'exceptions' => $exc, 'exceptions_total' => array_sum($exc),
        ];
    }
}
