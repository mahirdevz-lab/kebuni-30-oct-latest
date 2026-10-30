<?php

namespace App\Controllers;

use App\Models\PricePeriodModel;
use App\Services\DestinationService;
use App\Services\OpexAreaService;
use App\Services\OpexCategoryService;
use App\Services\PackingMaterialService;
use App\Services\PricePeriodService;
use App\Services\ProductService;
use App\Services\TransportRouteService;

class PricePeriodController extends BaseController
{
    protected PricePeriodService $service;
    protected ProductService $productService;
    protected PackingMaterialService $packingService;
    protected TransportRouteService $transportRouteService;
    protected OpexAreaService $opexAreaService;
    protected OpexCategoryService $opexCategoryService;

    public function __construct()
    {
        $this->service               = new PricePeriodService();
        $this->productService        = new ProductService();
        $this->packingService        = new PackingMaterialService();
        $this->transportRouteService = new TransportRouteService();
        $this->opexAreaService       = new OpexAreaService();
        $this->opexCategoryService   = new OpexCategoryService();
    }

    public function index()
    {
        $search = $this->request->getGet('search');
        $status = $this->request->getGet('status');

        $periods = $this->service->paginate($search, $status);

        $data = [
            'title'       => 'Price Period',
            'breadcrumbs' => [
                ['label' => 'Pricing'],
                ['label' => 'Price Period'],
            ],
            'periods' => $periods,
            'pager'   => $this->service->pager(),
            'search'  => $search,
            'status'  => $status,
        ];

        if ($this->request->isAJAX()) {
            return view('price_period/_table', $data);
        }

        return view('price_period/index', $data);
    }

    public function create()
    {
        $data = [
            'title'       => 'Buat Price Period',
            'breadcrumbs' => [
                ['label' => 'Pricing'],
                ['label' => 'Price Period', 'url' => base_url('admin/price-period')],
                ['label' => 'Buat Baru'],
            ],
            'defaultStart' => date('Y-m-d'),
            'defaultEnd'   => date('Y-m-d', strtotime('+6 days')),
        ];

        return view('price_period/create', $data);
    }

    public function store()
    {
        $rules = (new PricePeriodModel())->getValidationRules();

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->service->create($this->request->getPost(['name', 'start_date', 'end_date', 'notes']));

        return $this->response->setJSON($result);
    }

    public function detail(int $id)
    {
        $period = $this->service->find($id);

        if (! $period) {
            return redirect()->to('/admin/price-period')->with('error', 'Price Period tidak ditemukan.');
        }

        $data = [
            'title'       => $period['period_code'] . ' - ' . $period['name'],
            'breadcrumbs' => [
                ['label' => 'Pricing'],
                ['label' => 'Price Period', 'url' => base_url('admin/price-period')],
                ['label' => $period['period_code']],
            ],
            'period'    => $period,
            'editable'  => $this->service->isEditable($period),
        ];

        return view('price_period/detail', $data);
    }

    public function updateHeader(int $id)
    {
        $result = $this->service->updateHeader($id, $this->request->getPost(['name', 'start_date', 'end_date', 'notes', 'labour_budget']));

        return $this->response->setJSON($result);
    }

    public function publish(int $id)
    {
        return $this->response->setJSON($this->service->publish($id));
    }

    public function close(int $id)
    {
        return $this->response->setJSON($this->service->close($id));
    }

    public function delete(int $id)
    {
        return $this->response->setJSON($this->service->delete($id));
    }

    public function duplicate(int $id)
    {
        $newStart = $this->request->getPost('start_date');
        $newEnd   = $this->request->getPost('end_date');

        if (! $newStart || ! $newEnd) {
            return $this->response->setJSON(['success' => false, 'message' => 'Start Date dan End Date wajib diisi.'])->setStatusCode(422);
        }

        return $this->response->setJSON($this->service->duplicate($id, $newStart, $newEnd));
    }

    // ---------------- PRODUCT PRICE TAB ----------------

    public function productPrices(int $id)
    {
        return $this->response->setJSON([
            'success'  => true,
            'lines'    => $this->service->getProductPrices($id),
            'products' => $this->productService->activeOptions(),
        ]);
    }

    public function saveProductPrices(int $id)
    {
        $items = json_decode($this->request->getPost('items') ?? '[]', true) ?: [];

        return $this->response->setJSON($this->service->saveProductPrices($id, $items));
    }

    // ---------------- PACKING PRICE TAB ----------------

    public function packingPrices(int $id)
    {
        return $this->response->setJSON([
            'success'          => true,
            'lines'            => $this->service->getPackingPrices($id),
            'packingMaterials' => $this->packingService->activeOptions(),
        ]);
    }

    public function savePackingPrices(int $id)
    {
        $items = json_decode($this->request->getPost('items') ?? '[]', true) ?: [];

        return $this->response->setJSON($this->service->savePackingPrices($id, $items));
    }

    // ---------------- TRANSPORT PRICE TAB ----------------

    public function transportPrices(int $id)
    {
        return $this->response->setJSON([
            'success' => true,
            'lines'   => $this->service->getTransportPrices($id),
            'routes'  => $this->transportRouteService->activeOptions(),
        ]);
    }

    public function saveTransportPrices(int $id)
    {
        $items = json_decode($this->request->getPost('items') ?? '[]', true) ?: [];

        return $this->response->setJSON($this->service->saveTransportPrices($id, $items));
    }

    // ---------------- LABOUR RATES ----------------

    public function labourRates(int $id)
    {
        return $this->response->setJSON([
            'success' => true,
            'rates'   => $this->service->getLabourRates($id),
        ]);
    }

    public function saveLabourRates(int $id)
    {
        // Tangkap array rates[labour_rate_id]
        $rates = $this->request->getPost('rates') ?? [];

        return $this->response->setJSON($this->service->saveLabourRates($id, $rates));
    }

    // ---------------- OPEX TAB ----------------

    public function opex(int $id)
    {
        return $this->response->setJSON([
            'success'    => true,
            'areas'      => $this->service->getOpex($id),
            'opexAreas'  => $this->opexAreaService->activeOptions(),
            'categories' => $this->opexCategoryService->activeOptions(),
        ]);
    }

    public function saveOpex(int $id)
    {
        $areas = json_decode($this->request->getPost('areas') ?? '[]', true) ?: [];

        return $this->response->setJSON($this->service->saveOpex($id, $areas));
    }
}

