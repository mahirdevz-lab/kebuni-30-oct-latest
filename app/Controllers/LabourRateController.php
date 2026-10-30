<?php

namespace App\Controllers;

use App\Services\LabourRateService;

class LabourRateController extends BaseController
{
    protected LabourRateService $labourRateService;

    public function __construct()
    {
        $this->labourRateService = new LabourRateService();
    }

    public function index()
    {
        $search = $this->request->getGet('search');
        $gender = $this->request->getGet('gender');
        $status = $this->request->getGet('status');

        $data = [
            'title'       => 'Master Labour Rate',
            'labourRates' => $this->labourRateService->paginate($search, $gender, $status),
            'pager'       => $this->labourRateService->pager(),
        ];

        return view('labour_rates/index', $data);
    }

    /**
     * Endpoint API/AJAX untuk dipanggil oleh Price Simulator via GET
     */
    public function getByPeriod()
    {
        $pricePeriodId = (int) $this->request->getGet('price_period_id');

        if (! $pricePeriodId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'price_period_id wajib diisi.',
                'rates'   => [],
            ])->setStatusCode(400);
        }

        $rates = $this->labourRateService->getRatesByPeriod($pricePeriodId);

        return $this->response->setJSON([
            'success' => true,
            'rates'   => $rates,
        ]);
    }

    public function store()
    {
        $result = $this->labourRateService->create($this->request->getPost());

        if (! $result['success']) {
            return redirect()->back()->withInput()->with('errors', $result['errors']);
        }

        return redirect()->to('/labour-rates')->with('success', $result['message']);
    }

    public function update(int $id)
    {
        $result = $this->labourRateService->update($id, $this->request->getPost());

        if (! $result['success']) {
            return redirect()->back()->withInput()->with('errors', $result['errors'] ?? $result['message']);
        }

        return redirect()->to('/labour-rates')->with('success', $result['message']);
    }

    public function delete(int $id)
    {
        $result = $this->labourRateService->delete($id);

        return $this->response->setJSON($result);
    }
}