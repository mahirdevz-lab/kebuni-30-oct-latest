<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\DailyClosingService;
use App\Services\StockOpeningService;

/** Stok awal toko (go-live): draft hasil hitung fisik + biaya per unit -> posting mutasi OPENING. */
class StockOpeningController extends BaseController
{
    private StockOpeningService $opening;

    public function __construct()
    {
        $this->opening = new StockOpeningService();
    }

    public function index()
    {
        $db   = db_connect();
        $docs = $db->table('stock_openings o')
            ->select('o.*, u.full_name AS created_name, up.full_name AS posted_name,
                (SELECT COUNT(*) FROM stock_opening_lines l WHERE l.opening_id = o.id AND l.qty > 0) AS n_lines,
                (SELECT COALESCE(SUM(l.qty * l.unit_cost),0) FROM stock_opening_lines l WHERE l.opening_id = o.id) AS total_value', false)
            ->join('users u', 'u.id = o.created_by', 'left')->join('users up', 'up.id = o.posted_by', 'left')
            ->orderBy('o.id', 'DESC')->get()->getResultArray();
        $draft = array_values(array_filter($docs, static fn ($d) => $d['status'] === 'DRAFT'))[0] ?? null;

        return view('stock/opening_index', [
            'title' => 'Stok Awal', 'breadcrumbs' => [['label' => 'Stok'], ['label' => 'Stok Awal']],
            'docs' => $docs, 'draft' => $draft,
            'withStock' => count($this->opening->productsWithMovements((new DailyClosingService())->locationId())),
        ]);
    }

    public function create()
    {
        $at  = str_replace('T', ' ', (string) $this->request->getPost('effective_at'));
        $res = $this->opening->createDraft(strlen($at) === 16 ? $at . ':00' : $at, $this->request->getPost('notes'), (int) session()->get('user_id'));

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    public function show(int $id)
    {
        $db  = db_connect();
        $doc = $db->table('stock_openings o')->select('o.*, u.full_name AS created_name, up.full_name AS posted_name')
            ->join('users u', 'u.id = o.created_by', 'left')->join('users up', 'up.id = o.posted_by', 'left')
            ->where('o.id', $id)->get()->getRowArray();
        if (! $doc) {
            return redirect()->to(base_url('admin/stock/opening'))->with('error', 'Dokumen tidak ditemukan.');
        }
        $lines = $db->table('stock_opening_lines l')->select('l.*, p.product_code, p.product_name, p.unit')
            ->join('products p', 'p.id = l.product_id')->where('l.opening_id', $id)->orderBy('p.product_name')->get()->getResultArray();

        $products = [];
        if ($doc['status'] === 'DRAFT') {
            $moved = array_flip($this->opening->productsWithMovements((int) $doc['location_id']));
            foreach ($db->table('products')->select('id, product_code, barcode, product_name, unit, is_pos')->where('deleted_at', null)->where('status', 'aktif')->orderBy('product_name')->get()->getResultArray() as $p) {
                $c = $this->opening->defaultCost((int) $p['id']);
                $products[] = $p + ['default_cost' => $c['cost'], 'cost_source' => $c['source'], 'has_stock' => isset($moved[(int) $p['id']])];
            }
        }

        return view('stock/opening_form', [
            'title' => 'Stok Awal ' . $doc['opening_no'],
            'breadcrumbs' => [['label' => 'Stok'], ['label' => 'Stok Awal', 'url' => base_url('admin/stock/opening')], ['label' => $doc['opening_no']]],
            'doc' => $doc, 'lines' => $lines, 'products' => $products,
            'canPost' => (new AuthService())->hasPermission('stock.opening_post'),
        ]);
    }

    public function save(int $id)
    {
        $p = $this->request->getJSON(true);
        if (! is_array($p)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Permintaan tidak valid.']);
        }
        $at  = isset($p['effective_at']) ? str_replace('T', ' ', (string) $p['effective_at']) : null;
        $res = $this->opening->saveLines($id, $p['lines'] ?? [], $at !== null && strlen($at) === 16 ? $at . ':00' : $at, $p['notes'] ?? null);

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    public function post(int $id)
    {
        $res = $this->opening->post($id, (int) session()->get('user_id'));

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    public function cancel(int $id)
    {
        $res = $this->opening->cancel($id);

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }
}
