<?php

namespace App\Controllers;

use App\Services\SupplierReturnService;

/** Retur barang ke supplier. */
class SupplierReturnController extends BaseController
{
    public function index()
    {
        $db   = db_connect();
        $rows = $db->table('supplier_returns r')
            ->select('r.*, s.nama_supplier, g.receipt_no, u.full_name AS user_name,
                (SELECT COUNT(*) FROM supplier_return_items i WHERE i.return_id = r.id) AS n_items', false)
            ->join('suppliers s', 's.id = r.supplier_id', 'left')->join('goods_receipts g', 'g.id = r.receipt_id', 'left')->join('users u', 'u.id = r.created_by', 'left')
            ->orderBy('r.id', 'DESC')->limit(100)->get()->getResultArray();

        return view('stock/supplier_returns', [
            'title' => 'Retur ke Supplier', 'breadcrumbs' => [['label' => 'Stok'], ['label' => 'Retur ke Supplier']],
            'rows' => $rows, 'reasons' => SupplierReturnService::REASONS,
            'suppliers' => $db->table('suppliers')->select('id, nama_supplier')->where('deleted_at', null)->where('status', 'aktif')->orderBy('nama_supplier')->get()->getResultArray(),
            'receipts'  => $db->table('goods_receipts')->select('id, receipt_no, supplier_id, receipt_date')->where('status', 'POSTED')->orderBy('id', 'DESC')->limit(60)->get()->getResultArray(),
            'products'  => $db->table('products p')->select('p.id, p.product_code, p.product_name, p.unit, COALESCE(sb.qty,0) AS stock', false)
                ->join('stock_balances sb', 'sb.product_id = p.id AND sb.location_id = 1', 'left')
                ->where('p.deleted_at', null)->groupStart()->where('p.is_pos', 1)->orWhere('sb.qty >', 0)->groupEnd()->orderBy('p.product_name')->get()->getResultArray(),
        ]);
    }

    public function show(int $id)
    {
        $db  = db_connect();
        $doc = $db->table('supplier_returns r')->select('r.*, s.nama_supplier, g.receipt_no, u.full_name AS user_name')
            ->join('suppliers s', 's.id = r.supplier_id', 'left')->join('goods_receipts g', 'g.id = r.receipt_id', 'left')->join('users u', 'u.id = r.created_by', 'left')
            ->where('r.id', $id)->get()->getRowArray();
        if (! $doc) {
            return redirect()->to(base_url('admin/stock/supplier-returns'))->with('error', 'Retur tidak ditemukan.');
        }
        $items = $db->table('supplier_return_items i')->select('i.*, p.product_code, p.product_name, p.unit')->join('products p', 'p.id = i.product_id')->where('i.return_id', $id)->get()->getResultArray();

        return view('stock/supplier_return_show', [
            'title' => $doc['return_no'], 'breadcrumbs' => [['label' => 'Stok'], ['label' => 'Retur ke Supplier', 'url' => base_url('admin/stock/supplier-returns')], ['label' => $doc['return_no']]],
            'doc' => $doc, 'items' => $items, 'reasons' => SupplierReturnService::REASONS,
        ]);
    }

    public function store()
    {
        $p = $this->request->getJSON(true);
        if (! is_array($p)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Permintaan tidak valid.']);
        }
        $res = (new SupplierReturnService())->create($p, (int) session()->get('user_id'));

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }
}
