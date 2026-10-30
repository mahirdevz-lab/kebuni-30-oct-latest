<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\PriceCalculatorService;
use App\Models\TakingOrderModel;
use App\Models\TakingOrderItemModel;
use App\Models\TakingOrderItemPackingModel;
use App\Models\PricePeriodModel;
use App\Models\PaymentTermModel;
use App\Models\ProductModel;
use App\Models\TransportRouteModel;
use App\Models\PackingMaterialModel;
use App\Models\LabourRateModel;

class TakingOrderController extends BaseController
{
    protected $calculatorService;
    protected $takingOrderModel;
    protected $takingOrderItemModel;
    protected $takingOrderItemPackingModel;
    protected $pricePeriodModel;
    protected $paymentTermModel;
    protected $productModel;
    protected $transportRouteModel;
    protected $packingMaterialModel;
    protected $labourRateModel;

    public function __construct()
    {
        $this->calculatorService           = new PriceCalculatorService();
        $this->takingOrderModel            = new TakingOrderModel();
        $this->takingOrderItemModel       = new TakingOrderItemModel();
        $this->takingOrderItemPackingModel= new TakingOrderItemPackingModel();
        $this->pricePeriodModel            = new PricePeriodModel();
        $this->paymentTermModel            = new PaymentTermModel();
        $this->productModel                = new ProductModel();
        $this->transportRouteModel         = new TransportRouteModel();
        $this->packingMaterialModel        = new PackingMaterialModel();
        $this->labourRateModel             = new LabourRateModel();
    }

    /**
     * Halaman Form Create Taking Order / Penawaran
     */
    public function index()
    {
        $data = [
            'title'        => 'Buat Penawaran Harga (Taking Order)',
            'periods'      => $this->pricePeriodModel->where('is_active', 1)->findAll(),
            'paymentTerms' => $this->paymentTermModel->findAll(),
            'products'     => $this->productModel->findAll(),
            'routes'       => $this->transportRouteModel->findAll(),
            'packings'     => $this->packingMaterialModel->findAll(),
        ];

        return view('admin/taking_order/index', $data);
    }

    /**
     * AJAX: Hitung Simulasi Sebelum Disimpan (Sama seperti Simulator)
     */
    public function calculate()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(405)->setJSON(['message' => 'Method Not Allowed']);
        }

        try {
            $pricePeriodId   = (int) $this->request->getPost('price_period_id');
            $paymentTermId   = (int) $this->request->getPost('payment_term_id');
            $labourCountsRaw = $this->request->getPost('labour_counts');
            $itemsRaw        = $this->request->getPost('items');

            $labourCounts = !empty($labourCountsRaw) ? json_decode($labourCountsRaw, true) : [];
            $items        = !empty($itemsRaw) ? json_decode($itemsRaw, true) : [];

            if (empty($items)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Minimal harus ada 1 item produk.'
                ]);
            }

            // Panggil service kalkulasi
            $result = $this->calculatorService->calculate(
                $pricePeriodId,
                $paymentTermId,
                $labourCounts,
                $items
            );

            return $this->response->setJSON(array_merge(['success' => true], $result));

        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Store Data Taking Order ke DB (Save Transaction)
     */
    public function store()
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            $pricePeriodId   = (int) $this->request->getPost('price_period_id');
            $paymentTermId   = (int) $this->request->getPost('payment_term_id');
            $customerId      = $this->request->getPost('customer_id');
            $labourCountsRaw = $this->request->getPost('labour_counts');
            $itemsRaw        = $this->request->getPost('items');

            $labourCounts = !empty($labourCountsRaw) ? json_decode($labourCountsRaw, true) : [];
            $items        = !empty($itemsRaw) ? json_decode($itemsRaw, true) : [];

            // 1. Hitung Ulang via Service untuk Memastikan Data Valid & Presisi
            $calcResult = $this->calculatorService->calculate(
                $pricePeriodId,
                $paymentTermId,
                $labourCounts,
                $items
            );

            $summary = $calcResult['summary'];
            $lines   = $calcResult['lines'];

            // 2. Insert Header Taking Order
            $headerData = [
                'order_number'        => 'TO-' . date('Ymd') . '-' . sprintf('%04d', rand(1, 9999)),
                'customer_id'         => $customerId,
                'price_period_id'     => $pricePeriodId,
                'payment_term_id'     => $paymentTermId,
                'total_qty_kg'        => $summary['total_qty'],
                'total_labour_cost'   => $summary['total_labour_cost'],
                'labour_cost_per_kg'  => $summary['labour_cost_per_kg'],
                'total_cogs'          => $summary['total_cogs'],
                'grand_total'         => $summary['grand_total'],
                'estimated_profit'    => $summary['estimated_profit'],
                'profit_percent'      => $summary['estimated_profit_percent'],
                'labour_details_json' => json_encode($labourCounts), // Menyimpan histori input kuli
                'created_by'          => session()->get('user_id') ?? 1,
                'created_at'          => date('Y-m-d H:i:s'),
            ];

            $orderId = $this->takingOrderModel->insert($headerData);

            // 3. Insert Items & Packing Detail
            foreach ($lines as $line) {
                if (!empty($line['errors'])) {
                    throw new \Exception("Gagal menyimpan. Terjadi kesalahan pada produk " . $line['product']['name']);
                }

                $itemData = [
                    'taking_order_id'    => $orderId,
                    'product_id'         => $line['product']['id'],
                    'qty_kg'             => $line['qty'],
                    'cogs_sk'            => $line['cogs_sk'],
                    'transport_cost'     => $line['transport_cost'],
                    'packing_cost'       => $line['packing_cost'],
                    'labour_cost'        => $line['labour_cost'],
                    'cogs_destination'   => $line['cogs_destination'],
                    'recommended_price'  => $line['recommended_price'],
                    'selling_price'      => $line['selling_price'],
                    'subtotal'           => $line['subtotal_final'],
                ];

                $itemId = $this->takingOrderItemModel->insert($itemData);

                // Insert Multiple Packing Breakdown per Item
                if (!empty($line['packing_breakdown'])) {
                    foreach ($line['packing_breakdown'] as $pack) {
                        $this->takingOrderItemPackingModel->insert([
                            'taking_order_item_id' => $itemId,
                            'packing_material_id'  => $pack['packing_id'],
                            'qty_pcs'              => $pack['qty_pcs'],
                            'capacity_kg'          => $pack['capacity_kg'],
                            'price_per_unit'       => $pack['price_per_unit'],
                            'total_cost'           => $pack['total_cost'],
                            'cost_per_kg_product'  => $pack['cost_per_kg_product'],
                        ]);
                    }
                }
            }

            $db->transCommit();

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Penawaran Taking Order berhasil disimpan!',
                'order_id' => $orderId
            ]);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Gagal menyimpan order: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * AJAX: Ambil Tarif Kuli saat Period Diganti
     */
    public function getLabourRatesByPeriod()
    {
        $periodId = $this->request->getGet('price_period_id');

        if (!$periodId) {
            return $this->response->setJSON(['rates' => []]);
        }

        $rates = $this->labourRateModel
            ->where('price_period_id', $periodId)
            ->findAll();

        return $this->response->setJSON(['rates' => $rates]);
    }

    /**
     * Komponen Price Period untuk form order (redesign COGS 2026-10-18):
     * daftar OPEX area + labour budget + transport & packing yg ada harganya.
     */
    public function componentsByPeriod()
    {
        $pricePeriodId = (int) $this->request->getGet('price_period_id');

        if (! $pricePeriodId) {
            return $this->response->setJSON(['success' => false, 'message' => 'price_period_id wajib diisi.'])->setStatusCode(422);
        }

        $opexModel  = new \App\Models\PricePeriodOpexAreaModel();
        $period     = (new \App\Models\PricePeriodModel())->find($pricePeriodId);
        $transports = (new \App\Models\PricePeriodTransportModel())->getByPeriod($pricePeriodId);
        $packings   = (new \App\Models\PricePeriodPackingModel())->getByPeriod($pricePeriodId);

        return $this->response->setJSON([
            'success'       => true,
            'opex'          => $opexModel->optionsByPeriod($pricePeriodId),
            'labour_budget' => (float) ($period['labour_budget'] ?? 0),
            'transports'    => array_map(static fn (array $t) => [
                'id'               => (int) $t['transport_route_id'],
                'origin'           => $t['origin'] ?? '',
                'destination_name' => $t['destination_name'] ?? '',
                'cost_per_kg'      => (float) ($t['cost_per_kg'] ?? 0),
            ], $transports),
            'packings'      => array_map(static fn (array $p) => [
                'id'             => (int) $p['packing_material_id'],
                'name'           => $p['name'] ?? '',
                'code'           => $p['code'] ?? '',
                'price_per_unit' => (float) ($p['price_per_unit'] ?? $p['price'] ?? 0),
            ], $packings),
        ]);
    }
}
