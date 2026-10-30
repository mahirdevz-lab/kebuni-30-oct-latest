<?php

namespace App\Controllers;

use App\Services\MarginAnalysisService;
use App\Services\ProductCategoryService;

class MarginDashboardController extends BaseController
{
    protected MarginAnalysisService $marginService;
    protected ProductCategoryService $categoryService;

    public function __construct()
    {
        $this->marginService   = new MarginAnalysisService();
        $this->categoryService = new ProductCategoryService();
    }

    public function index()
    {
        $data = [
            'title'       => 'Margin Analysis',
            'breadcrumbs' => [
                ['label' => 'Dashboard'],
                ['label' => 'Margin Analysis'],
            ],
            'categories' => $this->categoryService->activeOptions(),
        ];

        return view('dashboard/margin', $data);
    }

    /**
     * Data trend moving average margin (line chart).
     */
    public function trend()
    {
        $from   = $this->request->getGet('from');
        $to     = $this->request->getGet('to');
        $status = $this->request->getGet('status');
        $window = (int) ($this->request->getGet('window') ?: 7);
        $categoryId = $this->request->getGet('category_id');

        return $this->response->setJSON([
            'success' => true,
            'data'    => $this->marginService->marginTrend($from, $to, $status, $window, $categoryId ? (int) $categoryId : null),
        ]);
    }

    /**
     * Data ranking margin per produk (bar chart + tabel barang margin rendah/tinggi).
     */
    public function ranking()
    {
        $from       = $this->request->getGet('from');
        $to         = $this->request->getGet('to');
        $status     = $this->request->getGet('status');
        $categoryId = $this->request->getGet('category_id');
        $limit      = (int) ($this->request->getGet('limit') ?: 10);

        $result = $this->marginService->marginRanking(
            $from,
            $to,
            $status,
            $categoryId ? (int) $categoryId : null,
            $limit
        );

        return $this->response->setJSON(['success' => true, 'data' => $result]);
    }
}
