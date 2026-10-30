<?php

namespace App\Controllers;

use App\Models\ProductModel;
use App\Services\PackingMaterialService;
use App\Services\ProductCategoryService;
use App\Services\ProductService;

class ProductController extends BaseController
{
    protected ProductService $productService;
    protected ProductCategoryService $categoryService;
    protected PackingMaterialService $packingService;

    public function __construct()
    {
        $this->productService  = new ProductService();
        $this->categoryService = new ProductCategoryService();
        $this->packingService  = new PackingMaterialService();
    }

    public function index()
    {
        $search     = $this->request->getGet('search');
        $categoryId = $this->request->getGet('category_id');
        $status     = $this->request->getGet('status');

        $products = $this->productService->paginate($search, $categoryId ? (int) $categoryId : null, $status);

        $data = [
            'title'       => 'Products',
            'breadcrumbs' => [
                ['label' => 'Master Data'],
                ['label' => 'Products'],
            ],
            'products'   => $products,
            'pager'      => $this->productService->pager(),
            'recipeProductIds' => $this->productService->recipeProductIds(),
            'search'     => $search,
            'status'     => $status,
            'categoryId' => $categoryId,
            'categories' => $this->categoryService->activeOptions(),
        ];

        if ($this->request->isAJAX()) {
            return view('product/_table', $data);
        }

        return view('product/index', $data);
    }

    public function store()
    {
        $rules = (new ProductModel())->getValidationRules();

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->productService->create($this->request->getPost([
            'product_code', 'product_name', 'category_id', 'unit', 'shrinkage_type', 'shrinkage_value', 'notes',
        ]));

        return $this->response->setJSON($result);
    }

    public function edit(int $id)
    {
        $product = $this->productService->find($id);

        if (! $product) {
            return $this->response->setJSON(['success' => false, 'message' => 'Product tidak ditemukan.'])->setStatusCode(404);
        }

        return $this->response->setJSON(['success' => true, 'data' => $product]);
    }

    public function update(int $id)
    {
        $model = new ProductModel();
        $rules = $model->getValidationRules();
        $rules['product_code'] = str_replace('{id}', (string) $id, $rules['product_code']);

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->productService->update($id, $this->request->getPost([
            'product_code', 'product_name', 'category_id', 'unit', 'shrinkage_type', 'shrinkage_value', 'notes',
        ]));

        return $this->response->setJSON($result);
    }

    public function delete(int $id)
    {
        return $this->response->setJSON($this->productService->delete($id));
    }

    /**
     * Ambil recipe packing milik product (untuk mengisi modal recipe).
     */
    public function recipe(int $id)
    {
        $product = $this->productService->find($id);

        if (! $product) {
            return $this->response->setJSON(['success' => false, 'message' => 'Product tidak ditemukan.'])->setStatusCode(404);
        }

        return $this->response->setJSON([
            'success'          => true,
            'product'          => $product,
            'recipe'           => $this->productService->getRecipe($id),
            'packingMaterials' => $this->packingService->activeOptions(),
        ]);
    }

    /**
     * Simpan ulang seluruh recipe packing milik product.
     * Payload: items (JSON): [{packing_material_id, quantity_usage}, ...]
     */
    public function saveRecipe(int $id)
    {
        $items = json_decode($this->request->getPost('items') ?? '[]', true) ?: [];

        return $this->response->setJSON($this->productService->saveRecipe($id, $items));
    }
}
