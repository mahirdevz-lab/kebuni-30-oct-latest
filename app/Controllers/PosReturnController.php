<?php

namespace App\Controllers;

use App\Models\SettingModel;
use App\Services\DailyClosingService;
use App\Services\PosReturnService;

/** Retur pelanggan (sebagian/penuh, termasuk setelah EOD) & koreksi metode pembayaran. */
class PosReturnController extends BaseController
{
    private PosReturnService $returns;

    public function __construct()
    {
        $this->returns = new PosReturnService();
    }

    public function index()
    {
        $from = $this->request->getGet('from') ?: date('Y-m-01');
        $to   = $this->request->getGet('to') ?: date('Y-m-d');
        $rows = db_connect()->table('pos_returns r')
            ->select('r.*, s.sale_no, s.sale_date, a.nama_account AS customer_name, u.full_name AS user_name, ca.name AS account_name')
            ->join('pos_sales s', 's.id = r.sale_id')->join('accounts a', 'a.id = s.customer_id', 'left')
            ->join('users u', 'u.id = r.created_by', 'left')->join('cash_accounts ca', 'ca.id = r.cash_account_id', 'left')
            ->where('r.return_date >=', $from . ' 00:00:00')->where('r.return_date <=', $to . ' 23:59:59')
            ->orderBy('r.id', 'DESC')->limit(300)->get()->getResultArray();

        $corrections = db_connect()->table('pos_payment_corrections c')
            ->select('c.*, s.sale_no, u.full_name AS user_name')->join('pos_sales s', 's.id = c.sale_id')->join('users u', 'u.id = c.created_by', 'left')
            ->where('c.created_at >=', $from . ' 00:00:00')->where('c.created_at <=', $to . ' 23:59:59')
            ->orderBy('c.id', 'DESC')->get()->getResultArray();

        return view('pos/returns', [
            'title' => 'Retur & Koreksi', 'breadcrumbs' => [['label' => 'POS'], ['label' => 'Retur & Koreksi']],
            'rows' => $rows, 'corrections' => $corrections, 'from' => $from, 'to' => $to, 'reasons' => PosReturnService::REASONS,
        ]);
    }

    public function form(int $saleId)
    {
        $db   = db_connect();
        $sale = $db->table('pos_sales s')->select('s.*, a.nama_account AS customer_name, d.business_date, d.status AS day_status')
            ->join('accounts a', 'a.id = s.customer_id', 'left')->join('pos_daily_closings d', 'd.id = s.daily_closing_id', 'left')
            ->where('s.id', $saleId)->get()->getRowArray();
        if (! $sale || $sale['status'] !== 'POSTED') {
            return redirect()->to(base_url('admin/pos/sales'))->with('error', 'Penjualan tidak ditemukan atau sudah dibatalkan.');
        }
        $done  = $this->returns->returnedQty($saleId);
        $items = $db->table('pos_sale_items i')->select('i.*, p.product_name, p.product_code')->join('products p', 'p.id = i.product_id')
            ->where('i.sale_id', $saleId)->orderBy('i.line_no')->get()->getResultArray();
        foreach ($items as &$i) {
            $i['returned']   = $done[$i['id']] ?? 0.0;
            $i['returnable'] = max(0, round((float) $i['qty'] - $i['returned'], 3));
            $i['unit_paid']  = $i['line_type'] === 'PROMO_FREE' || (float) $i['qty'] <= 0 ? 0 : round((float) $i['line_total'] / (float) $i['qty'], 2);
        }
        unset($i);
        $prior = (float) $db->query("SELECT COALESCE(SUM(refund_amount),0) s FROM pos_returns WHERE sale_id = ? AND status = 'POSTED'", [$saleId])->getRow('s');

        return view('pos/return_form', [
            'title' => 'Retur ' . $sale['sale_no'],
            'breadcrumbs' => [['label' => 'POS'], ['label' => 'Penjualan', 'url' => base_url('admin/pos/sales')], ['label' => $sale['sale_no'], 'url' => base_url('admin/pos/sales/' . $saleId)], ['label' => 'Retur']],
            'sale' => $sale, 'items' => $items, 'reasons' => PosReturnService::REASONS,
            'refundable' => max(0, (float) $sale['paid_total'] - $prior),
            'dayOpen' => (bool) (new DailyClosingService())->openDay(),
            'accounts' => $db->table('cash_accounts')->where('is_active', 1)->where('id !=', (int) (new SettingModel())->value('pos_cash_account', '0'))->orderBy('id')->get()->getResultArray(),
            'pendingQris' => $db->table('pos_payments')->where('sale_id', $saleId)->where('status', 'PENDING_VERIFICATION')->countAllResults(),
        ]);
    }

    public function store(int $saleId)
    {
        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Permintaan tidak valid.']);
        }
        $res = $this->returns->create($saleId, $payload, (int) session()->get('user_id'));

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    public function show(int $id)
    {
        $db = db_connect();
        $r  = $db->table('pos_returns r')
            ->select('r.*, s.sale_no, s.sale_date, s.customer_id, a.nama_account AS customer_name, a.nomor_telepon AS customer_phone, u.full_name AS user_name, ca.name AS account_name')
            ->join('pos_sales s', 's.id = r.sale_id')->join('accounts a', 'a.id = s.customer_id', 'left')
            ->join('users u', 'u.id = r.created_by', 'left')->join('cash_accounts ca', 'ca.id = r.cash_account_id', 'left')
            ->where('r.id', $id)->get()->getRowArray();
        if (! $r) {
            return redirect()->to(base_url('admin/pos/returns'))->with('error', 'Retur tidak ditemukan.');
        }
        $items = $db->table('pos_return_items ri')->select('ri.*, p.product_name, si.unit, si.line_type, b.report_no AS broken_no')
            ->join('products p', 'p.id = ri.product_id')->join('pos_sale_items si', 'si.id = ri.sale_item_id')
            ->join('stock_broken_reports b', 'b.id = ri.broken_report_id', 'left')
            ->where('ri.return_id', $id)->get()->getResultArray();
        $store = (new SettingModel())->value('pos_store_name', 'Kebuni Mart');

        return view('pos/return_show', [
            'title' => $r['return_no'],
            'breadcrumbs' => [['label' => 'POS'], ['label' => 'Retur & Koreksi', 'url' => base_url('admin/pos/returns')], ['label' => $r['return_no']]],
            'r' => $r, 'items' => $items, 'reasons' => PosReturnService::REASONS, 'store' => $store,
            'whatsappUrl' => $this->whatsappUrl($r, $items, $store),
        ]);
    }

    public function correctPayment(int $paymentId)
    {
        $res = $this->returns->correctPayment($paymentId, (string) $this->request->getPost('to_method'), (string) $this->request->getPost('reason'), (int) session()->get('user_id'));

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    /** Nota retur via WhatsApp (kasir tetap menekan kirim). */
    private function whatsappUrl(array $r, array $items, string $store): string
    {
        $fmt   = static fn ($v) => number_format((float) $v, 0, ',', '.');
        $lines = ["*{$store} — Nota Retur*", "No: {$r['return_no']}", "Dari penjualan: {$r['sale_no']}", 'Tanggal: ' . date('d/m/Y H:i', strtotime($r['return_date'])), ''];
        foreach ($items as $i) {
            $qty     = rtrim(rtrim(number_format((float) $i['qty'], 3, ',', '.'), '0'), ',');
            $lines[] = "{$i['product_name']} {$qty} {$i['unit']} = Rp " . $fmt($i['value']);
        }
        $lines[] = '';
        $lines[] = '*Nilai retur: Rp ' . $fmt($r['return_value']) . '*';
        if ((float) $r['refund_amount'] > 0) {
            $lines[] = 'Dikembalikan: Rp ' . $fmt($r['refund_amount']) . ($r['refund_method'] === 'CASH' ? ' (tunai)' : ' (transfer)');
        }
        if ((float) $r['due_reduction'] > 0) {
            $lines[] = 'Mengurangi sisa tagihan: Rp ' . $fmt($r['due_reduction']);
        }
        $phone = preg_replace('/\D+/', '', (string) ($r['customer_phone'] ?? ''));
        if ($phone !== '' && str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode(implode("\n", $lines));
    }
}
