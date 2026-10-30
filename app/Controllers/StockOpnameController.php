<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\DailyClosingService;
use App\Services\StockOpnameService;

/** Stock opname setelah EOD: buka sesi, hitung dari HP, submit, review selisih, setujui. */
class StockOpnameController extends BaseController
{
    private StockOpnameService $opname;

    public function __construct()
    {
        $this->opname = new StockOpnameService();
    }

    public function index()
    {
        $days     = new DailyClosingService();
        $sessions = db_connect()->table('stock_opname_sessions s')
            ->select("s.*, u.full_name AS opened_name,
                (SELECT COUNT(*) FROM stock_opname_lines l WHERE l.session_id = s.id) AS line_count,
                (SELECT COUNT(*) FROM stock_opname_lines l WHERE l.session_id = s.id AND l.counted_qty IS NOT NULL) AS counted_count,
                (SELECT COALESCE(SUM(CASE WHEN l.difference > 0 THEN l.diff_value END),0) FROM stock_opname_lines l WHERE l.session_id = s.id) AS shrink_value", false)
            ->join('users u', 'u.id = s.opened_by', 'left')->orderBy('s.id', 'DESC')->limit(50)->get()->getResultArray();

        return view('stock/opname_index', [
            'title' => 'Stock Opname', 'breadcrumbs' => [['label' => 'Stok'], ['label' => 'Stock Opname']],
            'sessions' => $sessions, 'storeOpen' => (bool) $days->openDay(), 'lastDay' => $days->lastDay(),
            'active' => $this->opname->activeSession($days->locationId()),
        ]);
    }

    public function open()
    {
        $res = $this->opname->open((int) session()->get('user_id'), trim((string) $this->request->getPost('notes')) ?: null);

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    /** Halaman hitung (mobile-first) atau review, tergantung status. */
    public function show(int $id)
    {
        $db   = db_connect();
        $sess = $db->table('stock_opname_sessions s')->select('s.*, uo.full_name AS opened_name, ua.full_name AS approved_name')
            ->join('users uo', 'uo.id = s.opened_by', 'left')->join('users ua', 'ua.id = s.approved_by', 'left')
            ->where('s.id', $id)->get()->getRowArray();
        if (! $sess) {
            return redirect()->to(base_url('admin/stock/opname'))->with('error', 'Sesi tidak ditemukan.');
        }
        $lines = $db->table('stock_opname_lines l')
            ->select('l.*, p.product_code, p.barcode, p.product_name, p.unit, u.full_name AS counted_name, ur.full_name AS resolved_name')
            ->join('products p', 'p.id = l.product_id')->join('users u', 'u.id = l.counted_by', 'left')->join('users ur', 'ur.id = l.resolved_by', 'left')
            ->where('l.session_id', $id)->orderBy('p.product_name')->get()->getResultArray();

        $auth = new AuthService();

        if ($sess['status'] === 'COUNTING') {
            // BLIND COUNT: view hitung tidak menerima saldo buku, selisih, maupun hasil putaran sebelumnya.
            $blind = [];
            foreach ($lines as $l) {
                $blind[] = [
                    'product_id' => $l['product_id'], 'product_code' => $l['product_code'], 'barcode' => $l['barcode'],
                    'product_name' => $l['product_name'], 'unit' => $l['unit'], 'counted_qty' => $l['counted_qty'],
                    'counted_name' => $l['counted_name'], 'note' => $l['note'], 'added_during_count' => $l['added_during_count'],
                    'countable' => StockOpnameService::isCountable($l, $sess),
                ];
            }
            $inSession = array_column($lines, 'product_id');

            return view('stock/opname_count', [
                'title' => 'Opname ' . $sess['session_no'],
                'breadcrumbs' => [['label' => 'Stok'], ['label' => 'Stock Opname', 'url' => base_url('admin/stock/opname')], ['label' => $sess['session_no']]],
                'session' => $sess, 'lines' => $blind,
                'canApprove' => $auth->hasPermission('stock.opname_approve'),
                'otherProducts' => $db->table('products')->select('id, product_code, barcode, product_name, unit')->where('deleted_at', null)->where('status', 'aktif')
                    ->whereNotIn('id', $inSession ?: [0])->orderBy('product_name')->get()->getResultArray(),
            ]);
        }

        $history = [];
        foreach ($db->table('stock_opname_counts c')->select('c.*, u.full_name')->join('users u', 'u.id = c.counted_by', 'left')
            ->where('c.session_id', $id)->orderBy('c.round')->get()->getResultArray() as $c) {
            $history[(int) $c['line_id']][] = $c;
        }
        foreach ($lines as &$l) {
            $l['state']   = StockOpnameService::lineState($l);
            $l['history'] = $history[(int) $l['id']] ?? [];
        }
        unset($l);

        return view('stock/opname_review', [
            'title' => 'Opname ' . $sess['session_no'],
            'breadcrumbs' => [['label' => 'Stok'], ['label' => 'Stock Opname', 'url' => base_url('admin/stock/opname')], ['label' => $sess['session_no']]],
            'session' => $sess, 'lines' => $lines,
            'canApprove' => $auth->hasPermission('stock.opname_approve'),
            'thresholds' => $this->opname->thresholds(),
            'reasons' => StockOpnameService::CONFIRM_REASONS,
        ]);
    }

    public function addLine(int $id)
    {
        $res = $this->opname->addLine($id, (int) $this->request->getPost('product_id'), (int) session()->get('user_id'));

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    public function confirm(int $id)
    {
        $res = $this->opname->confirmLine($id, (int) $this->request->getPost('line_id'), (string) $this->request->getPost('reason'),
            (string) $this->request->getPost('note'), (int) session()->get('user_id'));

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    public function count(int $id)
    {
        $raw = $this->request->getPost('counted_qty');
        $qty = ($raw === null || $raw === '') ? null : (float) str_replace(',', '.', (string) $raw);
        $res = $this->opname->count($id, (int) $this->request->getPost('product_id'), $qty, $this->request->getPost('note'), (int) session()->get('user_id'));

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    public function submit(int $id)
    {
        $res = $this->opname->submit($id, (int) session()->get('user_id'));

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    public function recount(int $id)
    {
        $res = $this->opname->requestRecount($id, (array) $this->request->getPost('line_ids'), (int) session()->get('user_id'));

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    public function approve(int $id)
    {
        $res = $this->opname->approve($id, (int) session()->get('user_id'));

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    public function cancel(int $id)
    {
        $res = $this->opname->cancel($id, (string) $this->request->getPost('reason'));

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }
}
