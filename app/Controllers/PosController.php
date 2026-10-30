<?php

namespace App\Controllers;

use App\Models\SettingModel;
use App\Services\AuditLogService;
use App\Services\AuthService;
use App\Services\PosPricingService;
use App\Services\PosSaleService;

/**
 * POS Kebuni Mart: checkout, pencarian produk/pelanggan, daftar penjualan, struk, void, verifikasi QRIS.
 * Hak akses ditegakkan di route (RoleFilter) dan diulang di service untuk aksi sensitif.
 */
class PosController extends BaseController
{
    private PosSaleService $sales;
    private PosPricingService $pricing;
    private AuthService $auth;

    public function __construct()
    {
        $this->sales   = new PosSaleService();
        $this->pricing = new PosPricingService();
        $this->auth    = new AuthService();
    }

    public function index()
    {
        return view('pos/checkout', [
            'title'       => 'Kasir POS',
            'breadcrumbs' => [['label' => 'POS'], ['label' => 'Kasir']],
            'canOverride' => $this->auth->hasPermission('pos.override'),
            'canDiscount' => $this->auth->hasPermission('pos.discount'),
            'supervisors' => (new \App\Services\SupervisorAuthService())->eligible(),
        ]);
    }

    /** POST pos/verify-supervisor -> cek PIN lebih awal di layar kasir (verifikasi final tetap saat checkout). */
    public function verifySupervisor()
    {
        $ability = $this->request->getPost('ability') === 'override' ? 'pos.override' : 'pos.discount';
        $res = (new \App\Services\SupervisorAuthService())->verify((int) $this->request->getPost('supervisor_id'), (string) $this->request->getPost('pin'), $ability, (int) session()->get('user_id'));

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON(['success' => $res['success'], 'message' => $res['message'] ?? 'Supervisor terverifikasi.', 'name' => $res['user']['full_name'] ?? null]);
    }

    public function pin()
    {
        $sa = new \App\Services\SupervisorAuthService();
        $uid = (int) session()->get('user_id');

        return view('pos/pin', [
            'title' => 'PIN Supervisor', 'breadcrumbs' => [['label' => 'POS'], ['label' => 'PIN Supervisor']],
            'hasPin' => $sa->hasPin($uid), 'supervisors' => $sa->eligible(),
        ]);
    }

    public function savePin()
    {
        $res = (new \App\Services\SupervisorAuthService())->setPin((int) session()->get('user_id'), (string) $this->request->getPost('password'), (string) $this->request->getPost('pin'));

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    /** GET pos/products?q=... -> produk POS aktif + harga efektif + stok. */
    public function products()
    {
        $q   = trim((string) $this->request->getGet('q'));
        $db  = db_connect();
        $loc = (int) (new SettingModel())->value('pos_location_id', '1');

        $b = $db->table('products p')
            ->select('p.id, p.product_code, p.barcode, p.product_name, p.unit, COALESCE(sb.qty, 0) AS stock', false)
            ->join('stock_balances sb', 'sb.product_id = p.id AND sb.location_id = ' . $loc, 'left')
            ->where('p.deleted_at', null)->where('p.status', 'aktif')->where('p.is_pos', 1);
        if ($q !== '') {
            $b->groupStart()->like('p.product_name', $q)->orLike('p.product_code', $q)->orWhere('p.barcode', $q)->groupEnd();
        }
        $rows = $b->orderBy('p.product_name')->limit(30)->get()->getResultArray();

        $promoMap = (new \App\Services\PromoService())->activeFor(array_column($rows, 'id'));
        $out = [];
        foreach ($rows as $r) {
            $price = $this->pricing->priceFor((int) $r['id']);
            if (! $price) {
                continue; // belum punya harga retail -> tidak dapat dijual
            }
            $out[] = [
                'id' => (int) $r['id'], 'code' => $r['product_code'], 'name' => $r['product_name'], 'unit' => $r['unit'],
                'stock' => (float) $r['stock'], 'price' => $price['unit_price'], 'list_price' => $price['list_price'],
                'markdown' => $price['markdown_id'] !== null,
                'promo' => [
                    'bogo' => isset($promoMap[(int) $r['id']]['BOGO']) ? ['name' => $promoMap[(int) $r['id']]['BOGO']['name'], 'buy' => (float) $promoMap[(int) $r['id']]['BOGO']['buy_qty'], 'get' => (float) $promoMap[(int) $r['id']]['BOGO']['get_qty'], 'max' => $promoMap[(int) $r['id']]['BOGO']['max_sets'] !== null ? (int) $promoMap[(int) $r['id']]['BOGO']['max_sets'] : null] : null,
                    'tier' => isset($promoMap[(int) $r['id']]['TIER']) ? ['name' => $promoMap[(int) $r['id']]['TIER']['name'], 'min' => (float) $promoMap[(int) $r['id']]['TIER']['min_qty'], 'price' => (float) $promoMap[(int) $r['id']]['TIER']['tier_price']] : null,
                ],
            ];
        }

        return $this->response->setJSON(['success' => true, 'items' => $out]);
    }

    /** GET pos/customers?q=... -> cari pelanggan berdasarkan telepon/nama. */
    public function customers()
    {
        $q = trim((string) $this->request->getGet('q'));
        if (mb_strlen($q) < 3) {
            return $this->response->setJSON(['success' => true, 'items' => []]);
        }
        $digits = preg_replace('/\D+/', '', $q);

        $b = db_connect()->table('accounts')->select('id, nama_account, nomor_telepon, customer_type')
            ->where('deleted_at', null)->where('status', 'aktif')->groupStart()->like('nama_account', $q);
        if ($digits !== '') {
            $b->orLike('nomor_telepon', $digits);
        }
        $rows = $b->groupEnd()->orderBy('nama_account')->limit(10)->get()->getResultArray();

        return $this->response->setJSON(['success' => true, 'items' => $rows]);
    }

    /** POST pos/customers/store -> pelanggan retail baru (nama & telepon wajib, tanpa duplikat telepon). */
    public function storeCustomer()
    {
        $name  = trim((string) $this->request->getPost('nama_account'));
        $phone = preg_replace('/[^\d+]/', '', (string) $this->request->getPost('nomor_telepon'));
        if (mb_strlen($name) < 2 || strlen(preg_replace('/\D/', '', $phone)) < 8) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Nama dan nomor telepon wajib diisi dengan benar.']);
        }

        $db  = db_connect();
        $dup = $db->table('accounts')->where('deleted_at', null)->where('nomor_telepon', $phone)->get()->getRowArray();
        if ($dup) {
            return $this->response->setJSON(['success' => true, 'existing' => true, 'customer' => [
                'id' => (int) $dup['id'], 'nama_account' => $dup['nama_account'], 'nomor_telepon' => $dup['nomor_telepon'],
            ], 'message' => 'Nomor ini sudah terdaftar sebagai ' . $dup['nama_account'] . '.']);
        }

        $now = date('Y-m-d H:i:s');
        $db->table('accounts')->insert([
            'nama_account' => $name, 'customer_type' => 'RETAIL', 'alamat' => trim((string) $this->request->getPost('alamat')) ?: null,
            'nomor_telepon' => $phone, 'status' => 'aktif', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $id = (int) $db->insertID();
        (new AuditLogService())->log('Tambah Pelanggan POS', 'pos', 'ACCOUNT', $id, $name);

        return $this->response->setJSON(['success' => true, 'existing' => false, 'customer' => [
            'id' => $id, 'nama_account' => $name, 'nomor_telepon' => $phone,
        ], 'message' => 'Pelanggan baru tersimpan.']);
    }

    /** POST pos/checkout (JSON). */
    public function checkout()
    {
        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Permintaan tidak valid.']);
        }

        $res = $this->sales->checkout($payload, [
            'user_id'      => (int) session()->get('user_id'),
            'can_override' => $this->auth->hasPermission('pos.override'),
            'can_discount' => $this->auth->hasPermission('pos.discount'),
        ]);

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    public function sales()
    {
        $from   = $this->request->getGet('from') ?: date('Y-m-d');
        $to     = $this->request->getGet('to') ?: date('Y-m-d');
        $status = $this->request->getGet('payment_status');
        $mine   = ! $this->auth->hasPermission('pos.void') && ! $this->auth->hasPermission('pos.verify_payment');

        $b = db_connect()->table('pos_sales s')
            ->select('s.*, a.nama_account AS customer_name, u.full_name AS cashier_name')
            ->join('accounts a', 'a.id = s.customer_id', 'left')->join('users u', 'u.id = s.cashier_id', 'left')
            ->where('s.sale_date >=', $from . ' 00:00:00')->where('s.sale_date <=', $to . ' 23:59:59');
        if ($status) {
            $b->where('s.payment_status', $status);
        }
        if ($mine) {
            $b->where('s.cashier_id', (int) session()->get('user_id')); // kasir hanya melihat transaksinya sendiri
        }

        $returns = db_connect()->query(
            "SELECT COUNT(*) c, COALESCE(SUM(return_value),0) v, COALESCE(SUM(refund_amount),0) r, COALESCE(SUM(cost_value),0) cv FROM pos_returns WHERE status = 'POSTED' AND return_date BETWEEN ? AND ?",
            [$from . ' 00:00:00', $to . ' 23:59:59']
        )->getRowArray();

        return view('pos/sales', [
            'title'       => 'Penjualan POS',
            'breadcrumbs' => [['label' => 'POS'], ['label' => 'Penjualan']],
            'returns'     => $returns,
            'sales'       => $b->orderBy('s.id', 'DESC')->limit(300)->get()->getResultArray(),
            'from' => $from, 'to' => $to, 'status' => $status,
            'canVoid'   => $this->auth->hasPermission('pos.void'),
            'canVerify' => $this->auth->hasPermission('pos.verify_payment'),
        ]);
    }

    /** GET pos/sales/report?from=&to=&format=print|csv&type=daily|detail — laporan penjualan harian (kasir hanya miliknya). */
    public function salesReport()
    {
        $valid = static fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
        $from  = $this->request->getGet('from');
        $to    = $this->request->getGet('to');
        $from  = $valid($from) ? $from : date('Y-m-d');
        $to    = $valid($to) ? $to : $from;
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }
        if (strtotime($to) - strtotime($from) > 366 * 86400) {
            return $this->response->setStatusCode(422)->setBody('Rentang laporan maksimal 1 tahun.');
        }
        $mine   = ! $this->auth->hasPermission('pos.void') && ! $this->auth->hasPermission('pos.verify_payment');
        $report = (new \App\Services\PosSalesReportService())->build($from, $to, $mine ? (int) session()->get('user_id') : null);

        if ($this->request->getGet('format') === 'csv') {
            $detail = $this->request->getGet('type') === 'detail';
            $h      = fopen('php://temp', 'w+');
            fwrite($h, "\xEF\xBB\xBF");
            if ($detail) {
                fputcsv($h, ['No', 'Waktu', 'Kasir', 'Pelanggan', 'Subtotal', 'Diskon', 'Total', 'Dibayar', 'Sisa', 'HPP', 'Status'], ';');
                foreach ($report['sales'] as $s) {
                    fputcsv($h, [$s['sale_no'], $s['sale_date'], $s['cashier'], $s['customer'], $s['subtotal'], $s['discount_total'], $s['grand_total'], $s['paid_total'], $s['balance_due'], $s['cogs_total'],
                        $s['status'] === 'VOIDED' ? 'DIBATALKAN' : $s['payment_status']], ';');
                }
            } else {
                fputcsv($h, ['Tanggal', 'Transaksi', 'Omzet kotor', 'Retur', 'Omzet bersih', 'Diskon', 'HPP bersih', 'Laba kotor', 'Margin %', 'Tunai', 'QRIS', 'Transfer', 'Sisa tagihan', 'Void', 'Nilai void'], ';');
                foreach ($report['daily'] as $d) {
                    fputcsv($h, [$d['date'], $d['count'], $d['omzet'], $d['returns'], $d['net'], $d['discount'], $d['cogs_net'], $d['profit'], $d['margin'], $d['cash'], $d['qris'], $d['transfer'], $d['due'], $d['voids'], $d['void_value']], ';');
                }
            }
            rewind($h);
            $csv = stream_get_contents($h);
            fclose($h);

            return $this->response->setHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->setHeader('Content-Disposition', 'attachment; filename="penjualan-' . ($detail ? 'rincian' : 'harian') . "-{$from}-{$to}.csv\"")->setBody($csv);
        }

        return view('pos/sales_report_print', ['report' => $report, 'from' => $from, 'to' => $to, 'mine' => $mine, 'store' => (new \App\Models\SettingModel())->value('pos_store_name', 'Kebuni Mart')]);
    }

    public function receipt(int $id)
    {
        $db   = db_connect();
        $sale = $db->table('pos_sales s')->select('s.*, a.nama_account AS customer_name, a.nomor_telepon AS customer_phone, u.full_name AS cashier_name')
            ->join('accounts a', 'a.id = s.customer_id', 'left')->join('users u', 'u.id = s.cashier_id', 'left')
            ->where('s.id', $id)->get()->getRowArray();
        if (! $sale) {
            return redirect()->to(base_url('admin/pos/sales'))->with('error', 'Penjualan tidak ditemukan.');
        }
        if (! $this->auth->hasPermission('pos.void') && ! $this->auth->hasPermission('pos.verify_payment') && (int) $sale['cashier_id'] !== (int) session()->get('user_id')) {
            return service('response')->setStatusCode(403)->setBody(view('errors/html/error_403'));
        }

        $items = $db->table('pos_sale_items i')->select('i.*, p.product_name')->join('products p', 'p.id = i.product_id')
            ->where('i.sale_id', $id)->orderBy('i.line_no')->get()->getResultArray();
        $pays  = $db->table('pos_payments')->where('sale_id', $id)->orderBy('id')->get()->getResultArray();
        $set   = new SettingModel();

        return view('pos/receipt', [
            'title' => 'Struk ' . $sale['sale_no'], 'breadcrumbs' => [['label' => 'POS'], ['label' => 'Penjualan', 'url' => base_url('admin/pos/sales')], ['label' => $sale['sale_no']]],
            'sale' => $sale, 'items' => $items, 'payments' => $pays,
            'store' => $set->value('pos_store_name', 'Kebuni Mart'), 'footer' => $set->value('pos_store_whatsapp_footer', ''),
            'whatsappUrl' => $this->whatsappUrl($sale, $items, $pays, $set),
            'canVoid' => $this->auth->hasPermission('pos.void'), 'canVerify' => $this->auth->hasPermission('pos.verify_payment'),
            'canSell' => $this->auth->hasPermission('pos.sell'),
            'canReturn' => $this->auth->hasPermission('pos.return'), 'canCorrect' => $this->auth->hasPermission('pos.payment_correct'),
            'dayOpen' => $sale['daily_closing_id'] && $db->table('pos_daily_closings')->where('id', $sale['daily_closing_id'])->where('status', 'OPEN')->countAllResults() > 0,
            'returns' => $db->table('pos_returns')->where('sale_id', $id)->where('status', 'POSTED')->orderBy('id')->get()->getResultArray(),
        ]);
    }

    /** Daftar penjualan dengan sisa tagihan pelanggan (belum lunas). */
    public function receivables()
    {
        $rows = db_connect()->query(
            "SELECT s.id, s.sale_no, s.sale_date, s.grand_total, s.paid_total, s.balance_due, s.payment_status, a.nama_account, a.nomor_telepon,
                    (SELECT COALESCE(SUM(p.amount),0) FROM pos_payments p WHERE p.sale_id = s.id AND p.status = 'PENDING_VERIFICATION') AS pending_amount
             FROM pos_sales s LEFT JOIN accounts a ON a.id = s.customer_id
             WHERE s.status = 'POSTED' AND s.balance_due > 0 ORDER BY s.sale_date"
        )->getResultArray();

        return view('pos/receivables', [
            'title' => 'Piutang Pelanggan POS', 'breadcrumbs' => [['label' => 'POS'], ['label' => 'Piutang Pelanggan']], 'rows' => $rows,
        ]);
    }

    /** POST pos/sales/(:num)/pay (JSON) -> pelunasan sisa tagihan. */
    public function payBalance(int $id)
    {
        $p = $this->request->getJSON(true);
        if (! is_array($p)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Permintaan tidak valid.']);
        }
        $res = $this->sales->addPayment($id, (string) ($p['client_uuid'] ?? ''), (string) ($p['method'] ?? ''), (float) ($p['amount'] ?? 0), $p['reference_no'] ?? null, (int) session()->get('user_id'));

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    public function void(int $id)
    {
        $res = $this->sales->void($id, trim((string) $this->request->getPost('reason')), (int) session()->get('user_id'));

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    public function verifyPayment(int $paymentId)
    {
        $approve = $this->request->getPost('decision') !== 'reject';
        $res     = $this->sales->verifyQris($paymentId, $approve, (int) session()->get('user_id'));

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    /**
     * Link wa.me dengan pesan struk terisi. Kasir tetap menekan kirim; sistem tidak mengklaim pesan terkirim.
     */
    private function whatsappUrl(array $sale, array $items, array $pays, SettingModel $set): ?string
    {
        $lines   = [];
        $lines[] = '*' . $set->value('pos_store_name', 'Kebuni Mart') . '*';
        $lines[] = 'No: ' . $sale['sale_no'];
        $lines[] = 'Waktu: ' . date('d/m/Y H:i', strtotime($sale['sale_date']));
        $lines[] = 'Kasir: ' . ($sale['cashier_name'] ?? '-');
        if (! empty($sale['customer_name'])) {
            $lines[] = 'Pelanggan: ' . $sale['customer_name'];
        }
        $lines[] = '';
        foreach ($items as $i) {
            $qty     = rtrim(rtrim(number_format((float) $i['qty'], 3, ',', '.'), '0'), ',');
            $price   = $i['line_type'] === 'PROMO_FREE' ? 'GRATIS' : 'Rp ' . number_format((float) $i['line_total'] + (float) $i['discount'], 0, ',', '.');
            $lines[] = "{$i['product_name']} {$qty} {$i['unit']} x " . number_format((float) $i['unit_price'], 0, ',', '.') . " = {$price}";
        }
        $lines[] = '';
        if ((float) $sale['discount_total'] > 0) {
            $lines[] = 'Subtotal: Rp ' . number_format((float) $sale['subtotal'], 0, ',', '.');
            $lines[] = 'Diskon: -Rp ' . number_format((float) $sale['discount_total'], 0, ',', '.');
        }
        $lines[] = '*Total: Rp ' . number_format((float) $sale['grand_total'], 0, ',', '.') . '*';
        foreach ($pays as $p) {
            if (in_array($p['status'], ['VOIDED', 'REJECTED'], true)) {
                continue;
            }
            $label   = (int) $p['is_balance'] ? 'Pelunasan ' . strtolower(\App\Services\PosSaleService::label($p['method'])) : ($p['method'] === 'CASH' ? 'Tunai diterima' : \App\Services\PosSaleService::label($p['method']));
            $amount  = $p['method'] === 'CASH' && ! (int) $p['is_balance'] ? $p['tendered'] : $p['amount'];
            $lines[] = $label . ': Rp ' . number_format((float) $amount, 0, ',', '.') . ($p['status'] === 'PENDING_VERIFICATION' ? ' (menunggu verifikasi)' : '');
        }
        if ((float) $sale['change_amount'] > 0) {
            $lines[] = 'Kembalian: Rp ' . number_format((float) $sale['change_amount'], 0, ',', '.');
        }
        if ((float) $sale['balance_due'] > 0) {
            $lines[] = 'Sisa tagihan: Rp ' . number_format((float) $sale['balance_due'], 0, ',', '.');
        }
        if ($footer = $set->value('pos_store_whatsapp_footer', '')) {
            $lines[] = '';
            $lines[] = $footer;
        }

        $phone = preg_replace('/\D+/', '', (string) ($sale['customer_phone'] ?? ''));
        if ($phone !== '') {
            $phone = str_starts_with($phone, '0') ? '62' . substr($phone, 1) : $phone;
        }

        // Tanpa nomor pelanggan: wa.me/?text= membuka WhatsApp agar kasir memilih kontak sendiri.
        return 'https://wa.me/' . $phone . '?text=' . rawurlencode(implode("\n", $lines));
    }
}
