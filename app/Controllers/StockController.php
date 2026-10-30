<?php

namespace App\Controllers;

use App\Models\SettingModel;
use App\Services\GoodsReceiptService;

/** Saldo stok, penerimaan barang (+bonus supplier) dan buku stok (PRD FR-06/07/08). */
class StockController extends BaseController
{
    /** Saldo stok saat ini per produk POS. */
    public function index()
    {
        $loc  = (int) (new SettingModel())->value('pos_location_id', '1');
        $rows = db_connect()->table('products p')
            ->select('p.id, p.product_code, p.product_name, p.unit, p.min_stock, COALESCE(sb.qty,0) AS qty, COALESCE(sb.avg_cost,0) AS avg_cost, COALESCE(sb.value,0) AS value', false)
            ->join('stock_balances sb', 'sb.product_id = p.id AND sb.location_id = ' . $loc, 'left')
            ->where('p.deleted_at', null)->groupStart()->where('p.is_pos', 1)->orWhere('sb.product_id IS NOT NULL', null, false)->groupEnd()
            ->orderBy('p.product_name')->get()->getResultArray();

        return view('stock/index', [
            'title' => 'Stok Toko', 'breadcrumbs' => [['label' => 'Stok'], ['label' => 'Saldo Stok']], 'rows' => $rows,
        ]);
    }

    public function receipts()
    {
        $rows = db_connect()->table('goods_receipts r')
            ->select('r.*, s.nama_supplier, u.full_name AS user_name, (SELECT COALESCE(SUM(qty_regular),0) FROM goods_receipt_items WHERE receipt_id = r.id) AS qty_regular, (SELECT COALESCE(SUM(qty_bonus),0) FROM goods_receipt_items WHERE receipt_id = r.id) AS qty_bonus, (SELECT COALESCE(SUM(line_cost),0) FROM goods_receipt_items WHERE receipt_id = r.id) AS total_cost', false)
            ->join('suppliers s', 's.id = r.supplier_id', 'left')->join('users u', 'u.id = r.created_by', 'left')
            ->orderBy('r.id', 'DESC')->limit(200)->get()->getResultArray();

        return view('stock/receipts', [
            'title' => 'Penerimaan Barang', 'breadcrumbs' => [['label' => 'Stok'], ['label' => 'Penerimaan Barang']],
            'rows' => $rows,
            'suppliers' => db_connect()->table('suppliers')->select('id, nama_supplier')->where('deleted_at', null)->where('status', 'aktif')->orderBy('nama_supplier')->get()->getResultArray(),
            'products'  => db_connect()->table('products')->select('id, product_code, product_name, unit')->where('deleted_at', null)->where('status', 'aktif')->where('is_pos', 1)->orderBy('product_name')->get()->getResultArray(),
            'bonusPolicy' => (new SettingModel())->value('bonus_cost_policy', 'allocate'),
            'refCost' => $this->referenceCosts(),
        ]);
    }

    /** Biaya per unit pembelian POS terakhir (dari ledger) sebagai acuan kewajaran; tidak memakai Price Period. @return array<int,float> */
    private function referenceCosts(): array
    {
        $out = [];
        foreach (db_connect()->query("SELECT product_id, unit_cost FROM stock_ledger WHERE movement_type = 'PURCHASE' AND status = 'POSTED' AND unit_cost > 0 ORDER BY effective_at ASC, id ASC")->getResultArray() as $r) {
            $out[(int) $r['product_id']] = (float) $r['unit_cost'];
        }

        return $out;
    }

    /** POST stock/receipts/store (JSON) */
    public function storeReceipt()
    {
        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Permintaan tidak valid.']);
        }
        $res = (new GoodsReceiptService())->create($payload, (int) session()->get('user_id'));

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    public function receiptDetail(int $id)
    {
        $db = db_connect();
        $r  = $db->table('goods_receipts r')->select('r.*, s.nama_supplier, u.full_name AS user_name')
            ->join('suppliers s', 's.id = r.supplier_id', 'left')->join('users u', 'u.id = r.created_by', 'left')->where('r.id', $id)->get()->getRowArray();
        if (! $r) {
            return redirect()->to(base_url('admin/stock/receipts'))->with('error', 'Penerimaan tidak ditemukan.');
        }
        $items = $db->table('goods_receipt_items i')->select('i.*, p.product_code, p.product_name, p.unit')->join('products p', 'p.id = i.product_id')->where('i.receipt_id', $id)->get()->getResultArray();

        return view('stock/receipt_detail', [
            'title' => $r['receipt_no'], 'breadcrumbs' => [['label' => 'Stok'], ['label' => 'Penerimaan Barang', 'url' => base_url('admin/stock/receipts')], ['label' => $r['receipt_no']]],
            'receipt' => $r, 'items' => $items,
        ]);
    }

    /** Laporan penyusutan: known (Broken Stock) vs unknown (opname) vs fisik lebih. ?from=&to=&product_id=&print=1 */
    public function shrinkage()
    {
        $from = $this->request->getGet('from') ?: date('Y-m-01');
        $to   = $this->request->getGet('to') ?: date('Y-m-d');
        $pid  = (int) $this->request->getGet('product_id') ?: null;

        return view($this->request->getGet('print') ? 'stock/shrinkage_print' : 'stock/shrinkage', [
            'title' => 'Laporan Penyusutan', 'breadcrumbs' => [['label' => 'Stok'], ['label' => 'Laporan Penyusutan']],
            'r' => (new \App\Services\ShrinkageReportService())->build($from, $to, $pid),
            'from' => $from, 'to' => $to, 'productId' => $pid,
            'products' => db_connect()->table('products')->select('id, product_code, product_name')->where('deleted_at', null)
                ->groupStart()->where('is_pos', 1)->orWhere('id IN (SELECT DISTINCT product_id FROM stock_ledger)', null, false)->groupEnd()
                ->orderBy('product_name')->get()->getResultArray(),
            'reasons' => \App\Services\BrokenStockService::REASONS,
            'store' => (new SettingModel())->value('pos_store_name', 'Kebuni Mart'),
        ]);
    }

    /**
     * Buku stok: per produk per hari -> saldo awal, masuk, keluar, saldo akhir, referensi.
     * ?from=&to=&product_id=&print=1
     */
    public function book()
    {
        $from  = $this->request->getGet('from') ?: date('Y-m-01');
        $to    = $this->request->getGet('to') ?: date('Y-m-d');
        $pid   = (int) $this->request->getGet('product_id');
        $print = (bool) $this->request->getGet('print');
        $loc   = (int) (new SettingModel())->value('pos_location_id', '1');
        $db    = db_connect();

        $products = $db->table('products')->select('id, product_code, product_name, unit')->where('deleted_at', null)
            ->groupStart()->where('is_pos', 1)->orWhere('id IN (SELECT DISTINCT product_id FROM stock_ledger)', null, false)->groupEnd()
            ->orderBy('product_name')->get()->getResultArray();

        $selected = $pid ? array_values(array_filter($products, static fn ($p) => (int) $p['id'] === $pid)) : $products;
        $book     = [];

        foreach ($selected as $p) {
            $id      = (int) $p['id'];
            // Dijumlah dari mutasi (bukan balance_after) karena adjustment opname diposting dengan effective_at = cut-off,
            // bisa setelah ada penjualan berikutnya; penjumlahan tetap benar berapa pun urutan posting.
            $opening = (float) $db->query(
                'SELECT COALESCE(SUM(qty_in - qty_out), 0) AS q FROM stock_ledger WHERE product_id = ? AND location_id = ? AND effective_at < ?',
                [$id, $loc, $from . ' 00:00:00']
            )->getRow('q');

            $days = $db->query(
                "SELECT DATE(effective_at) AS d, SUM(qty_in) AS qin, SUM(qty_out) AS qout,
                        GROUP_CONCAT(DISTINCT CONCAT(reference_type, '#', reference_id) ORDER BY id SEPARATOR ', ') AS refs
                 FROM stock_ledger WHERE product_id = ? AND location_id = ? AND effective_at >= ? AND effective_at <= ?
                 GROUP BY DATE(effective_at) ORDER BY d",
                [$id, $loc, $from . ' 00:00:00', $to . ' 23:59:59']
            )->getResultArray();

            if (! $days && $opening == 0.0 && ! $pid) {
                continue; // produk tanpa aktivitas & saldo nol disembunyikan pada laporan semua produk
            }
            $running = $opening;
            $lines   = [];
            foreach ($days as $d) {
                $start   = $running;
                $running = round($running + (float) $d['qin'] - (float) $d['qout'], 3);
                $lines[] = ['date' => $d['d'], 'opening' => $start, 'in' => (float) $d['qin'], 'out' => (float) $d['qout'], 'closing' => $running, 'refs' => $d['refs']];
            }
            $book[] = ['product' => $p, 'opening' => $opening, 'closing' => $running, 'lines' => $lines];
        }

        $ledger = [];
        if ($pid) {
            $ledger = $db->table('stock_ledger l')->select('l.*, u.full_name AS user_name')->join('users u', 'u.id = l.user_id', 'left')
                ->where('l.product_id', $pid)->where('l.location_id', $loc)->where('l.effective_at >=', $from . ' 00:00:00')->where('l.effective_at <=', $to . ' 23:59:59')
                ->orderBy('l.id')->get()->getResultArray();
        }

        return view($print ? 'stock/book_print' : 'stock/book', [
            'title' => 'Buku Stok', 'breadcrumbs' => [['label' => 'Stok'], ['label' => 'Buku Stok']],
            'book' => $book, 'ledger' => $ledger, 'products' => $products, 'from' => $from, 'to' => $to, 'productId' => $pid,
            'store' => (new SettingModel())->value('pos_store_name', 'Kebuni Mart'),
        ]);
    }
}
