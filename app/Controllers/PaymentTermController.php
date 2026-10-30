<?php

namespace App\Controllers;

use App\Models\PaymentTermModel;
use App\Services\PaymentTermService;

class PaymentTermController extends BaseController
{
    protected PaymentTermService $service;

    public function __construct()
    {
        $this->service = new PaymentTermService();
    }

    public function index()
    {
        $search = $this->request->getGet('search');
        $status = $this->request->getGet('status');

        $terms = $this->service->paginate($search, $status);

        $data = [
            'title'       => 'Payment Term',
            'breadcrumbs' => [
                ['label' => 'Pricing'],
                ['label' => 'Payment Term'],
            ],
            'terms'  => $terms,
            'pager'  => $this->service->pager(),
            'search' => $search,
            'status' => $status,
        ];

        if ($this->request->isAJAX()) {
            return view('payment_term/_table', $data);
        }

        return view('payment_term/index', $data);
    }

    public function store()
    {
        $rules = (new PaymentTermModel())->getValidationRules();

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->service->create($this->request->getPost(['name', 'days', 'daily_interest_percent', 'notes']));

        return $this->response->setJSON($result);
    }

    public function edit(int $id)
    {
        $term = $this->service->find($id);

        if (! $term) {
            return $this->response->setJSON(['success' => false, 'message' => 'Data tidak ditemukan.'])->setStatusCode(404);
        }

        return $this->response->setJSON(['success' => true, 'data' => $term]);
    }

    public function update(int $id)
    {
        $rules = (new PaymentTermModel())->getValidationRules();

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->service->update($id, $this->request->getPost(['name', 'days', 'daily_interest_percent', 'notes']));

        return $this->response->setJSON($result);
    }

    public function delete(int $id)
    {
        return $this->response->setJSON($this->service->delete($id));
    }
}
