<?php
namespace App\Services;

class OrderService
{
    protected $priceCalc;
    public function __construct() { $this->priceCalc = new \App\Services\PriceCalculationService(); }

    public function simulatePrice(array $payload)
    {
        $lines = []; $summary = ['total_qty' => 0, 'grand_total' => 0, 'estimated_profit' => 0];
        foreach ($payload['items'] as $item) {
            // Pastikan keys dikirim ke kalkulator persis dengan yang diharapkan oleh Service Kalkulator lama Anda
            $res = $this->priceCalc->calculateItem([
                'price_period_id' => $payload['price_period_id'],
                'product_id'      => $item['product_id'],
                'qty'             => $item['qty'],
                'route_id'        => $item['transport_route_id'] ?? null,
                'labour_id'       => $item['labour_id'] ?? null,
                'packing_id'      => $item['packing_id'] ?? null,
            ]);
            $lines[] = $res;
            $summary['grand_total'] += $res['subtotal_final'];
            $summary['estimated_profit'] += $res['estimated_profit'];
        }
        return ['lines' => $lines, 'summary' => $summary];
    }

    public function saveOrder(array $payload)
    {
        // Masukkan logika Insert ke OrderModel dan OrderItemModel di sini
        return ['status' => true, 'order_number' => 'ORD-'.time()];
    }
}