<?php

namespace App\Controllers;

use App\Models\ProductCategoryModel;
use App\Services\ProductCategoryService;

class ProductCategoryController extends BaseController
{
    protected ProductCategoryService $service;

    public function __construct()
    {
        $this->service = new ProductCategoryService();
    }

    public function index()
    {
        $search = $this->request->getGet('search');
        $status = $this->request->getGet('status');

        $categories = $this->service->paginate($search, $status);

        $data = [
            'title'       => 'Product Category',
            'breadcrumbs' => [
                ['label' => 'Master Data'],
                ['label' => 'Product Category'],
            ],
            'categories' => $categories,
            'pager'      => $this->service->pager(),
            'search'     => $search,
            'status'     => $status,
        ];

        if ($this->request->isAJAX()) {
            return view('product_category/_table', $data);
        }

        return view('product_category/index', $data);
    }

    public function store()
    {
        $rules = (new ProductCategoryModel())->getValidationRules();

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        return $this->response->setJSON($this->service->create($this->request->getPost(['name'])));
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
        $model = new ProductCategoryModel();
        $rules = $model->getValidationRules();
        $rules['name'] = str_replace('{id}', (string) $id, $rules['name']);

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        return $this->response->setJSON($this->service->update($id, $this->request->getPost(['name'])));
    }

    public function delete(int $id)
    {
        return $this->response->setJSON($this->service->delete($id));
    }
}
