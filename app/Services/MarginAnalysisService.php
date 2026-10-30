<?php

namespace App\Services;

use App\Models\SalesOrderItemModel;

class MarginAnalysisService
{
    protected SalesOrderItemModel $itemModel;

    public function __construct()
    {
        $this->itemModel = new SalesOrderItemModel();
    }

    /**
     * Trend moving average margin harian.
     * Formula margin: (omzet - total_cogs) / omzet * 100
     *
     * @return array{labels: array, margin_percent: array, moving_average: array, omzet: array, window: int}
     */
    public function marginTrend(?string $from, ?string $to, ?string $status, int $window = 7, ?int $categoryId = null): array
    {
        $rows = $this->itemModel->dailyMargin($from, $to, $status, $categoryId);

        $labels     = [];
        $margins    = [];
        $omzet      = [];
        $dailyCogs  = [];
        $totalOmzet = 0.0;
        $totalCogs  = 0.0;

        foreach ($rows as $r) {
            $omzetVal  = (float) $r['omzet'];
            $cogsVal   = (float) $r['total_cogs'];
            $marginPct = $omzetVal > 0 ? round((($omzetVal - $cogsVal) / $omzetVal) * 100, 2) : 0;

            $labels[]     = $r['order_date'];
            $margins[]    = $marginPct;
            $omzet[]      = round($omzetVal, 2);
            $dailyCogs[]  = $cogsVal;
            $totalOmzet  += $omzetVal;
            $totalCogs   += $cogsVal;
        }

        $totalProfit = $totalOmzet - $totalCogs;

        return [
            'labels'         => $labels,
            'margin_percent' => $margins,
            'moving_average' => $this->weightedMovingAverage($omzet, $dailyCogs, $window),
            'omzet'          => $omzet,
            'window'         => $window,
            'totals'         => [
                'omzet'          => round($totalOmzet, 2),
                'cogs'           => round($totalCogs, 2),
                'profit'         => round($totalProfit, 2),
                'margin_percent' => $totalOmzet > 0 ? round(($totalProfit / $totalOmzet) * 100, 2) : 0,
            ],
        ];
    }

    /**
     * Ranking product: barang margin rendah (perlu perhatian/review harga) vs margin tinggi.
     *
     * @return array{lowest: array, highest: array, all: array}
     */
    public function marginRanking(?string $from, ?string $to, ?string $status, ?int $categoryId, int $limit = 10): array
    {
        $rows = $this->itemModel->productMargin($from, $to, $status, $categoryId);

        $ranked = array_map(function ($r) {
            $omzetVal  = (float) $r['omzet'];
            $cogsVal   = (float) $r['total_cogs'];
            $profit    = $omzetVal - $cogsVal;
            $marginPct = $omzetVal > 0 ? round(($profit / $omzetVal) * 100, 2) : 0;

            return [
                'product_id'     => $r['product_id'],
                'product_code'   => $r['product_code'],
                'product_name'   => $r['product_name'],
                'unit'           => $r['unit'],
                'total_qty'      => (float) $r['total_qty'],
                'omzet'          => round($omzetVal, 2),
                'total_cogs'     => round($cogsVal, 2),
                'profit'         => round($profit, 2),
                'margin_percent' => $marginPct,
                'jumlah_order'   => (int) $r['jumlah_order'],
            ];
        }, $rows);

        usort($ranked, fn ($a, $b) => $a['margin_percent'] <=> $b['margin_percent']);

        return [
            'lowest'  => array_slice($ranked, 0, $limit),
            'highest' => array_slice(array_reverse($ranked), 0, $limit),
            'all'     => $ranked,
        ];
    }

    /**
     * Moving average margin TERBOBOT omzet: (sum profit / sum omzet) pada window terakhir.
     * Rata-rata sederhana dari persentase harian menyesatkan karena hari omzet kecil
     * berbobot sama dengan hari omzet besar.
     * Window dihitung berdasar jumlah titik data (hari yang ada transaksi), bukan hari kalender,
     * supaya tetap jalan meski data jarang/tidak setiap hari ada order.
     */
    protected function weightedMovingAverage(array $omzet, array $cogs, int $window): array
    {
        $result = [];
        $count  = count($omzet);

        for ($i = 0; $i < $count; $i++) {
            $start     = max(0, $i - $window + 1);
            $sumOmzet  = array_sum(array_slice($omzet, $start, $i - $start + 1));
            $sumCogs   = array_sum(array_slice($cogs, $start, $i - $start + 1));
            $result[]  = $sumOmzet > 0 ? round((($sumOmzet - $sumCogs) / $sumOmzet) * 100, 2) : 0;
        }

        return $result;
    }
}
