<?php

namespace App\Controllers;

use App\Models\SupplierModel;
use App\Services\SupplierService;

class SupplierController extends BaseController
{
    protected SupplierService $supplierService;

    public function __construct()
    {
        $this->supplierService = new SupplierService();
    }

    public function index()
    {
        $search = $this->request->getGet('search');
        $status = $this->request->getGet('status');

        $suppliers = $this->supplierService->paginate($search, $status);

        $data = [
            'title'       => 'Supplier',
            'breadcrumbs' => [
                ['label' => 'Master Data'],
                ['label' => 'Supplier'],
            ],
            'suppliers' => $suppliers,
            'pager'     => $this->supplierService->pager(),
            'search'    => $search,
            'status'    => $status,
        ];

        if ($this->request->isAJAX()) {
            return view('supplier/_table', $data);
        }

        return view('supplier/index', $data);
    }

    public function store()
    {
        $rules = (new SupplierModel())->getValidationRules();

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Data tidak valid.',
                'errors'  => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->supplierService->create($this->request->getPost([
            'nama_supplier', 'alamat', 'kota', 'nomor_telepon', 'email', 'contact_person',
        ]));

        return $this->response->setJSON($result);
    }

    public function edit(int $id)
    {
        $supplier = $this->supplierService->find($id);

        if (! $supplier) {
            return $this->response->setJSON(['success' => false, 'message' => 'Data tidak ditemukan.'])->setStatusCode(404);
        }

        return $this->response->setJSON(['success' => true, 'data' => $supplier]);
    }

    public function update(int $id)
    {
        $rules = (new SupplierModel())->getValidationRules();

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Data tidak valid.',
                'errors'  => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->supplierService->update($id, $this->request->getPost([
            'nama_supplier', 'alamat', 'kota', 'nomor_telepon', 'email', 'contact_person',
        ]));

        return $this->response->setJSON($result);
    }

    public function delete(int $id)
    {
        return $this->response->setJSON($this->supplierService->delete($id));
    }

    public function toggleStatus(int $id)
    {
        return $this->response->setJSON($this->supplierService->toggleStatus($id));
    }
}
