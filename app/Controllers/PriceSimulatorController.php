<?php

namespace App\Controllers;

use App\Models\PricePeriodLabourRateModel;
use App\Services\PackingMaterialService; // Ditambahkan untuk Packing
use App\Services\PaymentTermService;
use App\Services\PriceCalculationService;
use App\Services\PricePeriodService;
use App\Services\ProductService;
use App\Services\TransportRouteService;

class PriceSimulatorController extends BaseController
{
    protected PriceCalculationService $engine;
    protected PricePeriodService $periodService;
    protected ProductService $productService;
    protected TransportRouteService $routeService;
    protected PaymentTermService $paymentTermService;
    protected PackingMaterialService $PackingMaterialService; // Service Packing
    protected PricePeriodLabourRateModel $periodLabourRateModel;

    public function __construct()
    {
        $this->engine                = new PriceCalculationService();
        $this->periodService         = new PricePeriodService();
        $this->productService        = new ProductService();
        $this->routeService          = new TransportRouteService();
        $this->paymentTermService    = new PaymentTermService();
        $this->PackingMaterialService        = new PackingMaterialService(); // Init PackingMaterialService
        $this->periodLabourRateModel = new PricePeriodLabourRateModel();
    }

    public function index()
    {
        // Draft tetap diikutkan agar admin bisa mengecek harga sebelum publish.
        // dropdown() eksplisit: tanpa pager (BUG-5 audit pagination).
        $periods = $this->periodService->dropdown(null, 100);

        // Fetch Rate Kuli per Price Period dengan struktur Array List untuk JS
        $rawRates = $this->periodLabourRateModel
            ->select('price_period_labour_rates.*, labour_rates.name as labour_name, labour_rates.gender')
            ->join('labour_rates', 'labour_rates.id = price_period_labour_rates.labour_rate_id')
            ->findAll();

        $periodLabourRates = [];
        foreach ($rawRates as $r) {
            $periodId = $r['price_period_id'];
            
            // Format array list agar kompatibel dengan perulangan Javascript/View
            $periodLabourRates[$periodId][] = [
                'id'              => $r['labour_rate_id'],
                'name'            => $r['labour_name'] ?? ('Kuli ' . ucfirst($r['gender'] ?? '')),
                'gender'          => strtolower($r['gender'] ?? ''),
                'rate_per_person' => (float) $r['rate_per_person'],
            ];
        }

        $data = [
            'title'       => 'Price Simulator',
            'breadcrumbs' => [
                ['label' => 'Pricing'],
                ['label' => 'Price Simulator'],
            ],
            'periods'           => $periods,
            'products'          => $this->productService->activeOptions(),
            'routes'            => $this->routeService->activeOptions(),
            'paymentTerms'      => $this->paymentTermService->activeOptions(),
            'packings'          => $this->PackingMaterialService->activeOptions(), // Mengirim master data packing ke View
            'periodLabourRates' => $periodLabourRates, // Array list kuli per period
        ];

        return view('price_simulator/index', $data);
    }

    /**
     * AJAX: hitung 1 order (banyak item) sekaligus.
     * Payload: price_period_id, payment_term_id, labour_counts (JSON/Array), items (JSON array)
     */
    public function calculate()
    {
        $pricePeriodId = (int) $this->request->getPost('price_period_id');
        $paymentTermId = (int) $this->request->getPost('payment_term_id');

        // Menerima input kuli dinamis (labour_counts) atau fallback ke male/female legacy
        $labourCountsRaw = $this->request->getPost('labour_counts');
        $labourCounts    = is_string($labourCountsRaw) ? json_decode($labourCountsRaw, true) : ($labourCountsRaw ?? []);

        // Fallback support jika masih ada pengiriman parameter legacy male/female
        $maleLabourCount   = (int) ($this->request->getPost('male_labour_count') ?? 0);
        $femaleLabourCount = (int) ($this->request->getPost('female_labour_count') ?? 0);

        $items = json_decode($this->request->getPost('items') ?? '[]', true) ?: [];

        if (!$pricePeriodId || !$paymentTermId || empty($items)) {
            return $this->response
                ->setJSON(['success' => false, 'message' => 'Price Period, Payment Term, dan minimal 1 item wajib diisi.'])
                ->setStatusCode(422);
        }

        foreach ($items as &$item) {
            $item['price_period_id'] = $pricePeriodId;
        }
        unset($item);

        // Panggil calculation engine dengan menyertakan labourCounts & legacy count
        $result = $this->engine->calculateOrder(
            $items, 
            $paymentTermId, 
            $labourCounts, 
            $maleLabourCount, 
            $femaleLabourCount
        );

        return $this->response->setJSON(['success' => true] + $result);
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
