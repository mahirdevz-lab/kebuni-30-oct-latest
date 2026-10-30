<?php

namespace App\Services;

/** Data ringkasan untuk dashboard utama. Read-only. */
class DashboardService
{
    protected const VALID = ['Submitted', 'Approved', 'Processing', 'Completed'];

    protected function salesBetween(string $from, string $to): array
    {
        $row = db_connect()->table('sales_orders')
            ->select('COUNT(*) as orders, COALESCE(SUM(grand_total),0) as omzet, COALESCE(SUM(estimated_profit),0) as profit, COALESCE(SUM(total_qty),0) as qty', false)
            ->where('deleted_at', null)
            ->whereIn('status', self::VALID)
            ->where('order_date >=', $from)
            ->where('order_date <=', $to)
            ->get()->getRowArray();

        return array_map('floatval', $row);
    }

    public function summary(bool $canFinance, bool $canAttendance, bool $canAudit): array
    {
        $db = db_connect();

        $thisFrom = date('Y-m-01');
        $thisTo   = date('Y-m-t');
        $prevFrom = date('Y-m-01', strtotime('first day of last month'));
        $prevTo   = date('Y-m-t', strtotime('last day of last month'));

        $now  = $this->salesBetween($thisFrom, $thisTo);
        $prev = $this->salesBetween($prevFrom, $prevTo);

        $data = [
            'sales' => [
                'current'      => $now,
                'previous'     => $prev,
                'omzet_change' => $prev['omzet'] > 0 ? round((($now['omzet'] - $prev['omzet']) / $prev['omzet']) * 100, 1) : null,
                'margin'       => $now['omzet'] > 0 ? round(($now['profit'] / $now['omzet']) * 100, 2) : 0,
            ],
            'pipeline' => array_column(
                $db->table('sales_orders')->select('status, COUNT(*) as n', false)->where('deleted_at', null)
                    ->whereIn('status', ['Draft', 'Submitted', 'Approved', 'Processing'])->groupBy('status')->get()->getResultArray(),
                'n',
                'status'
            ),
            'pending' => $db->table('sales_orders so')
                ->select('so.id, so.order_no, so.order_date, so.grand_total, a.nama_account')
                ->join('accounts a', 'a.id = so.account_id')
                ->where('so.deleted_at', null)->where('so.status', 'Submitted')
                ->orderBy('so.id', 'DESC')->limit(5)->get()->getResultArray(),
            'top_customers' => $db->table('sales_orders so')
                ->select('a.nama_account, COUNT(*) as orders, SUM(so.grand_total) as omzet', false)
                ->join('accounts a', 'a.id = so.account_id')
                ->where('so.deleted_at', null)->whereIn('so.status', self::VALID)
                ->where('so.order_date >=', $thisFrom)->where('so.order_date <=', $thisTo)
                ->groupBy('so.account_id, a.nama_account')->orderBy('omzet', 'DESC')->limit(5)->get()->getResultArray(),
            'chart' => $this->sixMonthChart(),
        ];

        if ($canFinance) {
            $fin   = new FinanceService();
            $month = $fin->dashboardSummary($thisFrom, $thisTo);
            $today = date('Y-m-d');

            $overdue = static function (string $table) use ($db, $today): array {
                return array_map('floatval', $db->table($table)
                    ->select('COUNT(*) as n, COALESCE(SUM(remaining_amount),0) as total', false)
                    ->where('status !=', 'PAID')->where('due_date <', $today)->get()->getRowArray());
            };

            $data['finance'] = $month + [
                'receivable_overdue' => $overdue('receivables'),
                'payable_overdue'    => $overdue('payables'),
            ];
        }

        if ($canAttendance) {
            $data['attendance'] = (new AttendanceService())->summaryForDate();
        }

        if ($canAudit) {
            $data['activity'] = $db->table('activity_logs al')
                ->select('al.action, al.description, al.created_at, u.full_name')
                ->join('users u', 'u.id = al.user_id', 'left')
                ->orderBy('al.id', 'DESC')->limit(6)->get()->getResultArray();
        }

        return $data;
    }

    /** Omzet vs profit 6 bulan terakhir (termasuk bulan ini). */
    protected function sixMonthChart(): array
    {
        $from = date('Y-m-01', strtotime('-5 months'));

        $rows = db_connect()->table('sales_orders')
            ->select("DATE_FORMAT(order_date, '%Y-%m') as ym, SUM(grand_total) as omzet, SUM(estimated_profit) as profit", false)
            ->where('deleted_at', null)->whereIn('status', self::VALID)->where('order_date >=', $from)
            ->groupBy('ym')->get()->getResultArray();
        $byMonth = array_column($rows, null, 'ym');

        $out = ['labels' => [], 'omzet' => [], 'profit' => []];
        for ($i = 5; $i >= 0; $i--) {
            $ts  = strtotime("-{$i} months", strtotime(date('Y-m-01')));
            $key = date('Y-m', $ts);
            $out['labels'][] = date('M Y', $ts);
            $out['omzet'][]  = (float) ($byMonth[$key]['omzet'] ?? 0);
            $out['profit'][] = (float) ($byMonth[$key]['profit'] ?? 0);
        }

        return $out;
    }
}
