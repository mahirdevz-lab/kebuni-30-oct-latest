<?php

namespace App\Controllers;

use App\Models\SalesModel;
use App\Services\SalesService;

class SalesController extends BaseController
{
    protected SalesService $service;

    public function __construct()
    {
        $this->service = new SalesService();
    }

    public function index()
    {
        $search = $this->request->getGet('search');

        $data = [
            'title'       => 'Sales',
            'breadcrumbs' => [
                ['label' => 'Master Data'],
                ['label' => 'Sales'],
            ],
            'salesList' => $this->service->paginate($search),
            'pager'     => $this->service->pager(),
            'search'    => $search,
        ];

        if ($this->request->isAJAX()) {
            return view('sales_master/_table', $data);
        }

        return view('sales_master/index', $data);
    }

    public function store()
    {
        $rules = (new SalesModel())->getValidationRules();

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->service->create($this->request->getPost(['name', 'phone', 'email', 'notes']));

        return $this->response->setJSON($result);
    }

    public function edit(int $id)
    {
        $sales = $this->service->find($id);

        if (! $sales) {
            return $this->response->setJSON(['success' => false, 'message' => 'Data tidak ditemukan.'])->setStatusCode(404);
        }

        return $this->response->setJSON(['success' => true, 'data' => $sales]);
    }

    public function update(int $id)
    {
        $rules = (new SalesModel())->getValidationRules();

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->service->update($id, $this->request->getPost(['name', 'phone', 'email', 'notes']));

        return $this->response->setJSON($result);
    }

    public function delete(int $id)
    {
        return $this->response->setJSON($this->service->delete($id));
    }
}