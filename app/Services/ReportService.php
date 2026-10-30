<?php

namespace App\Services;

use App\Models\AttendanceModel;

/**
 * Query read-only untuk modul Report (Transaksi, Absensi, Keuangan).
 * Semua method mengembalikan ['rows' => [...], 'summary' => [...]] agar
 * view HTML dan export CSV memakai data yang persis sama.
 */
class ReportService
{
    /** Order berstatus ini dianggap valid untuk omzet (Draft & Cancelled diabaikan kecuali difilter eksplisit). */
    protected const VALID_ORDER_STATUSES = ['Submitted', 'Approved', 'Processing', 'Completed'];

    public function transaction(string $from, string $to, ?string $status = null, ?int $accountId = null): array
    {
        $builder = db_connect()->table('sales_orders so')
            ->select('so.order_no, so.order_date, so.status, so.total_qty, so.total_cogs, so.grand_total, so.estimated_profit, so.commission_amount, a.nama_account, pt.name as payment_term')
            ->join('accounts a', 'a.id = so.account_id')
            ->join('payment_terms pt', 'pt.id = so.payment_term_id')
            ->where('so.deleted_at', null)
            ->where('so.order_date >=', $from)
            ->where('so.order_date <=', $to)
            ->orderBy('so.order_date', 'DESC')
            ->orderBy('so.id', 'DESC');

        if ($status) {
            $builder->where('so.status', $status);
        } else {
            $builder->whereIn('so.status', self::VALID_ORDER_STATUSES);
        }

        if ($accountId) {
            $builder->where('so.account_id', $accountId);
        }

        $rows = $builder->get()->getResultArray();

        $summary = ['orders' => count($rows), 'qty' => 0.0, 'omzet' => 0.0, 'cogs' => 0.0, 'profit' => 0.0, 'commission' => 0.0];
        foreach ($rows as $r) {
            $summary['qty']        += (float) $r['total_qty'];
            $summary['omzet']      += (float) $r['grand_total'];
            $summary['cogs']       += (float) $r['total_cogs'];
            $summary['profit']     += (float) $r['estimated_profit'];
            $summary['commission'] += (float) ($r['commission_amount'] ?? 0);
        }

        // Laba bersih = estimasi profit (sudah termasuk OPEX di COGS) dikurangi komisi sales.
        $summary['net_profit'] = $summary['profit'] - $summary['commission'];

        return ['rows' => $rows, 'summary' => $summary];
    }

    public function attendance(string $from, string $to, ?string $search = null, ?string $locationStatus = null): array
    {
        $model = new AttendanceModel();
        $rows  = $model->scopeList($search, $from, $to, null, $locationStatus)->findAll();

        $summary = ['records' => count($rows), 'check_in' => 0, 'check_out' => 0, 'late' => 0, 'outside' => 0];
        foreach ($rows as $r) {
            $r['attendance_type'] === 'CHECK_IN' ? $summary['check_in']++ : $summary['check_out']++;
            $summary['late']    += (int) $r['is_late'];
            $summary['outside'] += $r['location_status'] === 'OUTSIDE_AREA' ? 1 : 0;
        }

        return ['rows' => $rows, 'summary' => $summary, 'recap' => $model->recap($from, $to, $search)];
    }

    public function finance(string $from, string $to, ?string $type = null): array
    {
        $builder = db_connect()->table('financial_transactions ft')
            ->select('ft.transaction_date, ft.type, ft.amount, ft.description, ft.reference_type, fc.name as category_name, a.nama_account, s.nama_supplier')
            ->join('financial_categories fc', 'fc.id = ft.category_id', 'left')
            ->join('accounts a', 'a.id = ft.account_id', 'left')
            ->join('suppliers s', 's.id = ft.supplier_id', 'left')
            ->where('ft.transaction_date >=', $from)
            ->where('ft.transaction_date <=', $to)
            ->orderBy('ft.transaction_date', 'DESC')
            ->orderBy('ft.id', 'DESC');

        if ($type) {
            $builder->where('ft.type', $type);
        }

        $rows = $builder->get()->getResultArray();

        $summary = ['income' => 0.0, 'expense' => 0.0];
        foreach ($rows as $r) {
            $summary[$r['type'] === 'INCOME' ? 'income' : 'expense'] += (float) $r['amount'];
        }
        $summary['net'] = $summary['income'] - $summary['expense'];

        return [
            'rows'       => $rows,
            'summary'    => $summary,
            'receivable' => $this->aging('receivables'),
            'payable'    => $this->aging('payables'),
        ];
    }

    /** Umur piutang/hutang outstanding per bucket keterlambatan (hari lewat jatuh tempo). */
    protected function aging(string $table): array
    {
        $row = db_connect()->table($table)
            ->select("
                COALESCE(SUM(CASE WHEN due_date IS NULL OR due_date >= CURDATE() THEN remaining_amount END), 0) as current_amt,
                COALESCE(SUM(CASE WHEN due_date < CURDATE() AND DATEDIFF(CURDATE(), due_date) <= 30 THEN remaining_amount END), 0) as d1_30,
                COALESCE(SUM(CASE WHEN due_date < CURDATE() AND DATEDIFF(CURDATE(), due_date) BETWEEN 31 AND 60 THEN remaining_amount END), 0) as d31_60,
                COALESCE(SUM(CASE WHEN due_date < CURDATE() AND DATEDIFF(CURDATE(), due_date) > 60 THEN remaining_amount END), 0) as d60_plus
            ", false)
            ->where('status !=', 'PAID')
            ->get()->getRowArray();

        $row = array_map('floatval', $row ?: []);
        $row['total'] = array_sum($row);

        return $row;
    }

    public function accountOptions(): array
    {
        return db_connect()->table('accounts')->select('id, nama_account')->where('deleted_at', null)->orderBy('nama_account')->get()->getResultArray();
    }
}
