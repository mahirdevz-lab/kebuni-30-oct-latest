<?php

namespace App\Controllers;

use App\Services\PromoService;

/** Promo terjadwal: BOGO otomatis & harga bertingkat per jumlah. */
class PosPromoController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        return view('pos/promos', [
            'title' => 'Promo Terjadwal', 'breadcrumbs' => [['label' => 'POS'], ['label' => 'Promo Terjadwal']],
            'promos' => $db->table('pos_promos pr')->select('pr.*, p.product_name, p.unit, u.full_name AS user_name')
                ->join('products p', 'p.id = pr.product_id')->join('users u', 'u.id = pr.created_by', 'left')->orderBy('pr.id', 'DESC')->limit(200)->get()->getResultArray(),
            'products' => $db->table('products')->select('id, product_code, product_name, unit')->where('deleted_at', null)->where('is_pos', 1)->where('status', 'aktif')->orderBy('product_name')->get()->getResultArray(),
        ]);
    }

    public function store()
    {
        $res = (new PromoService())->create($this->request->getPost(), (int) session()->get('user_id'));

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    public function cancel(int $id)
    {
        $res = (new PromoService())->cancel($id);

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }
}
