<?php

namespace App\Controllers;

use App\Services\AuditLogService;
use App\Services\PosPricingService;

/** Katalog POS: produk mana yang dijual, barcode, stok minimum, harga retail berversi, markdown. */
class PosPriceController extends BaseController
{
    private PosPricingService $pricing;

    public function __construct()
    {
        $this->pricing = new PosPricingService();
    }

    public function index()
    {
        $search = trim((string) $this->request->getGet('search'));
        $db     = db_connect();

        $b = $db->table('products p')->select('p.id, p.product_code, p.barcode, p.product_name, p.unit, p.is_pos, p.min_stock')
            ->where('p.deleted_at', null)->where('p.status', 'aktif');
        if ($search !== '') {
            $b->groupStart()->like('p.product_name', $search)->orLike('p.product_code', $search)->orLike('p.barcode', $search)->groupEnd();
        }
        $rows = $b->orderBy('p.is_pos', 'DESC')->orderBy('p.product_name')->limit(200)->get()->getResultArray();

        foreach ($rows as &$r) {
            $price          = $this->pricing->priceFor((int) $r['id']);
            $r['price']     = $price['list_price'] ?? null;
            $r['promo']     = ($price && $price['markdown_id']) ? $price['unit_price'] : null;
            $r['markdown_id'] = $price['markdown_id'] ?? null;
        }
        unset($r);

        return view('pos/prices', [
            'title' => 'Katalog & Harga Retail', 'breadcrumbs' => [['label' => 'POS'], ['label' => 'Katalog & Harga']],
            'products' => $rows, 'search' => $search,
        ]);
    }

    /** POST pos/prices/(:num)/save */
    public function save(int $id)
    {
        $db = db_connect();
        if (! $db->table('products')->where('id', $id)->where('deleted_at', null)->countAllResults()) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Produk tidak ditemukan.']);
        }

        $barcode = trim((string) $this->request->getPost('barcode')) ?: null;
        if ($barcode && $db->table('products')->where('barcode', $barcode)->where('id !=', $id)->countAllResults()) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Barcode sudah dipakai produk lain.']);
        }
        $minStock = $this->request->getPost('min_stock');
        $isPos    = $this->request->getPost('is_pos') ? 1 : 0;
        $price    = $this->request->getPost('retail_price');

        $db->table('products')->where('id', $id)->update([
            'barcode' => $barcode, 'is_pos' => $isPos,
            'min_stock' => ($minStock === null || $minStock === '') ? null : max(0, (float) $minStock),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($price !== null && $price !== '') {
            $current = $this->pricing->priceFor($id);
            if (! $current || abs($current['list_price'] - (float) $price) > 0.004) {
                $res = $this->pricing->setRetailPrice($id, (float) $price, null, (int) session()->get('user_id'));
                if (! $res['success']) {
                    return $this->response->setStatusCode(422)->setJSON($res);
                }
            }
        }
        (new AuditLogService())->log('Ubah Katalog POS', 'pos', 'PRODUCT', $id, 'is_pos=' . $isPos);

        return $this->response->setJSON(['success' => true, 'message' => 'Tersimpan.']);
    }

    /** POST pos/prices/(:num)/markdown */
    public function markdown(int $id)
    {
        $res = $this->pricing->createMarkdown(
            $id,
            (float) $this->request->getPost('promo_price'),
            (string) $this->request->getPost('reason'),
            str_replace('T', ' ', (string) $this->request->getPost('start_at')) . ':00',
            str_replace('T', ' ', (string) $this->request->getPost('end_at')) . ':00',
            (int) session()->get('user_id')
        );

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    /** POST pos/prices/markdown/(:num)/cancel */
    public function cancelMarkdown(int $markdownId)
    {
        db_connect()->table('pos_markdowns')->where('id', $markdownId)->update(['status' => 'CANCELLED', 'updated_at' => date('Y-m-d H:i:s')]);
        (new AuditLogService())->log('Batalkan Markdown', 'pos', 'POS_MARKDOWN', $markdownId, 'Markdown dibatalkan');

        return $this->response->setJSON(['success' => true, 'message' => 'Markdown dibatalkan.']);
    }
}
