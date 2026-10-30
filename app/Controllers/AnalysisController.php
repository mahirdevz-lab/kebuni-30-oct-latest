<?php

namespace App\Controllers;

use App\Services\ProfitShrinkageService;

/** Analisa dampak penyusutan ke laba rugi & matriks keputusan produk Kebuni Mart. */
class AnalysisController extends BaseController
{
    public function index()
    {
        $from = $this->request->getGet('from') ?: date('Y-m-01');
        $to   = $this->request->getGet('to') ?: date('Y-m-d');
        if (strtotime($to) < strtotime($from)) {
            [$from, $to] = [$to, $from];
        }

        return view('pos/analysis', [
            'title'       => 'Analisa Laba & Produk',
            'breadcrumbs' => [['label' => 'POS'], ['label' => 'Analisa Laba & Produk']],
            'a'           => (new ProfitShrinkageService())->build($from, $to),
            'actions'     => ProfitShrinkageService::ACTIONS,
        ]);
    }
}
