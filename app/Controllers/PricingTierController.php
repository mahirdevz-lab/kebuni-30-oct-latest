<?php

namespace App\Controllers;

use App\Models\PricingTierModel;
use App\Services\PricingTierService;

class PricingTierController extends BaseController
{
    protected PricingTierService $service;

    public function __construct()
    {
        $this->service = new PricingTierService();
    }

    public function index()
    {
        $search = $this->request->getGet('search');
        $status = $this->request->getGet('status');

        $tiers = $this->service->paginate($search, $status);

        $data = [
            'title'       => 'Pricing Tier',
            'breadcrumbs' => [
                ['label' => 'Pricing'],
                ['label' => 'Pricing Tier'],
            ],
            'tiers'  => $tiers,
            'pager'  => $this->service->pager(),
            'search' => $search,
            'status' => $status,
        ];

        if ($this->request->isAJAX()) {
            return view('pricing_tier/_table', $data);
        }

        return view('pricing_tier/index', $data);
    }

    public function store()
    {
        $rules = (new PricingTierModel())->getValidationRules();

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->service->create($this->request->getPost([
            'name', 'min_qty', 'max_qty', 'markup_percent', 'start_date', 'end_date', 'notes',
        ]));

        return $this->response->setJSON($result);
    }

    public function edit(int $id)
    {
        $tier = $this->service->find($id);

        if (! $tier) {
            return $this->response->setJSON(['success' => false, 'message' => 'Data tidak ditemukan.'])->setStatusCode(404);
        }

        return $this->response->setJSON(['success' => true, 'data' => $tier]);
    }

    public function update(int $id)
    {
        $rules = (new PricingTierModel())->getValidationRules();

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->service->update($id, $this->request->getPost([
            'name', 'min_qty', 'max_qty', 'markup_percent', 'start_date', 'end_date', 'notes',
        ]));

        return $this->response->setJSON($result);
    }

    public function delete(int $id)
    {
        return $this->response->setJSON($this->service->delete($id));
    }
}
