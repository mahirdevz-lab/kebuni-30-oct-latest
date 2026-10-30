<?php

namespace App\Controllers;

use App\Models\PackingMaterialModel;
use App\Services\PackingMaterialService;

class PackingMaterialController extends BaseController
{
    protected PackingMaterialService $service;

    public function __construct()
    {
        $this->service = new PackingMaterialService();
    }

    public function index()
    {
        $search = $this->request->getGet('search');
        $status = $this->request->getGet('status');

        $materials = $this->service->paginate($search, $status);

        $data = [
            'title'       => 'Packing Material',
            'breadcrumbs' => [
                ['label' => 'Master Data'],
                ['label' => 'Packing Material'],
            ],
            'materials' => $materials,
            'pager'     => $this->service->pager(),
            'search'    => $search,
            'status'    => $status,
        ];

        if ($this->request->isAJAX()) {
            return view('packing_material/_table', $data);
        }

        return view('packing_material/index', $data);
    }

    public function store()
    {
        $rules = (new PackingMaterialModel())->getValidationRules();

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->service->create($this->request->getPost(['code', 'name', 'unit', 'capacity_kg', 'notes']));

        return $this->response->setJSON($result);
    }

    public function edit(int $id)
    {
        $material = $this->service->find($id);

        if (! $material) {
            return $this->response->setJSON(['success' => false, 'message' => 'Data tidak ditemukan.'])->setStatusCode(404);
        }

        return $this->response->setJSON(['success' => true, 'data' => $material]);
    }

    public function update(int $id)
    {
        $model = new PackingMaterialModel();
        $rules = $model->getValidationRules();
        $rules['code'] = str_replace('{id}', (string) $id, $rules['code']);

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->service->update($id, $this->request->getPost(['code', 'name', 'unit', 'capacity_kg', 'notes']));

        return $this->response->setJSON($result);
    }

    public function delete(int $id)
    {
        return $this->response->setJSON($this->service->delete($id));
    }
}
