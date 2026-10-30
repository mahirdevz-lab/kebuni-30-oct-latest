<?php

namespace App\Controllers;

use App\Services\PosDashboardService;

class PosDashboardController extends BaseController
{
    public function index()
    {
        return view('pos/today', [
            'title' => 'Kondisi Toko Hari Ini', 'breadcrumbs' => [['label' => 'POS'], ['label' => 'Kondisi Toko']],
            'd' => (new PosDashboardService())->build(),
        ]);
    }
}
