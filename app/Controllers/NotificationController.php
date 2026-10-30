<?php

namespace App\Controllers;

use App\Services\AuthService;

/** Notifikasi topbar: piutang/hutang jatuh tempo & PO menunggu approval (sesuai permission user). */
class NotificationController extends BaseController
{
    public function index()
    {
        $auth  = new AuthService();
        $db    = db_connect();
        $items = [];
        $today = date('Y-m-d');
        $soon  = date('Y-m-d', strtotime('+3 days'));

        if ($auth->hasPermission('finance.view')) {
            foreach ([
                ['receivables', 'Piutang', 'admin/finance/receivable', 'nama_account', 'accounts', 'account_id'],
                ['payables',    'Hutang',  'admin/finance/payable',    'nama_supplier', 'suppliers', 'supplier_id'],
            ] as [$table, $label, $url, , , ]) {
                $overdue = $db->table($table)->select('COUNT(*) as n, COALESCE(SUM(remaining_amount),0) as total', false)
                    ->where('status !=', 'PAID')->where('due_date <', $today)->get()->getRowArray();
                if ((int) $overdue['n'] > 0) {
                    $items[] = [
                        'tone' => 'danger', 'icon' => 'fa-triangle-exclamation', 'url' => base_url($url),
                        'title' => "{$overdue['n']} {$label} lewat jatuh tempo",
                        'text'  => 'Total sisa Rp ' . number_format((float) $overdue['total'], 0, ',', '.'),
                    ];
                }

                $due = $db->table($table)->select('COUNT(*) as n, COALESCE(SUM(remaining_amount),0) as total', false)
                    ->where('status !=', 'PAID')->where('due_date >=', $today)->where('due_date <=', $soon)->get()->getRowArray();
                if ((int) $due['n'] > 0) {
                    $items[] = [
                        'tone' => 'warning', 'icon' => 'fa-clock', 'url' => base_url($url),
                        'title' => "{$due['n']} {$label} jatuh tempo ≤ 3 hari",
                        'text'  => 'Total sisa Rp ' . number_format((float) $due['total'], 0, ',', '.'),
                    ];
                }
            }
        }

        if ($auth->hasPermission('price_period.view')) {
            $pending = $db->table('sales_orders')->where('deleted_at', null)->where('status', 'Submitted')->countAllResults();
            if ($pending > 0) {
                $items[] = [
                    'tone' => 'info', 'icon' => 'fa-file-circle-check', 'url' => base_url('admin/sales-order?status=Submitted'),
                    'title' => "{$pending} Sales Order menunggu approval",
                    'text'  => 'Klik untuk meninjau dan menyetujui.',
                ];
            }
        }

        return $this->response->setJSON(['success' => true, 'total' => count($items), 'items' => $items]);
    }
}
