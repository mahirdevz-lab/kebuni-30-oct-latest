<?php

namespace App\Controllers;

use App\Services\ReorderService;

/** Peringatan stok minimum & saran pesan ulang. */
class ReorderController extends BaseController
{
    public function index()
    {
        $svc = new ReorderService();
        $res = $svc->build();

        return view('stock/reorder', [
            'title' => 'Saran Pesan Ulang', 'breadcrumbs' => [['label' => 'Stok'], ['label' => 'Saran Pesan Ulang']],
            'rows' => $res['rows'], 'cfg' => $res['cfg'], 'counts' => $res['counts'], 'orderText' => $svc->orderText($res['rows']),
        ]);
    }
}
