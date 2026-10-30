<?php

namespace App\Controllers;

use App\Models\OpexCategoryModel;
use App\Services\OpexCategoryService;

class OpexCategoryController extends BaseController
{
    protected OpexCategoryService $service;

    public function __construct()
    {
        $this->service = new OpexCategoryService();
    }

    public function index()
    {
        $search = $this->request->getGet('search');
        $status = $this->request->getGet('status');

        $categories = $this->service->paginate($search, $status);

        $data = [
            'title'       => 'Kategori OPEX',
            'breadcrumbs' => [
                ['label' => 'Master Data'],
                ['label' => 'Kategori OPEX'],
            ],
            'categories' => $categories,
            'pager'      => $this->service->pager(),
            'search'     => $search,
            'status'     => $status,
        ];

        if ($this->request->isAJAX()) {
            return view('opex_category/_table', $data);
        }

        return view('opex_category/index', $data);
    }

    public function store()
    {
        $rules = (new OpexCategoryModel())->getValidationRules();

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->service->create($this->request->getPost(['code', 'name', 'notes']));

        return $this->response->setJSON($result);
    }

    public function edit(int $id)
    {
        $category = $this->service->find($id);

        if (! $category) {
            return $this->response->setJSON(['success' => false, 'message' => 'Data tidak ditemukan.'])->setStatusCode(404);
        }

        return $this->response->setJSON(['success' => true, 'data' => $category]);
    }

    public function update(int $id)
    {
        $model = new OpexCategoryModel();
        $rules = $model->getValidationRules();
        $rules['code'] = str_replace('{id}', (string) $id, $rules['code']);

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->service->update($id, $this->request->getPost(['code', 'name', 'notes']));

        return $this->response->setJSON($result);
    }

    public function delete(int $id)
    {
        return $this->response->setJSON($this->service->delete($id));
    }
}
